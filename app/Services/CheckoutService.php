<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\DeliveryService;
use App\Models\Earning;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Notifications\NewOrderReceived;
use App\Notifications\NewPurchaseForAdmin;
use App\Notifications\OrderPlaced;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    const PLATFORM_FEE_PERCENT = 0.20; // 20%

    public function __construct(
        private DeliveryFeeService $deliveryFees,
        private AdminNotificationService $adminNotifications,
    ) {}

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
        $unitPrice = function (CartItem $item) use ($audience): float {
            $price = $item->unitPriceFor($audience);

            /*
             * Refuse rather than fall back to 0. An admin may publish a
             * product before agreeing its price (base_price NULL), and such a
             * product is kept off the storefront — but one already sitting in
             * a basket when its price was cleared would otherwise be billed as
             * free, and the seller's Earning written against that nothing.
             *
             * Belt and braces with the pre-flight check below: this closure is
             * the only path from a cart line to money, so nothing unpriced can
             * reach an order total or an order line by any route.
             */
            if ($price === null) {
                throw ValidationException::withMessages([
                    'cart' => "Le produit '".($item->product->name ?? 'Inconnu')
                        ."' n'a pas encore de prix et ne peut pas être commandé. Retirez-le du panier.",
                ]);
            }

            return $price;
        };

        DB::transaction(function () use ($cart, $checkoutData, $unitPrice, $audience, &$orders) {
            // 1. Validate stock for ALL items first with lock to avoid race conditions
            foreach ($cart->items as $item) {
                // Priced-ness is checked up front alongside stock, so an
                // unpriced basket fails before any row is locked or written.
                if ($item->unitPriceFor($audience) === null) {
                    throw ValidationException::withMessages([
                        'cart' => "Le produit '".($item->product->name ?? 'Inconnu')
                            ."' n'a pas encore de prix et ne peut pas être commandé. Retirez-le du panier.",
                    ]);
                }

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

            /*
             * One purchase, however many workshops it touches. Every order
             * below carries this, so the admin desk can put the basket back
             * together — see the orders migration.
             */
            $groupReference = 'HRFG-'.strtoupper(Str::random(10));

            /*
             * Which carrier bills this basket, resolved once for the whole
             * checkout: whatever the buyer picked on the page, or Amana when
             * the field was never sent (the historical carrier — see
             * DeliveryFeeService::defaultService).
             */
            $service = isset($checkoutData['delivery_service_id'])
                ? DeliveryService::find((int) $checkoutData['delivery_service_id'])
                : null;
            $service ??= $this->deliveryFees->defaultService();

            /*
             * What that service charges for *this* destination, resolved once
             * for the whole basket. Quoted to the buyer by the same service on
             * the checkout page, so the summary and the bill agree.
             */
            $deliveryFee = $this->deliveryFees->resolve(
                $checkoutData['country'] ?? null,
                $checkoutData['city_id'] ?? null,
                $service,
            );

            /*
             * The delivery fee belongs to the checkout as a whole, not to any
             * one store's order — but it still lives on the `orders` /
             * `earnings` rows, which are per store. Rather than add a new
             * basket-level place to hold it, it's attached to whichever order
             * happens to be created first and every other order in the same
             * checkout gets 0. Which order that is doesn't matter today: the
             * admin runs delivery as one service, not each seller arranging
             * their own shipment, so there is no per-seller reimbursement to
             * split fairly here yet.
             */
            $deliveryFeeRemaining = $deliveryFee;

            foreach ($grouped as $storeId => $items) {
                $store = $items->first()->product->store;

                // Calculate amounts
                $grossAmount = $items->sum(fn ($item) => $unitPrice($item) * $item->quantity);
                $platformFee = round($grossAmount * self::PLATFORM_FEE_PERCENT, 2);
                $orderDeliveryFee = $deliveryFeeRemaining;
                $deliveryFeeRemaining = 0.0;
                $sellerEarnings = $grossAmount - $platformFee + $orderDeliveryFee;

                // Create order
                $order = Order::create([
                    'buyer_id' => $cart->user_id,
                    'store_id' => $storeId,
                    'city_id' => $checkoutData['city_id'],
                    'group_reference' => $groupReference,
                    'status' => 'pending',
                    'total_amount' => $grossAmount,
                    'platform_fee' => $platformFee,
                    'delivery_fee' => $orderDeliveryFee,
                    // Which carrier shipped the basket and what it was called
                    // then — the id is a reference, the name a snapshot.
                    'delivery_service_id' => $service?->id,
                    'delivery_service_name' => $service?->name,
                    /*
                     * What the buyer was reading, and the rate that was live
                     * then. Presentation history only — every figure above is
                     * MAD and stays MAD. Null when the caller supplied none
                     * (seeders, tests, older callers), which reads honestly as
                     * "unknown" rather than claiming MAD.
                     */
                    'display_currency' => $checkoutData['display_currency'] ?? null,
                    'display_rate' => $checkoutData['display_rate'] ?? null,
                    'seller_earnings' => $sellerEarnings,
                    'shipping_address' => $checkoutData['shipping_address'],
                    // Every order a split basket produces ships to the same
                    // buyer, so each carries the number its courier should call.
                    'shipping_phone' => $checkoutData['shipping_phone'] ?? null,
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
                    'delivery_fee' => $orderDeliveryFee,
                    'net_amount' => $sellerEarnings,
                    'status' => 'pending',
                ]);

                // Notify seller
                // try {
                //     $order->load(['items.product', 'buyer', 'store']);
                //     $store->user->notify(new NewOrderReceived($order));
                // } catch (\Exception $e) {
                //     \Log::warning("Failed to notify seller {$store->user->id} for order {$order->id}: ".$e->getMessage());
                // }

                $orders[] = $order;
            }

            // Clear cart
            $cart->items()->delete();
        });

        /*
         * Outside the transaction, deliberately. Hirfaty's desk fulfils every
         * purchase, so this is the email that actually starts the work — but a
         * mail failure must not roll back an order that is already paid for
         * and already has stock decremented against it.
         */
        $this->notifyAdmin($orders);
        // The admin panel's bell, alongside the desk email above.
        $this->adminNotifications->notifyNewPurchase($orders);
        $this->notifyBuyer($orders, $cart->user);

        return $orders;
    }

    /**
     * Tell the Hirfaty desk a basket has been bought.
     *
     * One email for the whole purchase, not one per workshop: the desk picks
     * the parcel up as a single job, and three separate emails for one buyer's
     * order would read as three things to do.
     *
     * @param  array<int, Order>  $orders
     */
    private function notifyAdmin(array $orders): void
    {
        if ($orders === []) {
            return;
        }

        $address = config('hirfaty.admin_email');

        if (! $address) {
            return;
        }

        try {
            $loaded = Order::whereIn('id', collect($orders)->pluck('id'))
                ->with(['store.city', 'buyer', 'items.product.images'])
                ->get();

            Notification::route('mail', $address)
                ->notify(new NewPurchaseForAdmin($loaded));
        } catch (\Exception $e) {
            Log::error('Admin purchase notification failed: '.$e->getMessage());
        }
    }

    /**
     * Tell the buyer their checkout went through.
     *
     * One email for the whole checkout, not one per workshop — see
     * OrderPlaced's docblock for why sending one per store used to make a
     * multi-seller purchase read as incomplete.
     *
     * @param  array<int, Order>  $orders
     */
    private function notifyBuyer(array $orders, ?User $buyer): void
    {
        if ($orders === [] || ! $buyer) {
            return;
        }

        try {
            $loaded = Order::whereIn('id', collect($orders)->pluck('id'))
                ->with(['store', 'items.product'])
                ->get();

            $buyer->notify(new OrderPlaced($loaded));
        } catch (\Exception $e) {
            Log::warning('Buyer purchase notification failed: '.$e->getMessage());
        }
    }
}
