<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Services\PayPal\PayPalClient;
use App\Services\PayPal\PayPalException;
use App\Services\Payzone\PayzoneClient;
use App\Services\Payzone\PayzoneException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Giving back money a buyer paid online, when the desk cancels with "refund"
 * ticked (OrderCancellationService).
 *
 * Both providers are refunded here, through their API, from the payment that
 * took the money — the whole of it when the whole purchase goes, the
 * cancelled orders' share otherwise:
 *
 *   - PayPal: from the capture; PayPal's webhook confirms it later.
 *   - Payzone (card): the `REFUND` command on the charge, once Payzone has
 *     issued the API caller credentials. Without them, the desk gets the
 *     usual "refund to make" alert and does it from Payzone's back-office;
 *     Payzone's REFUNDED notification then records it.
 *
 * A refund that fails falls back to the same alert, so a refund the desk
 * asked for is never silently dropped.
 */
class RefundService
{
    public function __construct(
        private PayPalClient $paypal,
        private PayzoneClient $payzone,
        private PaymentService $payments,
    ) {}

    /**
     * Refund what was paid online for these orders, purchase by purchase.
     *
     * @param  Collection<int, Order>  $orders  orders still marked paid online
     * @return list<array{purchase: string, provider: string, outcome: string, amount: float, currency: string}>
     *                                                                                                           outcome: `refunded`, `pending` (the provider is still processing it), `manual`
     *                                                                                                           (no API to do it — the desk refunds by hand) or `failed` (alert raised)
     */
    public function refund(Collection $orders, string $reason): array
    {
        $results = [];

        foreach ($orders->filter(fn (Order $order) => $order->isPaidOnline())->groupBy('group_reference') as $reference => $group) {
            $payment = Payment::where('group_reference', $reference)
                ->where('status', Payment::STATUS_PAID)
                ->latest('id')
                ->first();

            if (! $payment) {
                continue;
            }

            $result = match (true) {
                $payment->isPayPal() && filled($payment->provider_transaction_id) => $this->viaPayPal($payment, $group, $reason),
                $this->isAutomatic($payment) => $this->viaPayzone($payment, $group, $reason),
                default => $this->byHand($payment, $group, $reason),
            };

            if ($result !== null) {
                $results[] = $result;
            }
        }

        return $results;
    }

    /**
     * Whether ticking "refund" gives the money back on the spot, or leaves
     * the desk an alert to do it by hand — for the cancel dialog to say which.
     */
    public function isAutomatic(Payment $payment): bool
    {
        return $payment->isPayPal()
            ? filled($payment->provider_transaction_id)
            : $payment->provider === Payment::PROVIDER_PAYZONE && $this->payzone->canRefund();
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array{purchase: string, provider: string, outcome: string, amount: float, currency: string}
     */
    private function viaPayPal(Payment $payment, Collection $orders, string $reason): array
    {
        $whole = $this->isWholePurchase($payment, $orders);
        $amount = $this->amountFor($payment, $orders, $whole);

        try {
            $refund = $this->paypal->refundCapture(
                $payment->provider_transaction_id,
                $amount,
                $payment->currency,
                // One refund per set of orders: a double click, or a retry
                // after a timeout, gets PayPal's first answer back.
                'refund-'.$payment->charge_id.'-'.$orders->pluck('id')->sort()->implode('-'),
                "Hirfaty — remboursement de la commande {$payment->group_reference}",
            );
        } catch (PayPalException $e) {
            Log::error('PayPal refund failed', [
                'charge_id' => $payment->charge_id,
                'error' => $e->getMessage(),
            ]);

            $this->fallBack($payment, $orders, 'Remboursement PayPal automatique impossible ('
                .($e->issue ?? $e->status ?? 'erreur').') — à faire depuis le tableau de bord PayPal. '.$reason);

            return $this->result($payment, 'failed', $amount);
        }

        $status = strtoupper((string) ($refund['status'] ?? ''));

        if (! in_array($status, ['COMPLETED', 'PENDING'], true)) {
            $this->fallBack($payment, $orders, "Remboursement PayPal refusé ({$status}) — à faire depuis le tableau de bord PayPal. {$reason}");

            return $this->result($payment, 'failed', $amount);
        }

        $this->record($payment, $orders, $whole, $status === 'PENDING', $refund);

        return $this->result($payment, $status === 'PENDING' ? 'pending' : 'refunded', $amount);
    }

    /**
     * A card refund through Payzone's API.
     *
     * Payzone takes no idempotency key, so the payment row is locked around
     * the call and the orders re-read under it: a second click, or a second
     * desk, finds them already refunded and asks Payzone for nothing (null).
     *
     * @param  Collection<int, Order>  $orders
     * @return array{purchase: string, provider: string, outcome: string, amount: float, currency: string}|null
     */
    private function viaPayzone(Payment $payment, Collection $orders, string $reason): ?array
    {
        return DB::transaction(function () use ($payment, $orders, $reason) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->first();
            $orders = Order::whereIn('id', $orders->pluck('id'))
                ->where('payment_status', 'paid')
                ->lockForUpdate()
                ->get();

            if ($orders->isEmpty()) {
                return null;
            }

            $whole = $this->isWholePurchase($payment, $orders);
            $amount = $this->amountFor($payment, $orders, $whole);

            try {
                $charge = $this->payzone->refundCharge($payment->charge_id, $amount);
            } catch (PayzoneException $e) {
                Log::error('Payzone refund failed', [
                    'charge_id' => $payment->charge_id,
                    'error' => $e->getMessage(),
                ]);

                $this->fallBack($payment, $orders, 'Remboursement carte automatique impossible ('
                    .($e->errorCode ?? $e->status ?? 'erreur').') — à faire depuis le back-office Payzone. '.$reason);

                return $this->result($payment, 'failed', $amount);
            }

            /*
             * As Payzone's guide reads an answer: the last transaction on the
             * charge is the one just asked for, and REFUND + APPROVED means
             * the money is on its way back. The charge's own status stays
             * CHARGED after a partial refund, so it says little here.
             */
            $last = collect($charge['transactions'] ?? [])->last() ?? [];
            $type = strtoupper(trim((string) ($last['type'] ?? '')));
            $state = strtoupper(trim((string) ($last['state'] ?? '')));
            $pending = strtoupper((string) ($charge['status'] ?? '')) === 'REFUND_PENDING' || $state === 'PENDING';

            if ($type !== 'REFUND' || ! ($state === 'APPROVED' || $pending)) {
                $this->fallBack($payment, $orders, 'Remboursement carte refusé par Payzone ('
                    .($state ?: 'réponse inattendue').') — à faire depuis le back-office Payzone. '.$reason);

                return $this->result($payment, 'failed', $amount);
            }

            $this->record($payment, $orders, $whole, $pending, $charge);

            return $this->result($payment, $pending ? 'pending' : 'refunded', $amount);
        });
    }

    /**
     * No API to do it with: the desk refunds from Payzone's back-office,
     * reminded by the bell; Payzone's REFUNDED notification then records it.
     *
     * @param  Collection<int, Order>  $orders
     * @return array{purchase: string, provider: string, outcome: string, amount: float, currency: string}
     */
    private function byHand(Payment $payment, Collection $orders, string $reason): array
    {
        $this->fallBack($payment, $orders, 'Annulation avec remboursement — à faire depuis le back-office Payzone. '.$reason);

        return $this->result($payment, 'manual', $this->amountFor($payment, $orders, $this->isWholePurchase($payment, $orders)));
    }

    /**
     * What the provider accepted, on the orders and the payment.
     *
     * @param  Collection<int, Order>  $orders
     * @param  array<string, mixed>  $payload  the provider's answer
     */
    private function record(Payment $payment, Collection $orders, bool $whole, bool $pending, array $payload): void
    {
        DB::transaction(function () use ($payment, $orders, $whole, $pending, $payload) {
            Order::withoutGlobalScope(Order::SETTLED_SCOPE)
                ->whereIn('id', $orders->pluck('id'))
                ->update(['payment_status' => 'refunded']);

            $attributes = [
                'provider_status' => match (true) {
                    $pending => 'REFUND_PENDING',
                    $whole => 'REFUNDED',
                    default => 'PARTIALLY_REFUNDED',
                },
                'payload' => $payload,
            ];

            if ($whole) {
                $this->payments->refund($payment, $attributes);
            } else {
                $payment->update($attributes);
            }
        });
    }

    /** @param  Collection<int, Order>  $orders */
    private function fallBack(Payment $payment, Collection $orders, string $reason): void
    {
        $this->payments->flagRefund(
            $payment,
            trim($reason),
            $this->isWholePurchase($payment, $orders) ? null : $this->madTotal($orders),
            $orders->first()?->reference,
        );
    }

    /**
     * @return array{purchase: string, provider: string, outcome: string, amount: float, currency: string}
     */
    private function result(Payment $payment, string $outcome, float $amount): array
    {
        return [
            'purchase' => $payment->group_reference,
            'provider' => (string) $payment->provider,
            'outcome' => $outcome,
            'amount' => $amount,
            'currency' => $payment->currency,
        ];
    }

    /**
     * What goes back, in the charged currency: the whole payment, or these
     * orders' share — never more than was taken.
     *
     * @param  Collection<int, Order>  $orders
     */
    private function amountFor(Payment $payment, Collection $orders, bool $whole): float
    {
        return $whole
            ? (float) $payment->amount
            : min((float) $payment->amount, $payment->inChargedCurrency($this->madTotal($orders)));
    }

    /**
     * Whether these orders are everything the payment paid for, with nothing
     * refunded before — the only case where the whole captured amount goes
     * back, rather than a share recomputed from MAD (which could round a cent
     * past what is left).
     *
     * @param  Collection<int, Order>  $orders
     */
    private function isWholePurchase(Payment $payment, Collection $orders): bool
    {
        $purchase = $payment->orders()->whereIn('payment_status', ['paid', 'refunded'])->get(['id', 'payment_status']);

        return $purchase->where('payment_status', 'refunded')->isEmpty()
            && $purchase->pluck('id')->diff($orders->pluck('id'))->isEmpty();
    }

    /**
     * What these orders cost the buyer, in MAD: products plus delivery.
     *
     * @param  Collection<int, Order>  $orders
     */
    private function madTotal(Collection $orders): float
    {
        return round((float) $orders->sum(fn (Order $order) => (float) $order->total_amount + (float) $order->delivery_fee), 2);
    }
}
