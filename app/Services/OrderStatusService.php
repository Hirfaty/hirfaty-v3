<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Notifications\OrderCancelled;
use App\Notifications\OrderConfirmed;
use App\Notifications\OrderDelivered;
use App\Notifications\OrderShipped;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
     * Move one order to a new status and tell the buyer, alone.
     *
     * Used by Seller\OrderController, where a seller only ever holds their
     * own store's slice of a purchase — there is no group to fold an email
     * into here, so this is `transitionMany()` with a group of one.
     *
     * @param  array<string, mixed>  $attributes  Extra columns to write alongside the
     *                                            status change (tracking_number, cancellation_reason, ...).
     */
    public function transition(Order $order, string $status, array $attributes = [], ?string $note = null): Order
    {
        return $this->transitionMany(collect([$order]), $status, $attributes, $note)->first();
    }

    /**
     * Move every order in a purchase — or the one workshop an admin peeled
     * off with "handle separately" — to a new status, and tell the buyer
     * about it once.
     *
     * Hirfaty's desk collects a purchase from every workshop it touched and
     * ships it as one job (see Admin\OrderController's docblock), so moving
     * a whole group to "confirmed" is one thing happening, not N things. It
     * used to be N: Admin\OrderController looped its group through
     * `transition()` one order at a time, and each call emailed the buyer on
     * its own — a basket split across three workshops meant three "your
     * order is confirmed" emails landing together for what the buyer
     * experienced as a single purchase.
     *
     * Every order in the group still gets its own DB write and its own
     * earning flip — payouts and stock are genuinely per store — but the
     * buyer hears about it once, carrying every order that actually changed
     * in this pass (re-submitting a status an order is already at writes
     * nothing new to say, so it's left out; see OrderPlaced's docblock for
     * the same "one checkout, one email" shape at checkout time).
     *
     * Each order that moves gets a line in its history (OrderStatusEvent),
     * carrying `$note` when the desk wrote one — or, for a cancellation, the
     * reason. Re-submitting the status an order is already at records nothing
     * unless there is a note, in which case it is kept as a tracking update.
     *
     * @param  Collection<int, Order>  $orders
     * @param  array<string, mixed>  $attributes
     * @return Collection<int, Order>
     */
    public function transitionMany(Collection $orders, string $status, array $attributes = [], ?string $note = null): Collection
    {
        $changedIds = [];
        $note = $this->cleanNote($note);

        DB::transaction(function () use ($orders, $status, $attributes, $note, &$changedIds) {
            foreach ($orders as $order) {
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

                /*
                 * Stock is committed once, at checkout (CheckoutService), against
                 * every line the order held — not touched again by confirming,
                 * shipping or delivering. Stepping out of the fulfilment flow
                 * into 'cancelled' is the only point that gives it back, whether
                 * that happens from 'pending', 'confirmed' or 'shipped'. Guarded
                 * on the previous status so re-submitting 'cancelled' on an
                 * already-cancelled order (a stale tab, a double click) can't
                 * restore stock twice.
                 */
                if ($status === 'cancelled' && $previousStatus !== 'cancelled') {
                    $this->restoreStock($order);
                }

                if ($previousStatus !== $status) {
                    $changedIds[] = $order->id;

                    $order->statusEvents()->create([
                        'status' => $status,
                        'note' => $note ?? ($status === 'cancelled'
                            ? $this->cleanNote($attributes['cancellation_reason'] ?? null)
                            : null),
                    ]);
                } elseif ($note !== null) {
                    $order->statusEvents()->create(['status' => null, 'note' => $note]);
                }
            }
        });

        if ($changedIds !== []) {
            $this->notifyBuyer($changedIds, $status);
        }

        return $orders;
    }

    /**
     * Tell the buyer where their parcel is without moving it to a new status —
     * "arrivé au centre de tri de Casablanca" between shipped and delivered.
     *
     * Shown on the buyer's tracking page only; no email, since nothing about
     * the order changed that they need to act on.
     *
     * @param  Collection<int, Order>  $orders
     */
    public function addNote(Collection $orders, string $note): void
    {
        $note = $this->cleanNote($note);

        if ($note === null) {
            return;
        }

        foreach ($orders as $order) {
            $order->statusEvents()->create(['status' => null, 'note' => $note]);
        }
    }

    private function cleanNote(?string $note): ?string
    {
        $note = trim((string) $note);

        return $note === '' ? null : mb_substr($note, 0, 500);
    }

    /**
     * Give back every unit a cancelled order held, variant-aware — a variant
     * line's stock lives on the variant row, not the product (see
     * CheckoutService, which decremented it from the same place).
     */
    private function restoreStock(Order $order): void
    {
        foreach ($order->items as $item) {
            if ($item->product_variant_id !== null) {
                ProductVariant::where('id', $item->product_variant_id)->increment('stock', $item->quantity);
            } else {
                Product::where('id', $item->product_id)->increment('stock', $item->quantity);
            }
        }
    }

    /**
     * @param  array<int, int>  $orderIds
     */
    private function notifyBuyer(array $orderIds, string $status): void
    {
        // Reloaded rather than reused: the orders passed in may not carry
        // `store`/`items.product` yet, and a fresh, freshly-scoped Eloquent
        // collection is what the notification classes expect (the same
        // shape CheckoutService reloads into before OrderPlaced).
        $orders = Order::whereIn('id', $orderIds)
            ->with(['store', 'buyer', 'items.product'])
            ->get();

        $buyer = $orders->first()?->buyer;

        if (! $buyer) {
            return;
        }

        try {
            match ($status) {
                'confirmed' => $buyer->notify(new OrderConfirmed($orders)),
                'shipped' => $buyer->notify(new OrderShipped($orders)),
                'delivered' => $buyer->notify(new OrderDelivered($orders)),
                'cancelled' => $buyer->notify(new OrderCancelled($orders)),
                default => null,
            };
        } catch (\Exception $e) {
            Log::error('Failed to send order status notification for orders ['.implode(',', $orderIds).']: '.$e->getMessage());
        }
    }
}
