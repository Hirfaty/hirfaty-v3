<?php

namespace App\Services;

use App\Models\Order;
use App\Notifications\OrderCancelled;
use App\Notifications\OrderConfirmed;
use App\Notifications\OrderDelivered;
use App\Notifications\OrderShipped;
use Illuminate\Support\Facades\Log;

/**
 * The one place that moves an order to a new status and tells the buyer
 * about it — used by both Seller\OrderController and Admin\OrderController,
 * which otherwise each had their own copy of this (the admin one didn't
 * notify at all). Kept here rather than in either controller per CLAUDE.md's
 * "business logic that spans models goes in app/Services/".
 */
class OrderStatusService
{
    /**
     * @param  array<string, mixed>  $attributes  Extra columns to write alongside the
     *                                            status change (tracking_number, cancellation_reason, ...).
     */
    public function transition(Order $order, string $status, array $attributes = []): Order
    {
        $previousStatus = $order->status;

        $order->update(array_merge($attributes, [
            'status' => $status,
            'delivered_at' => $status === 'delivered' ? now() : $order->delivered_at,
        ]));

        // A store's payout only becomes collectible once the parcel has
        // actually reached the buyer.
        if ($status === 'delivered') {
            $order->earning?->update(['status' => 'available']);
        }

        // Re-submitting the status the order is already at (an admin saving
        // the same value, a double click) is not a transition — nothing
        // changed, so the buyer doesn't need a second email saying so.
        if ($previousStatus === $status) {
            return $order;
        }

        $this->notifyBuyer($order, $status);

        return $order;
    }

    private function notifyBuyer(Order $order, string $status): void
    {
        $order->loadMissing(['store', 'buyer', 'items.product']);

        if (! $order->buyer) {
            return;
        }

        try {
            match ($status) {
                'confirmed' => $order->buyer->notify(new OrderConfirmed($order)),
                'shipped' => $order->buyer->notify(new OrderShipped($order)),
                'delivered' => $order->buyer->notify(new OrderDelivered($order)),
                'cancelled' => $order->buyer->notify(new OrderCancelled($order)),
                default => null,
            };
        } catch (\Exception $e) {
            Log::error("Failed to send order status notification for order {$order->id}: ".$e->getMessage());
        }
    }
}
