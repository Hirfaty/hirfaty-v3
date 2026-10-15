<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Services\PayPal\PayPalClient;
use App\Services\PayPal\PayPalException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Giving back money a buyer paid online, when the desk cancels with "refund"
 * ticked (OrderCancellationService).
 *
 *   - PayPal: refunded here, through PayPal's API, from the capture that took
 *     the money — the whole of it when the whole purchase goes, the cancelled
 *     orders' share otherwise. PayPal's webhook confirms it later.
 *   - Payzone: Hirfaty has no way to refund a card itself, so the desk gets
 *     the usual "refund to make" alert and does it from Payzone's
 *     back-office; Payzone's REFUNDED notification records it.
 *
 * A PayPal refund that fails falls back to the same alert, so a refund the
 * desk asked for is never silently dropped.
 */
class RefundService
{
    public function __construct(
        private PayPalClient $paypal,
        private PaymentService $payments,
    ) {}

    /**
     * Refund what was paid online for these orders, purchase by purchase.
     *
     * @param  Collection<int, Order>  $orders  orders still marked paid online
     * @return list<array{purchase: string, provider: string, outcome: string, amount: float, currency: string}>
     *                                                                                                           outcome: `refunded`, `pending` (PayPal still processing), `manual`
     *                                                                                                           (Payzone — the desk refunds by hand) or `failed` (alert raised)
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

            $results[] = $payment->isPayPal() && filled($payment->provider_transaction_id)
                ? $this->viaPayPal($payment, $group, $reason)
                : $this->byHand($payment, $group, $reason);
        }

        return $results;
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array{purchase: string, provider: string, outcome: string, amount: float, currency: string}
     */
    private function viaPayPal(Payment $payment, Collection $orders, string $reason): array
    {
        $whole = $this->isWholePurchase($payment, $orders);
        $amount = $whole
            ? (float) $payment->amount
            : min((float) $payment->amount, $payment->inChargedCurrency($this->madTotal($orders)));

        $result = [
            'purchase' => $payment->group_reference,
            'provider' => Payment::PROVIDER_PAYPAL,
            'outcome' => 'failed',
            'amount' => $amount,
            'currency' => $payment->currency,
        ];

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

            return $result;
        }

        $status = strtoupper((string) ($refund['status'] ?? ''));

        if (! in_array($status, ['COMPLETED', 'PENDING'], true)) {
            $this->fallBack($payment, $orders, "Remboursement PayPal refusé ({$status}) — à faire depuis le tableau de bord PayPal. {$reason}");

            return $result;
        }

        DB::transaction(function () use ($payment, $orders, $whole, $status, $refund) {
            Order::withoutGlobalScope(Order::SETTLED_SCOPE)
                ->whereIn('id', $orders->pluck('id'))
                ->update(['payment_status' => 'refunded']);

            $attributes = [
                'provider_status' => match (true) {
                    $status === 'PENDING' => 'REFUND_PENDING',
                    $whole => 'REFUNDED',
                    default => 'PARTIALLY_REFUNDED',
                },
                'payload' => $refund,
            ];

            if ($whole) {
                $this->payments->refund($payment, $attributes);
            } else {
                $payment->update($attributes);
            }
        });

        return ['outcome' => $status === 'PENDING' ? 'pending' : 'refunded'] + $result;
    }

    /**
     * Payzone: the desk refunds from Payzone's back-office, reminded by the
     * bell; Payzone's REFUNDED notification then records it.
     *
     * @param  Collection<int, Order>  $orders
     * @return array{purchase: string, provider: string, outcome: string, amount: float, currency: string}
     */
    private function byHand(Payment $payment, Collection $orders, string $reason): array
    {
        $this->fallBack($payment, $orders, 'Annulation avec remboursement — à faire depuis le back-office Payzone. '.$reason);

        return [
            'purchase' => $payment->group_reference,
            'provider' => (string) $payment->provider,
            'outcome' => 'manual',
            'amount' => $this->isWholePurchase($payment, $orders)
                ? (float) $payment->amount
                : min((float) $payment->amount, $payment->inChargedCurrency($this->madTotal($orders))),
            'currency' => $payment->currency,
        ];
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
