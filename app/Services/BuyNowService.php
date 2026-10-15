<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * "Buy now" on a product page: straight to checkout with that one piece,
 * leaving the buyer's cart exactly as it was.
 *
 * The piece waits in the session, not in the cart. What checkout bills is a
 * one-line Cart built from it in memory and never saved, so CheckoutService
 * prices, splits and reserves it exactly as it would a basket — same stock
 * locks, same fees — without anything being written to `carts` or
 * `cart_items`.
 *
 * Once paid, PaymentService takes the purchased lines out of the real cart as
 * usual, so a buyer who also had the same piece sitting in their basket is
 * not left holding it there after buying it.
 */
class BuyNowService
{
    /** The piece on its way to checkout: product, variant, quantity. */
    public const SESSION_KEY = 'checkout.buy_now';

    /** Purchase reference → product slug, for sending a cancelled buy-now back to its page. */
    private const PURCHASES_KEY = 'checkout.buy_now_purchases';

    public function __construct(private CartService $carts) {}

    /**
     * Hold one piece for checkout, after the same checks adding it to the
     * cart would run — plus a price, since this goes straight to the till.
     *
     * @throws ValidationException
     */
    public function start(Session $session, User $buyer, int $productId, ?int $variantId, int $quantity): void
    {
        [$product, $variant] = $this->carts->assertBuyable($buyer, $productId, $variantId, $quantity);

        if ($this->line($product, $variant, $quantity)->unitPriceFor(Product::audienceFor($buyer)) === null) {
            throw ValidationException::withMessages([
                'product_id' => "Le produit '{$product->name}' n'a pas encore de prix et ne peut pas être commandé.",
            ]);
        }

        $session->put(self::SESSION_KEY, [
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'quantity' => $quantity,
        ]);
    }

    /**
     * The one-line cart checkout works from, never saved. Null when nothing
     * is waiting, or the piece has gone off sale since.
     */
    public function cart(Session $session, User $buyer): ?Cart
    {
        $held = $session->get(self::SESSION_KEY);

        if (! is_array($held) || empty($held['product_id'])) {
            return null;
        }

        $product = Product::storefront()->with(['images', 'store'])->find((int) $held['product_id']);

        if (! $product) {
            return null;
        }

        $variant = null;

        if (! empty($held['variant_id'])) {
            $variant = ProductVariant::with(['options', 'media'])
                ->where('product_id', $product->id)
                ->where('is_active', true)
                ->find((int) $held['variant_id']);

            if (! $variant) {
                return null;
            }
        }

        $cart = new Cart(['user_id' => $buyer->id]);
        $cart->setRelation('user', $buyer);
        $cart->setRelation('items', new Collection([
            $this->line($product, $variant, max(1, (int) ($held['quantity'] ?? 1))),
        ]));

        return $cart;
    }

    /**
     * The checkout went through: the piece is no longer waiting, and the
     * purchase is remembered as a buy-now one for the rest of the visit.
     */
    public function placed(Session $session, Cart $cart, string $reference): void
    {
        $session->forget(self::SESSION_KEY);

        $slug = $cart->items->first()?->product?->slug;

        if ($slug !== null) {
            $session->put(self::PURCHASES_KEY.'.'.$reference, $slug);
        }
    }

    /**
     * The product a buy-now purchase was for — where a buyer who gives up on
     * paying goes back to, since the piece was never in their cart. Null for
     * a purchase checked out from the cart.
     */
    public function productSlugFor(Session $session, string $reference): ?string
    {
        $slug = $session->get(self::PURCHASES_KEY.'.'.$reference);

        return is_string($slug) ? $slug : null;
    }

    private function line(Product $product, ?ProductVariant $variant, int $quantity): CartItem
    {
        $item = new CartItem([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'quantity' => $quantity,
        ]);

        $item->setRelation('product', $product);
        $item->setRelation('variant', $variant);

        return $item;
    }
}
