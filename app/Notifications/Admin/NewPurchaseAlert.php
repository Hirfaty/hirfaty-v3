<?php

namespace App\Notifications\Admin;

use App\Models\Order;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * The admin panel's bell entry for a new purchase — the in-app twin of
 * NewPurchaseForAdmin, which emails the desk inbox.
 *
 * Sent to every super_admin account rather than to the role inbox, because
 * the bell belongs to a person: each admin reads and clears their own copy.
 *
 * Not queued, unlike the mail. Writing one row is cheap, and an order that
 * only reaches the bell once a worker happens to be running is exactly the
 * delay this exists to remove.
 *
 * The payload is facts, not sentences: AdminNotificationService::present()
 * turns it into the title and body the panel shows, so the wording can change
 * without rewriting rows already stored.
 */
class NewPurchaseAlert extends Notification
{
    public const KIND = 'purchase.placed';

    /**
     * @param  Collection<int, Order>  $orders  Every order one checkout produced,
     *                                          with `buyer` and `items` loaded.
     */
    public function __construct(public Collection $orders) {}

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
        $first = $this->orders->first();
        $buyer = $first?->buyer;

        $buyerName = trim(($buyer?->first_name ?? '').' '.($buyer?->last_name ?? ''));

        return [
            // The first order of the group: Admin\OrderController::show opens
            // the whole purchase from any one of its orders. Its own
            // reference, not its id — orders are routed by reference
            // (Order::getRouteKeyName).
            'order_id' => $first?->id,
            'order_reference' => $first?->reference,
            'reference' => $first?->group_reference ?? $first?->reference,
            'buyer_name' => $buyerName !== '' ? $buyerName : ($buyer?->email ?? $buyer?->phone_number),
            // MAD, delivery included — what the courier collects.
            'total' => round(
                (float) $this->orders->sum('total_amount') + (float) $this->orders->sum('delivery_fee'),
                2,
            ),
            'store_count' => $this->orders->pluck('store_id')->unique()->count(),
            'item_count' => (int) $this->orders->sum(fn (Order $order) => $order->items->sum('quantity')),
        ];
    }
}
