<?php

namespace App\Notifications\Admin;

use Illuminate\Notifications\Notification;

/**
 * The admin bell entry for a DHL Express shipment that will not reach the
 * buyer as it stands: a bad address, the buyer moved or refused it, duties
 * left unpaid, the shipment stopped or on its way back. Nothing is cancelled
 * automatically — whether to call the buyer, reship or cancel (and refund) is
 * the desk's call.
 *
 * Facts only, like CathedisDeliveryIssue: AdminNotificationService::present()
 * writes the sentence.
 */
class DhlDeliveryIssue extends Notification
{
    public const KIND = 'dhl.delivery_issue';

    public function __construct(
        public string $purchase,
        public string $orderReference,
        public string $trackingNumber,
        public string $status,
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
            'order_reference' => $this->orderReference,
            'code' => $this->trackingNumber,
            'status' => $this->status,
        ];
    }
}
