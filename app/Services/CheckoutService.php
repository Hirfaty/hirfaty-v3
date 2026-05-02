<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Earning;
use App\Models\Order;
use App\Models\OrderItem;
use App\Notifications\NewOrderReceived;
use Illuminate\Support\Facades\DB;


class CheckoutService
{
    const PLATFORM_FEE_PERCENT = 0.05; // 5%
    const AMANA_FEE            = 15.00; // flat per order

    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function process(Cart $cart, array $checkoutData) {
        $orders = [];

        DB::transaction(function () use ($cart, $checkoutData, &$orders) {
            // Group cart items by store
            $grouped = $cart->items->groupBy(fn($item) => $item->product->store_id);

            foreach($grouped as $storeId => $items) {
                $store = $items->first()->product->store;

                // Calculate amounts
                $grossAmount  = $items->sum(fn($item) => $item->product->sale_price ?? $item->product->base_price) * 1;
                $grossAmount  = $items->sum(fn($item) => (float)($item->product->sale_price ?? $item->product->base_price) * $item->quantity);
                $platformFee  = round($grossAmount * self::PLATFORM_FEE_PERCENT, 2);
                $amanaFee     = self::AMANA_FEE;
                $sellerEarnings = $grossAmount - $platformFee - $amanaFee;

                // Create order
                $order = Order::create([
                    'buyer_id'         => $cart->user_id,
                    'store_id'         => $storeId,
                    'city_id'          => $checkoutData['city_id'],
                    'status'           => 'pending',
                    'total_amount'     => $grossAmount,
                    'platform_fee'     => $platformFee,
                    'amana_fee'        => $amanaFee,
                    'seller_earnings'  => $sellerEarnings,
                    'shipping_address' => $checkoutData['shipping_address'],
                    'notes'            => $checkoutData['notes'] ?? null,
                ]);

                // Create order items
                foreach ($items as $item) {
                    $price = (float)($item->product->sale_price ?? $item->product->base_price);
                    OrderItem::create([
                        'order_id'   => $order->id,
                        'product_id' => $item->product_id,
                        'quantity'   => $item->quantity,
                        'unit_price' => $price,
                        'subtotal'   => $price * $item->quantity,
                    ]);

                    // Decrease stock
                    $item->product->decrement('stock', $item->quantity);
                }

                
                // Create earning record
                Earning::create([
                    'store_id'     => $storeId,
                    'order_id'     => $order->id,
                    'gross_amount' => $grossAmount,
                    'platform_fee' => $platformFee,
                    'amana_fee'    => $amanaFee,
                    'net_amount'   => $sellerEarnings,
                    'status'       => 'pending',
                ]);

                
                // // Notify seller
               
                try {
                    $order->load(['items.product', 'buyer', 'store']);
                    $store->user->notify(new NewOrderReceived($order));
                } catch(\Exception $e) {
                    \Log::warning("Failed to notify seller {$store->user->id} for order {$order->id}: " . $e->getMessage());
                }

                $orders[] = $order;
            }

            // Clear cart
            $cart->items()->delete();
        });

        return $orders;
    }
}