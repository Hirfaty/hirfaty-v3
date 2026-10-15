<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Collection;

/**
 * The desk cancelling a purchase, or one workshop's part of it, from the
 * admin order page: why, and what happens to the money.
 *
 * Reasons are picked from a fixed list so they can be written in the buyer's
 * own language wherever they are shown — the cancellation email, the
 * tracking page. Codes are stored, never sentences; the labels live in
 * lang/<locale>/buyer.php as `cancel_reason.<code>`. Anything the desk adds
 * in its own words goes in `cancellation_reason` and is shown as written.
 *
 * For an order paid online the desk also decides whether the buyer is
 * refunded; RefundService does it (PayPal) or raises the alert to do it
 * (Payzone).
 */
class OrderCancellationService
{
    /** The reasons the desk picks from, in the order the dialog shows them. */
    public const REASONS = [
        'out_of_stock',
        'quality_issue',
        'damaged',
        'buyer_request',
        'address_issue',
        'unreachable',
        'delay',
        'duplicate',
        'pricing_error',
    ];

    public function __construct(
        private OrderStatusService $orderStatus,
        private RefundService $refunds,
    ) {}

    /**
     * The reasons, labelled in French for the admin dialog.
     *
     * @return list<array{code: string, label: string}>
     */
    public function options(): array
    {
        return array_map(fn (string $code) => [
            'code' => $code,
            'label' => self::label($code, 'fr'),
        ], self::REASONS);
    }

    /**
     * Why an order was cancelled, in `$locale`: each picked reason, then the
     * desk's (or buyer's, or seller's) own words. Empty when nobody said.
     *
     * @return list<string>
     */
    public static function describe(Order $order, ?string $locale = null): array
    {
        $locale ??= app()->getLocale();

        $reasons = collect((array) ($order->cancellation_tags ?? []))
            ->filter(fn ($code) => in_array($code, self::REASONS, true))
            ->map(fn (string $code) => self::label($code, $locale));

        if (filled($order->cancellation_reason)) {
            $reasons->push((string) $order->cancellation_reason);
        }

        return $reasons->values()->all();
    }

    public static function label(string $code, string $locale): string
    {
        return __("buyer.cancel_reason.{$code}", [], $locale);
    }

    /**
     * Cancel these orders, tell the buyer why, and — when `$refund` is set
     * and they were paid online — give the money back.
     *
     * The cancellation goes first, so the buyer's email (which says whether a
     * refund is coming) goes out whatever PayPal answers; a refund PayPal
     * refuses becomes an alert for the desk instead.
     *
     * @param  Collection<int, Order>  $orders
     * @param  list<string>  $reasons  codes from REASONS
     * @return list<array{purchase: string, provider: string, outcome: string, amount: float, currency: string}>
     *                                                                                                           what RefundService did, one entry per purchase refunded
     */
    public function cancel(Collection $orders, array $reasons, ?string $details, bool $refund): array
    {
        $reasons = array_values(array_intersect(self::REASONS, $reasons));
        $details = trim((string) $details);

        // Read before the status changes: these are the orders with money to give back.
        $paidOnline = $orders->filter(fn (Order $order) => $order->isPaidOnline())->values();

        $this->orderStatus->transitionMany($orders, 'cancelled', [
            'cancellation_reason' => $details === '' ? null : mb_substr($details, 0, 500),
            'cancellation_tags' => $reasons === [] ? null : $reasons,
            // Only a decision where money was taken online.
            'cancellation_refund' => $paidOnline->isNotEmpty() ? $refund : null,
        ], flagRefunds: false);

        if (! $refund || $paidOnline->isEmpty()) {
            return [];
        }

        $why = collect($reasons)->map(fn (string $code) => self::label($code, 'fr'))
            ->push($details)
            ->filter()
            ->implode(' · ');

        return $this->refunds->refund($paidOnline, $why === '' ? '' : "Motif : {$why}");
    }

    /**
     * The desk's confirmation, in French, of what happened to the money.
     *
     * @param  list<array{purchase: string, provider: string, outcome: string, amount: float, currency: string}>  $results
     */
    public function summary(array $results): string
    {
        $lines = ['Annulation enregistrée.'];

        foreach ($results as $result) {
            $amount = number_format($result['amount'], 2, ',', ' ').' '.$result['currency'];

            $lines[] = match ($result['outcome']) {
                'refunded' => "Remboursement PayPal de {$amount} effectué.",
                'pending' => "Remboursement PayPal de {$amount} envoyé — PayPal le traite encore.",
                'manual' => "Remboursement carte de {$amount} à faire depuis le back-office Payzone (alerte créée).",
                default => "Le remboursement PayPal de {$amount} a échoué — alerte créée pour le faire à la main.",
            };
        }

        return implode(' ', $lines);
    }

    /** @param  list<array{outcome: string}>  $results */
    public function anyFailed(array $results): bool
    {
        return collect($results)->contains(fn (array $result) => $result['outcome'] === 'failed');
    }
}
