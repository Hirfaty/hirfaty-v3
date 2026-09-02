<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Earning;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Notifications\NewOrderReceived;
use App\Notifications\OrderPlaced;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    const PLATFORM_FEE_PERCENT = 0.20; // 20%

    const AMANA_FEE = 20.00; // flat per order

    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function process(Cart $cart, array $checkoutData)
    {
        $orders = [];

        /*
         * Which price list this basket is billed on, resolved once from the
         * cart's owner. Verified trade accounts are charged their wholesale
         * rate; everyone else — including an unverified trade account — pays
         * retail. Product::audienceFor() is the same call the storefront uses
         * to decide what to *show*, so a buyer cannot be quoted one figure
         * and charged another.
         */
        $audience = Product::audienceFor($cart->user);

        /*
         * One place that turns a cart line into a unit price. The order total
         * and the individual order lines are computed separately below, and
         * pricing them through anything but a shared helper is how a total
         * ends up disagreeing with the sum of its own lines.
         *
         * Delegated to the line so a chosen variant's price override wins over
         * the product's list price; CartItem::unitPriceFor() falls back to the
         * product for a line with no variant.
         */
        $unitPrice = fn (CartItem $item): float => $item->unitPriceFor($audience);

        DB::transaction(function () use ($cart, $checkoutData, $unitPrice, &$orders) {
            // 1. Validate stock for ALL items first with lock to avoid race conditions
            foreach ($cart->items as $item) {
                /*
                 * Lock the row that actually holds the stock. For a variant
                 * line that is the variant, not the product: a variant
                 * product keeps products.stock at 0 by design, so checking
                 * the product here would reject every such order as
                 * out-of-stock — which is exactly what used to happen.
                 */
                if ($item->product_variant_id !== null) {
                    $variant = ProductVariant::where('id', $item->product_variant_id)
                        ->lockForUpdate()
                        ->first();

                    if (! $variant || $variant->stock < $item->quantity) {
                        $label = $item->product->name ?? 'Inconnu';

                        if ($item->variant) {
                            $label .= ' ('.$item->variant->label().')';
                        }

                        throw ValidationException::withMessages([
                            'cart' => "Le produit '{$label}' n'a plus assez de stock (disponible : ".(int) ($variant->stock ?? 0).').',
                        ]);
                    }

                    continue;
                }

                // We re-fetch each product with a lock to ensure the stock count is accurate
                $product = Product::where('id', $item->product_id)->lockForUpdate()->first();

                if (! $product || $product->stock < $item->quantity) {
                    $productName = $product ? $product->name : ($item->product->name ?? 'Inconnu');
                    $availableStock = $product ? $product->stock : 0;

                    throw ValidationException::withMessages([
                        'cart' => "Le produit '{$productName}' n'a plus assez de stock (disponible : {$availableStock}).",
                    ]);
                }
            }

            // 2. Group cart items by store
            $grouped = $cart->items->groupBy(fn ($item) => $item->product->store_id);

            foreach ($grouped as $storeId => $items) {
                $store = $items->first()->product->store;

                // Calculate amounts
                $grossAmount = $items->sum(fn ($item) => $unitPrice($item) * $item->quantity);
                $platformFee = round($grossAmount * self::PLATFORM_FEE_PERCENT, 2);
                $amanaFee = self::AMANA_FEE;
                $sellerEarnings = $grossAmount - $platformFee + $amanaFee;

                // Create order
                $order = Order::create([
                    'buyer_id' => $cart->user_id,
                    'store_id' => $storeId,
                    'city_id' => $checkoutData['city_id'],
                    'status' => 'pending',
                    'total_amount' => $grossAmount,
                    'platform_fee' => $platformFee,
                    'amana_fee' => $amanaFee,
                    'seller_earnings' => $sellerEarnings,
                    'shipping_address' => $checkoutData['shipping_address'],
                    'notes' => $checkoutData['notes'] ?? null,
                ]);

                // Create order items
                foreach ($items as $item) {
                    $price = $unitPrice($item);
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $item->product_id,
                        'product_variant_id' => $item->product_variant_id,
                        // Snapshot, so the line still reads "Rouge / M" after
                        // the variant is renamed or deleted.
                        'variant_label' => $item->variantLabel(),
                        'quantity' => $item->quantity,
                        'unit_price' => $price,
                        'subtotal' => $price * $item->quantity,
                    ]);

                    // Decrease stock on whichever row was locked above.
                    if ($item->product_variant_id !== null) {
                        ProductVariant::where('id', $item->product_variant_id)
                            ->decrement('stock', $item->quantity);
                    } else {
                        Product::where('id', $item->product_id)->decrement('stock', $item->quantity);
                    }
                }

                // Create earning record
                Earning::create([
                    'store_id' => $storeId,
                    'order_id' => $order->id,
                    'gross_amount' => $grossAmount,
                    'platform_fee' => $platformFee,
                    'amana_fee' => $amanaFee,
                    'net_amount' => $sellerEarnings,
                    'status' => 'pending',
                ]);

                // Notify seller
                try {
                    $order->load(['items.product', 'buyer', 'store']);
                    $store->user->notify(new NewOrderReceived($order));
                } catch (\Exception $e) {
                    \Log::warning("Failed to notify seller {$store->user->id} for order {$order->id}: ".$e->getMessage());
                }

                // Notify buyer
                try {
                    $cart->user->notify(new OrderPlaced($order));
                } catch (\Exception $e) {
                    \Log::warning("Buyer notification failed for order {$order->id}: ".$e->getMessage());
                }

                $orders[] = $order;
            }

            // Clear cart
            $cart->items()->delete();
        });

        return $orders;
    }
}
