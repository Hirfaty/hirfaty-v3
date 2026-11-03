<?php

namespace App\Notifications\Admin;

use Illuminate\Notifications\Notification;

/**
 * The admin bell entry for a Cathedis parcel that will not reach the buyer as
 * it stands: refused or cancelled at the door, a wrong number, an invalid
 * destination, a parcel on its way back (a REJECTED status). Nothing is
 * cancelled automatically — whether to call the buyer, reship or cancel (and
 * refund) is the desk's call.
 *
 * Facts only, like NewPurchaseAlert: AdminNotificationService::present()
 * writes the sentence.
 */
class CathedisDeliveryIssue extends Notification
{
    public const KIND = 'cathedis.delivery_issue';

    public function __construct(
        public string $purchase,
        public string $orderReference,
        public string $code,
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
            'code' => $this->code,
            'status' => $this->status,
        ];
    }
}
