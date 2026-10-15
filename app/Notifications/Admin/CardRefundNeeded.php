<?php

namespace App\Notifications\Admin;

use Illuminate\Notifications\Notification;

/**
 * The admin bell entry for money paid online that no live order stands
 * behind — a paid order that was then cancelled, a charge that landed after
 * the purchase expired, or a purchase paid twice. Refunds are made from
 * Payzone's back-office or PayPal's dashboard (`provider`); this is the
 * reminder that one is owed, in the currency that was taken.
 *
 * Facts only, like NewPurchaseAlert: AdminNotificationService::present()
 * writes the sentence.
 */
class CardRefundNeeded extends Notification
{
    public const KIND = 'payment.refund_needed';

    public function __construct(
        public string $purchase,
        public string $chargeId,
        public float $amount,
        public string $reason,
        public ?string $orderReference = null,
        public string $provider = 'payzone',
        public string $currency = 'MAD',
        public ?string $transactionId = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return self::KIND;
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'reference' => $this->purchase,
            'charge_id' => $this->chargeId,
            'amount' => round($this->amount, 2),
            'reason' => $this->reason,
            // Null when the orders are not visible to the desk (released
            // before the money landed) — the bell then opens the order list.
            'order_reference' => $this->orderReference,
            'provider' => $this->provider,
            'currency' => $this->currency,
            // What to search PayPal's activity for — its capture id.
            'transaction_id' => $this->transactionId,
        ];
    }
}
