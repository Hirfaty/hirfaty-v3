<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\PayPal\PayPalClient;
use App\Services\PayPal\PayPalException;
use Illuminate\Support\Facades\Log;

/**
 * Paying for a purchase with PayPal.
 *
 * The purchase itself is the same as a card one: CheckoutService holds its
 * stock, PaymentService settles, releases and expires it. What differs is how
 * the money moves:
 *
 *   1. launch() opens a PayPal order for the attempt and sends the buyer to
 *      PayPal to approve it.
 *   2. PayPal sends them back; capture() takes the money. Unlike Payzone's
 *      redirect, this proves something: PayPal's answer to the capture is
 *      the payment itself, so a COMPLETED capture settles the purchase on
 *      the spot.
 *   3. Webhooks cover the rest — a buyer who approved and then closed the tab
 *      (APPROVED → capture() here), a capture PayPal held back (PENDING →
 *      COMPLETED or DENIED), and refunds made from PayPal's dashboard.
 *
 * Approval is not payment. A purchase that was released or already paid by
 * the time the buyer approves is simply never captured, so no money moves
 * and nothing needs refunding.
 *
 * PayPal cannot charge MAD, so each attempt is priced in another currency —
 * see chargeFor().
 */
class PayPalPaymentService
{
    /**
     * Capture failures that are the buyer's funding being refused, as opposed
     * to PayPal or the network having trouble: the attempt is over.
     */
    private const DECLINED_ISSUES = [
        'INSTRUMENT_DECLINED', 'PAYER_ACTION_REQUIRED', 'ORDER_NOT_APPROVED',
        'PAYER_CANNOT_PAY', 'TRANSACTION_REFUSED', 'COMPLIANCE_VIOLATION',
        'MAX_NUMBER_OF_PAYMENT_ATTEMPTS_EXCEEDED', 'ORDER_EXPIRED',
    ];

    public function __construct(
        private PayPalClient $paypal,
        private PaymentService $payments,
        private ExchangeRateService $rates,
    ) {}

    /** Whether checkout should offer PayPal at all. */
    public function available(): bool
    {
        return $this->paypal->isConfigured();
    }

    /**
     * What PayPal charges a buyer who was reading `$displayCurrency`: that
     * currency when PayPal takes it, at the rate quoted to them (`$quotedRate`,
     * stored on the order at checkout); otherwise the configured fallback
     * (EUR) at today's rate. Null when no rate is known — better to refuse
     * the attempt than to charge 240 euros for 240 dirhams.
     *
     * @return array{currency: string, rate: float}|null
     */
    public function chargeFor(?string $displayCurrency, ?float $quotedRate = null): ?array
    {
        $display = strtoupper((string) $displayCurrency);

        if (in_array($display, PayPalClient::CURRENCIES, true)) {
            $rate = $quotedRate !== null && $quotedRate > 0 ? $quotedRate : $this->rateFor($display);

            return $rate === null ? null : ['currency' => $display, 'rate' => $rate];
        }

        $fallback = strtoupper((string) config('services.paypal.currency', 'EUR'));

        if (! in_array($fallback, PayPalClient::CURRENCIES, true)) {
            return null;
        }

        $rate = $this->rateFor($fallback);

        return $rate === null ? null : ['currency' => $fallback, 'rate' => $rate];
    }

    /**
     * chargeFor(), plus how the figure is written — for the checkout page,
     * which states what PayPal will take before the buyer picks it.
     *
     * @return array{currency: string, rate: float, locale: string}|null
     */
    public function quoteFor(string $displayCurrency): ?array
    {
        $charge = $this->chargeFor($displayCurrency);

        return $charge === null ? null : $charge + [
            'locale' => (string) config("currency.supported.{$charge['currency']}.locale", 'en-US'),
        ];
    }

    /**
     * Start a PayPal attempt and return the page where the buyer approves
     * it. Null when the purchase is not waiting for a PayPal payment (paid
     * already, released, paid by card, or not this buyer's), or when PayPal
     * would not open the order — the attempt is then marked failed, so the
     * payment page says so.
     */
    public function launch(User $buyer, string $reference, string $locale): ?string
    {
        $orders = $this->payments->openPurchase($buyer, $reference);

        if ($orders->isEmpty() || $orders->first()->payment_method !== Order::PAYMENT_PAYPAL) {
            return null;
        }

        $first = $orders->first();
        $charge = $this->chargeFor(
            $first->display_currency,
            $first->display_rate === null ? null : (float) $first->display_rate,
        );

        $payment = Payment::create([
            'group_reference' => $reference,
            'buyer_id' => $buyer->id,
            'provider' => Payment::PROVIDER_PAYPAL,
            'charge_id' => Payment::newChargeId(),
            'amount' => $charge ? round($this->payments->amountFor($orders) * $charge['rate'], 2) : 0,
            'currency' => $charge['currency'] ?? strtoupper((string) config('services.paypal.currency', 'EUR')),
            'exchange_rate' => $charge['rate'] ?? null,
            'status' => Payment::STATUS_PENDING,
        ]);

        if ($charge === null || (float) $payment->amount <= 0) {
            Log::error('PayPal attempt could not be priced', ['purchase' => $reference, 'currency' => $payment->currency]);
            $payment->update(['status' => Payment::STATUS_FAILED, 'provider_status' => 'NO_RATE']);

            return null;
        }

        try {
            $created = $this->paypal->createOrder(
                $payment,
                "Hirfaty — commande {$reference}",
                $locale,
                route('payments.paypal.return', $payment->charge_id),
                route('payments.paypal.return', [$payment->charge_id, 'outcome' => 'cancel']),
            );
        } catch (PayPalException $e) {
            Log::error('PayPal refused to open an order', [
                'purchase' => $reference,
                'charge_id' => $payment->charge_id,
                'error' => $e->getMessage(),
            ]);
            $payment->update([
                'status' => Payment::STATUS_FAILED,
                'provider_status' => mb_substr($e->issue ?? 'CREATE_FAILED', 0, 40),
                'payload' => $e->body ?: null,
            ]);

            return null;
        }

        $payment->update([
            'provider_order_id' => $created['id'],
            'provider_status' => mb_substr($created['status'], 0, 40) ?: null,
        ]);

        return $created['approve_url'];
    }

    /**
     * Take the money for an attempt the buyer has approved.
     *
     * Called from the buyer's return and from the APPROVED webhook, possibly
     * at the same moment: the capture is idempotent at PayPal (one request id
     * per attempt) and settling is idempotent here.
     *
     * @return string `success` when the money is taken or PayPal is still
     *                clearing it (the payment page then waits), `failure` when
     *                this attempt is over
     */
    public function capture(Payment $payment): string
    {
        if ($payment->isPaid()) {
            return 'success';
        }

        if ($payment->status !== Payment::STATUS_PENDING || blank($payment->provider_order_id)) {
            return 'failure';
        }

        /*
         * Released (expired, cancelled, replaced by a new checkout) or paid
         * by another attempt while the buyer was on PayPal. The approval is
         * left to lapse: nothing was taken, so there is nothing to refund.
         */
        if (! $this->payments->isOpen($payment->group_reference)) {
            $payment->update(['status' => Payment::STATUS_EXPIRED, 'provider_status' => 'NOT_CAPTURED']);

            return 'failure';
        }

        try {
            $order = $this->paypal->captureOrder($payment->provider_order_id, 'capture-'.$payment->charge_id);
        } catch (PayPalException $e) {
            if ($e->issue === 'ORDER_ALREADY_CAPTURED') {
                return $this->recheck($payment);
            }

            if (in_array($e->issue, self::DECLINED_ISSUES, true)) {
                $this->payments->fail($payment, [
                    'provider_status' => mb_substr((string) $e->issue, 0, 40),
                    'payload' => $e->body ?: null,
                ]);

                return 'failure';
            }

            /*
             * A timeout or PayPal having trouble: nobody knows whether the
             * money moved. The attempt stays pending — PayPal's webhook will
             * say which — and the buyer is shown the "confirming" page.
             */
            Log::error('PayPal capture did not answer', [
                'charge_id' => $payment->charge_id,
                'error' => $e->getMessage(),
            ]);

            return 'success';
        }

        return $this->apply($payment, $this->captureIn($order), $order);
    }

    /**
     * The buyer came back from PayPal without paying. The attempt stays
     * pending (and expires with the purchase): there was no refusal to record,
     * and the buyer may still approve the same order from another tab.
     */
    public function abandon(Payment $payment): void
    {
        if ($payment->status === Payment::STATUS_PENDING) {
            $payment->update(['provider_status' => 'PAYER_CANCELLED']);
        }
    }

    /**
     * Act on one of PayPal's webhook events. The caller has already checked
     * its signature.
     *
     * PayPal resends an event until it gets a 2xx, for up to three days, so
     * everything here is idempotent.
     *
     * @param  array<string, mixed>  $event
     * @return bool false when the event names no payment of ours, or is a
     *              kind this app does not act on
     */
    public function handleWebhook(array $event): bool
    {
        $type = (string) ($event['event_type'] ?? '');
        $resource = (array) ($event['resource'] ?? []);

        $payment = $this->paymentFor($type, $resource);

        if (! $payment) {
            return false;
        }

        switch ($type) {
            case 'CHECKOUT.ORDER.APPROVED':
                // The buyer approved but may never come back: take the money now.
                $this->capture($payment);

                return true;

            case 'PAYMENT.CAPTURE.COMPLETED':
            case 'PAYMENT.CAPTURE.PENDING':
            case 'PAYMENT.CAPTURE.DENIED':
            case 'PAYMENT.CAPTURE.DECLINED':
                $this->apply($payment, $resource, $event);

                return true;

            case 'PAYMENT.CAPTURE.REFUNDED':
            case 'PAYMENT.CAPTURE.REVERSED':
                $this->refunded($payment, $type, $resource, $event);

                return true;
        }

        return false;
    }

    /**
     * What one capture means for its attempt.
     *
     * @param  array<string, mixed>|null  $capture  PayPal's capture object
     * @param  array<string, mixed>  $payload  what to keep on the row for disputes
     * @return string `success` or `failure`, as capture()
     */
    private function apply(Payment $payment, ?array $capture, array $payload): string
    {
        $status = strtoupper((string) ($capture['status'] ?? ''));

        switch ($status) {
            case 'COMPLETED':
                $this->payments->settle(
                    $payment,
                    (float) ($capture['amount']['value'] ?? 0),
                    (string) ($capture['amount']['currency_code'] ?? ''),
                    [
                        'provider_status' => 'COMPLETED',
                        'provider_transaction_id' => $capture['id'] ?? null,
                        // Set when the buyer paid with a card inside PayPal.
                        'card_brand' => isset($payload['payment_source']['card']['brand'])
                            ? mb_substr((string) $payload['payment_source']['card']['brand'], 0, 40)
                            : null,
                        'payload' => $payload,
                    ],
                );

                return 'success';

            case 'DECLINED':
            case 'DENIED':
            case 'FAILED':
                $this->payments->fail($payment, [
                    'provider_status' => $status,
                    'provider_transaction_id' => $capture['id'] ?? null,
                    'payload' => $payload,
                ]);

                return 'failure';
        }

        /*
         * PENDING (PayPal is reviewing it, or the account must accept the
         * currency first) or something unforeseen: note it and wait for the
         * webhook that settles it.
         */
        if (! $payment->isPaid()) {
            $reason = $capture['status_details']['reason'] ?? null;

            $payment->update([
                'provider_status' => mb_substr(trim($status.' '.($reason ?? '')), 0, 40) ?: null,
                'provider_transaction_id' => $capture['id'] ?? $payment->provider_transaction_id,
                'payload' => $payload,
            ]);
        }

        return 'success';
    }

    /**
     * Money back to the buyer, made from PayPal's dashboard. A partial refund
     * is noted but leaves the purchase paid: which order it was for is the
     * desk's to know.
     *
     * @param  array<string, mixed>  $refund  PayPal's refund object
     * @param  array<string, mixed>  $event
     */
    private function refunded(Payment $payment, string $type, array $refund, array $event): void
    {
        $refundedSoFar = (float) ($refund['seller_payable_breakdown']['total_refunded_amount']['value']
            ?? $refund['amount']['value']
            ?? 0);

        $whole = $type === 'PAYMENT.CAPTURE.REVERSED'
            || $refundedSoFar + 0.01 >= (float) $payment->amount;

        if ($whole) {
            $this->payments->refund($payment, [
                'provider_status' => $type === 'PAYMENT.CAPTURE.REVERSED' ? 'REVERSED' : 'REFUNDED',
                'payload' => $event,
            ]);

            return;
        }

        $payment->update(['provider_status' => 'PARTIALLY_REFUNDED', 'payload' => $event]);
    }

    /**
     * The attempt PayPal is already done with: read its order back, to learn
     * how the earlier capture went.
     */
    private function recheck(Payment $payment): string
    {
        try {
            $order = $this->paypal->showOrder($payment->provider_order_id);
        } catch (PayPalException $e) {
            Log::error('PayPal order could not be read back', ['charge_id' => $payment->charge_id, 'error' => $e->getMessage()]);

            return 'success';
        }

        return $this->apply($payment, $this->captureIn($order), $order);
    }

    /**
     * The capture inside an order PayPal returned — one purchase unit, one
     * capture, since every order here is opened that way.
     *
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>|null
     */
    private function captureIn(array $order): ?array
    {
        $capture = $order['purchase_units'][0]['payments']['captures'][0] ?? null;

        return is_array($capture) ? $capture : null;
    }

    /**
     * The attempt an event is about. PayPal copies our charge id (sent as
     * `custom_id` and `invoice_id`) onto orders and captures; a refund made
     * from the dashboard may carry neither, but links back to its capture.
     *
     * @param  array<string, mixed>  $resource
     */
    private function paymentFor(string $type, array $resource): ?Payment
    {
        $query = fn () => Payment::where('provider', Payment::PROVIDER_PAYPAL);

        $chargeId = $resource['custom_id']
            ?? $resource['invoice_id']
            ?? $resource['purchase_units'][0]['custom_id']
            ?? $resource['purchase_units'][0]['invoice_id']
            ?? null;

        if (is_string($chargeId) && $chargeId !== '' && $found = $query()->where('charge_id', $chargeId)->first()) {
            return $found;
        }

        $orderId = str_starts_with($type, 'CHECKOUT.ORDER.')
            ? ($resource['id'] ?? null)
            : ($resource['supplementary_data']['related_ids']['order_id'] ?? null);

        if (is_string($orderId) && $orderId !== '' && $found = $query()->where('provider_order_id', $orderId)->first()) {
            return $found;
        }

        $captureId = match (true) {
            in_array($type, ['PAYMENT.CAPTURE.REFUNDED', 'PAYMENT.CAPTURE.REVERSED'], true) => $this->linkedCaptureId($resource),
            str_starts_with($type, 'PAYMENT.CAPTURE.') => $resource['id'] ?? null,
            default => null,
        };

        if (is_string($captureId) && $captureId !== '') {
            return $query()->where('provider_transaction_id', $captureId)->first();
        }

        return null;
    }

    /**
     * The capture a refund belongs to, from its `up` link.
     *
     * @param  array<string, mixed>  $refund
     */
    private function linkedCaptureId(array $refund): ?string
    {
        foreach ((array) ($refund['links'] ?? []) as $link) {
            if (($link['rel'] ?? null) === 'up' && preg_match('#/captures/([^/?]+)#', (string) ($link['href'] ?? ''), $match)) {
                return $match[1];
            }
        }

        return null;
    }

    /** MAD → `$currency`, or null when no rate is on file for it. */
    private function rateFor(string $currency): ?float
    {
        $rate = $this->rates->all()[$currency] ?? null;

        return is_numeric($rate) && (float) $rate > 0 ? (float) $rate : null;
    }
}
