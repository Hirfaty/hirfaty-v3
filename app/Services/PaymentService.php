<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\Admin\CardRefundNeeded;
use App\Services\Payzone\PayzoneClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Online payments for a purchase — card through Payzone, or PayPal — from the
 * redirect to the provider to the end of the purchase's life as "awaiting
 * payment".
 *
 * CheckoutService writes an online purchase's orders and holds their stock,
 * but leaves them unannounced and hidden (Order's settled scope). From there
 * this service is the only thing that moves them:
 *
 *   - the provider confirms the money → settle(): the orders become paid and
 *     confirmed, earnings are written, the cart lines go, and the buyer and
 *     the desk are told. For Payzone that is its signed CHARGED notification
 *     (handleNotification below); for PayPal, a completed capture
 *     (PayPalPaymentService).
 *   - the buyer gives up → cancel() hands the stock back.
 *   - nobody does anything → expireStale() (scheduled) releases it.
 *
 * Paying online is the only way to pay: there is no cash on delivery to fall
 * back to.
 *
 * For Payzone, only its notification can mark a purchase paid. The browser
 * coming back on the success URL proves nothing, so it only ever shows a
 * "confirming" page that waits for the notification.
 */
class PaymentService
{
    /** Payzone statuses that end a payment attempt without money moving. */
    private const FAILED_STATUSES = ['DECLINED', 'CANCELLED', 'ERROR', 'AUTH_REVERSED'];

    /** Payzone statuses meaning money went back to the buyer. */
    private const REFUNDED_STATUSES = ['REFUNDED', 'CHARGED_BACK'];

    public function __construct(
        private PayzoneClient $payzone,
        private CheckoutService $checkout,
        private OrderStatusService $orderStatus,
        private AdminNotificationService $adminNotifications,
    ) {}

    /** Whether checkout should offer card payment at all. */
    public function cardAvailable(): bool
    {
        return $this->payzone->isConfigured();
    }

    /**
     * The orders of one of the buyer's online purchases still waiting for
     * payment — empty once it is paid or released.
     *
     * @return Collection<int, Order>
     */
    public function openPurchase(User $buyer, string $reference): Collection
    {
        return $this->unsettled()
            ->where('buyer_id', $buyer->id)
            ->where('group_reference', $reference)
            ->with('items')
            ->get();
    }

    /** What a purchase costs the buyer, in MAD: every order plus delivery. */
    public function amountFor(Collection $orders): float
    {
        return round(
            (float) $orders->sum(fn (Order $order) => (float) $order->total_amount + (float) $order->delivery_fee),
            2,
        );
    }

    /**
     * How one of the buyer's open purchases is to be paid — `card` or
     * `paypal` — or null once there is nothing left to pay.
     */
    public function methodOf(User $buyer, string $reference): ?string
    {
        return $this->openPurchase($buyer, $reference)->first()?->payment_method;
    }

    /** Whether any order of the purchase is still waiting for its money. */
    public function isOpen(string $reference): bool
    {
        return $this->unsettled()->where('group_reference', $reference)->exists();
    }

    /**
     * Start a payment attempt and build the form that sends the buyer to
     * Payzone. Null when the purchase is not waiting for a card payment (paid
     * already, released, paid with PayPal, or not this buyer's).
     *
     * @return array{url: string, payload: string, signature: string}|null
     */
    public function launch(User $buyer, string $reference, string $locale): ?array
    {
        $orders = $this->openPurchase($buyer, $reference);

        if ($orders->isEmpty() || $orders->first()->payment_method !== Order::PAYMENT_CARD) {
            return null;
        }

        $payment = Payment::create([
            'group_reference' => $reference,
            'buyer_id' => $buyer->id,
            'provider' => Payment::PROVIDER_PAYZONE,
            'charge_id' => Payment::newChargeId(),
            'amount' => $this->amountFor($orders),
            'currency' => 'MAD',
            'status' => Payment::STATUS_PENDING,
        ]);

        return $this->payzone->launchForm($payment, $buyer, "Hirfaty — commande {$reference}", $locale);
    }

    /**
     * Act on one of Payzone's notifications. The caller has already checked
     * its signature.
     *
     * Payzone resends a notification until it gets a 200, for up to three
     * days, so everything here is idempotent: the same CHARGED twice settles
     * the purchase once.
     *
     * @param  array<string, mixed>  $data
     * @return bool false when the notification names no payment of ours
     */
    public function handleNotification(array $data): bool
    {
        $payment = Payment::where('provider', Payment::PROVIDER_PAYZONE)
            ->where('charge_id', (string) ($data['id'] ?? ''))
            ->first();

        if (! $payment) {
            return false;
        }

        $status = strtoupper((string) ($data['status'] ?? ''));
        $transaction = $this->approvedTransaction($data);

        match (true) {
            $status === 'CHARGED' && $transaction !== null => $this->settle(
                $payment,
                (float) ($data['lineItem']['amount'] ?? $transaction['amount'] ?? 0),
                (string) ($data['lineItem']['currency'] ?? $transaction['currency'] ?? $payment->currency),
                [
                    'provider_status' => 'CHARGED',
                    'provider_transaction_id' => $data['internalId'] ?? $transaction['gatewayProvidedId'] ?? null,
                    'card_brand' => isset($data['paymentMethod']) ? mb_substr((string) $data['paymentMethod'], 0, 40) : null,
                    'payload' => $data,
                ],
            ),
            in_array($status, self::FAILED_STATUSES, true) => $this->fail($payment, ['provider_status' => $status, 'payload' => $data]),
            in_array($status, self::REFUNDED_STATUSES, true) => $this->refund($payment, ['provider_status' => $status, 'payload' => $data]),
            // Pending states (CHARGE_PENDING, AUTHORIZED, ...): note it and
            // wait for the next notification.
            default => $payment->update(['provider_status' => $status ?: null, 'payload' => $data]),
        };

        return true;
    }

    /**
     * Place the purchase this payment was for, now the provider has taken the
     * money.
     *
     * Idempotent — providers resend, and PayPal may report one capture twice
     * (the buyer's return, then its webhook) — so a payment already paid
     * changes nothing. Money that no live order stands behind, or that does
     * not match what the attempt asked for, is flagged for the desk to refund
     * instead.
     *
     * @param  float  $charged  what the provider says it took, in `$currency`
     * @param  array<string, mixed>  $attributes  the provider's facts for the payment
     *                                            row (status, transaction id, payload…)
     */
    public function settle(Payment $payment, float $charged, string $currency, array $attributes): void
    {
        $refundReason = null;
        $placed = collect();

        DB::transaction(function () use ($payment, $charged, $currency, $attributes, &$refundReason, &$placed) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if ($payment->isPaid()) {
                return;
            }

            $payment->update([
                'status' => Payment::STATUS_PAID,
                'paid_at' => now(),
            ] + $attributes);

            if (strtoupper($currency) !== $payment->currency || $charged + 0.01 < (float) $payment->amount) {
                $refundReason = 'Montant débité ('.number_format($charged, 2, '.', '').' '.strtoupper($currency)
                    .') ne couvre pas le total de la commande ('.number_format((float) $payment->amount, 2, '.', '').' '.$payment->currency
                    .') — commande non validée.';

                return;
            }

            $orders = $payment->orders()->lockForUpdate()->get();
            $live = $orders->reject(fn (Order $order) => $order->status === 'cancelled');

            if ($live->isEmpty()) {
                $refundReason = 'Paiement reçu après l’expiration ou l’annulation de la commande.';

                return;
            }

            if ($live->contains(fn (Order $order) => $order->isPaidOnline())) {
                $refundReason = 'Commande déjà payée par une autre transaction — paiement en double.';

                return;
            }

            /*
             * Paid means confirmed: the desk has nothing left to approve, so
             * the order skips "pending" and its next step is shipping. Written
             * directly rather than through OrderStatusService, whose
             * OrderConfirmed email would land alongside the OrderPlaced one
             * announce() sends below — two emails for one event.
             */
            foreach ($live as $order) {
                $order->update(['payment_status' => 'paid', 'status' => 'confirmed']);
                $order->statusEvents()->create(['status' => 'confirmed']);
                $this->checkout->recordEarning($order);
            }

            $placed = $live;
            $this->clearPurchasedLines($live->first()->buyer, $live);
        });

        if ($refundReason !== null) {
            $this->flagRefund($payment->fresh(), $refundReason);

            return;
        }

        if ($placed->isNotEmpty()) {
            $this->checkout->announce($placed->all(), $placed->first()->buyer);
        }
    }

    /**
     * A declined or abandoned attempt. The purchase itself stays open — the
     * buyer can try again, with the same card or another — until it expires.
     *
     * @param  array<string, mixed>  $attributes  the provider's facts for the payment row
     */
    public function fail(Payment $payment, array $attributes): void
    {
        DB::transaction(function () use ($payment, $attributes) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->first();

            // A late DECLINED for an attempt that already went through changes nothing.
            if ($payment->isPaid()) {
                return;
            }

            $payment->update(['status' => Payment::STATUS_FAILED] + $attributes);
        });
    }

    /**
     * Money back to the buyer — done by the desk from Payzone's back-office or
     * PayPal's dashboard. Recorded on the payment and the orders; the orders'
     * own status is the desk's to change.
     *
     * @param  array<string, mixed>  $attributes  the provider's facts for the payment row
     */
    public function refund(Payment $payment, array $attributes): void
    {
        DB::transaction(function () use ($payment, $attributes) {
            $payment->update(['status' => Payment::STATUS_REFUNDED] + $attributes);

            $payment->orders()
                ->whereIn('payment_method', Order::ONLINE_METHODS)
                ->where('payment_status', 'paid')
                ->update(['payment_status' => 'refunded']);
        });
    }

    /**
     * The transaction that actually took the money, as Payzone's sample
     * callback checks it: APPROVED with a result code of 0.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function approvedTransaction(array $data): ?array
    {
        foreach (array_reverse((array) ($data['transactions'] ?? [])) as $transaction) {
            if (is_array($transaction)
                && ($transaction['state'] ?? null) === 'APPROVED'
                && (int) ($transaction['resultCode'] ?? -1) === 0) {
                return $transaction;
            }
        }

        return null;
    }

    /**
     * Where a buyer stands with a purchase they are paying online, for the
     * page Payzone or PayPal sends them back to. Null when it is not theirs.
     *
     * `state` is `placed` (paid — show the confirmation),
     * `released` (expired or cancelled), or `awaiting` (waiting for the
     * provider's word, or for the buyer to try again). `method` is `card` or
     * `paypal`; `charged` is what the latest attempt asked the provider for,
     * when that was not dirhams (PayPal).
     *
     * @return array{reference: string, method: string, state: string, last_attempt: string|null, amount: float, charged: array{amount: float, currency: string, locale: string}|null}|null
     */
    public function status(User $buyer, string $reference): ?array
    {
        $orders = Order::withoutGlobalScope(Order::SETTLED_SCOPE)
            ->where('buyer_id', $buyer->id)
            ->where('group_reference', $reference)
            ->get();

        if ($orders->isEmpty()) {
            return null;
        }

        $live = $orders->reject(fn (Order $order) => $order->status === 'cancelled');

        $state = match (true) {
            $live->isEmpty() => 'released',
            // Paid (or refunded since), or an order from before online payment
            // existed — either way there is nothing left to pay here.
            $live->contains(fn (Order $order) => ! $order->paysOnline()
                || $order->payment_status !== 'pending') => 'placed',
            default => 'awaiting',
        };

        $attempt = Payment::where('group_reference', $reference)->latest('id')->first();

        return [
            'reference' => $reference,
            'method' => (string) $orders->first()->payment_method,
            'state' => $state,
            'last_attempt' => $attempt?->status,
            'amount' => $this->amountFor($orders),
            'charged' => $attempt && $attempt->currency !== 'MAD'
                ? [
                    'amount' => (float) $attempt->amount,
                    'currency' => $attempt->currency,
                    'locale' => (string) config("currency.supported.{$attempt->currency}.locale", 'en-US'),
                ]
                : null,
        ];
    }

    /**
     * The buyer cancels an online purchase before paying for it.
     */
    public function cancel(User $buyer, string $reference): bool
    {
        $orders = $this->unsettled()
            ->where('buyer_id', $buyer->id)
            ->where('group_reference', $reference)
            ->get();

        if ($orders->isEmpty()) {
            return false;
        }

        $this->release($orders, $orders->first()->payment_method === Order::PAYMENT_PAYPAL
            ? 'Paiement PayPal abandonné.'
            : 'Paiement par carte abandonné.');

        return true;
    }

    /**
     * Hand back whatever the buyer's earlier, unpaid online purchases are
     * holding, before they check out again.
     *
     * Without this, a buyer who closed the payment tab and returned to
     * checkout would be told the last piece is out of stock — because their
     * own abandoned purchase is holding it.
     */
    public function releaseOpenPurchases(User $buyer): void
    {
        $this->unsettled()
            ->where('buyer_id', $buyer->id)
            ->get()
            ->groupBy('group_reference')
            ->each(fn (Collection $orders) => $this->release($orders, 'Remplacée par une nouvelle commande.'));
    }

    /**
     * Release every online purchase nobody has paid for in time.
     *
     * Measured from the latest sign of life — the checkout, or the buyer's
     * most recent trip to the payment page — so a buyer retrying a declined
     * card is not cut off mid-attempt.
     *
     * @return int how many purchases were released
     */
    public function expireStale(): int
    {
        $cutoff = now()->subMinutes(max(1, (int) config('services.payzone.expire_after_minutes', 60)));

        $groups = $this->unsettled()
            ->where('created_at', '<=', $cutoff)
            ->get()
            ->groupBy('group_reference');

        $released = 0;

        foreach ($groups as $reference => $orders) {
            $lastAttempt = Payment::where('group_reference', $reference)->max('created_at');

            if ($lastAttempt !== null && Carbon::parse($lastAttempt)->greaterThan($cutoff)) {
                continue;
            }

            $this->release($orders, 'Paiement en ligne non reçu à temps.');
            $released++;
        }

        return $released;
    }

    /**
     * Cancel an unpaid online purchase and give its stock back. Quietly: the
     * buyer was never told it was placed, so there is nothing to un-tell —
     * and the cart still holds what they wanted.
     *
     * @param  Collection<int, Order>  $orders
     */
    private function release(Collection $orders, string $reason): void
    {
        DB::transaction(function () use ($orders, $reason) {
            foreach ($orders as $order) {
                // Re-read under lock: a CHARGED notification (or a PayPal
                // capture) may have landed since the caller fetched it.
                $order = Order::withoutGlobalScope(Order::SETTLED_SCOPE)
                    ->whereKey($order->id)
                    ->lockForUpdate()
                    ->first();

                if (! $order
                    || $order->status === 'cancelled'
                    || ! $order->paysOnline()
                    || $order->payment_status !== 'pending') {
                    continue;
                }

                $order->update([
                    'status' => 'cancelled',
                    'payment_status' => 'failed',
                    'cancellation_reason' => $reason,
                ]);

                $this->orderStatus->restoreStock($order);
                $order->statusEvents()->create(['status' => 'cancelled', 'note' => $reason]);
            }

            Payment::whereIn('group_reference', $orders->pluck('group_reference')->unique())
                ->where('status', Payment::STATUS_PENDING)
                ->update(['status' => Payment::STATUS_EXPIRED]);
        });
    }

    /**
     * Money a provider took that no live order stands behind — the desk has
     * to give it back from Payzone's back-office or PayPal's dashboard.
     *
     * @param  float|null  $amount  the part owed, in MAD; null for the whole
     *                              attempt. Turned into the attempt's own
     *                              currency, since that is what gets refunded.
     */
    public function flagRefund(Payment $payment, string $reason, ?float $amount = null, ?string $orderReference = null): void
    {
        $owed = $amount === null
            ? (float) $payment->amount
            : min((float) $payment->amount, $payment->inChargedCurrency($amount));

        Log::critical(ucfirst((string) $payment->provider).' payment needs a manual refund', [
            'charge_id' => $payment->charge_id,
            'purchase' => $payment->group_reference,
            'amount' => $owed,
            'currency' => $payment->currency,
            'reason' => $reason,
        ]);

        try {
            $this->adminNotifications->notifyAdmins(new CardRefundNeeded(
                purchase: $payment->group_reference,
                chargeId: $payment->charge_id,
                amount: $owed,
                reason: $reason,
                orderReference: $orderReference,
                provider: (string) $payment->provider,
                currency: (string) $payment->currency,
                transactionId: $payment->provider_transaction_id,
            ));
        } catch (\Throwable $e) {
            Log::error('Refund alert failed: '.$e->getMessage());
        }
    }

    /**
     * Online orders still waiting for their money, and not yet released.
     */
    private function unsettled()
    {
        return Order::withoutGlobalScope(Order::SETTLED_SCOPE)
            ->whereIn('payment_method', Order::ONLINE_METHODS)
            ->where('payment_status', 'pending')
            ->where('status', '!=', 'cancelled');
    }

    /**
     * Take what was just bought out of the buyer's cart — and only that, since
     * an online checkout keeps the cart open and the buyer may have added to it
     * while paying.
     *
     * @param  Collection<int, Order>  $orders
     */
    private function clearPurchasedLines(?User $buyer, Collection $orders): void
    {
        if (! $buyer) {
            return;
        }

        $cart = Cart::where('user_id', $buyer->id)->first();

        if (! $cart) {
            return;
        }

        $orders->loadMissing('items');

        foreach ($orders->flatMap(fn (Order $order) => $order->items) as $item) {
            $cart->items()
                ->where('product_id', $item->product_id)
                ->when(
                    $item->product_variant_id === null,
                    fn ($query) => $query->whereNull('product_variant_id'),
                    fn ($query) => $query->where('product_variant_id', $item->product_variant_id),
                )
                ->delete();
        }
    }
}
