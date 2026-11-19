<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Adding to a cart, with the variant rules applied in one place.
 *
 * The "add to cart" button, the guest-cart merge and "Buy now" (which skips
 * the cart — see BuyNowService) all run through here, so every line is held
 * to exactly the same checks.
 *
 * `$audience` is the price list the buyer shops on (PriceModeService): on the
 * pro list a piece is sold from its per-product minimum, and a trade-only
 * piece is for sale on the pro list alone.
 */
class CartService
{
    /**
     * The cookie holding a visitor's basket token. Only its SHA-256 is stored
     * (`carts.guest_token_hash`), so a leaked table cannot be replayed into
     * someone's basket. Encrypted like every app cookie.
     */
    public const GUEST_COOKIE = 'hirfaty_cart';

    /** A visitor's basket waits a month for them to come back. */
    private const GUEST_COOKIE_MINUTES = 60 * 24 * 30;

    /**
     * The basket this request shops from: the account's once signed in, else
     * the one the visitor's cookie names. Null when there is none yet —
     * reading never creates one, so a bot browsing the catalogue leaves no
     * empty rows behind.
     */
    public function current(Request $request): ?Cart
    {
        if ($user = $request->user()) {
            return Cart::firstWhere('user_id', $user->id);
        }

        $token = $request->cookie(self::GUEST_COOKIE);

        if (! is_string($token) || $token === '') {
            return null;
        }

        return Cart::whereNull('user_id')
            ->firstWhere('guest_token_hash', hash('sha256', $token));
    }

    /**
     * The basket to write to, made (with its cookie) on a visitor's first
     * "add to cart".
     */
    public function currentOrCreate(Request $request): Cart
    {
        if ($user = $request->user()) {
            return Cart::firstOrCreate(['user_id' => $user->id]);
        }

        if ($cart = $this->current($request)) {
            return $cart;
        }

        $token = Str::random(48);

        $cart = Cart::create(['guest_token_hash' => hash('sha256', $token)]);

        Cookie::queue(self::GUEST_COOKIE, $token, self::GUEST_COOKIE_MINUTES);

        return $cart;
    }

    /**
     * Whether this line is in the basket this request shops from — checked
     * before a quantity change or a removal, so nobody edits another
     * basket's line by guessing its id.
     */
    public function ownsItem(Request $request, CartItem $item): bool
    {
        $cart = $this->current($request);

        return $cart !== null && $item->cart_id === $cart->id;
    }

    /**
     * Fold the visitor's basket into the account they just signed in to.
     *
     * An account with no basket simply takes the visitor's over. One that
     * already has lines gets the visitor's added to them through addLine(),
     * so every line is held to the same stock and minimum checks; one that
     * can no longer be bought (sold out, withdrawn) is dropped rather than
     * failing the sign-in. Runs on every sign-in (AppServiceProvider), so
     * whichever door they came through — a code, a password, Google — the
     * basket follows.
     */
    public function adoptGuestCart(User $user, Request $request): void
    {
        $token = $request->cookie(self::GUEST_COOKIE);

        if (! is_string($token) || $token === '') {
            return;
        }

        Cookie::queue(Cookie::forget(self::GUEST_COOKIE));

        $guest = Cart::whereNull('user_id')
            ->firstWhere('guest_token_hash', hash('sha256', $token));

        if (! $guest) {
            return;
        }

        // An artisan or an admin does not shop: nothing to fold it into.
        if (! $user->isBuyer()) {
            $guest->delete();

            return;
        }

        $own = Cart::firstWhere('user_id', $user->id);

        if (! $own) {
            $guest->update(['user_id' => $user->id, 'guest_token_hash' => null]);

            return;
        }

        $audience = app(PriceModeService::class)->audience($request);

        foreach ($guest->items as $item) {
            try {
                $this->addLine($own, $item->product_id, $item->product_variant_id, (int) $item->quantity, $audience);
            } catch (ValidationException) {
                continue;
            }
        }

        $guest->delete();
    }

    /**
     * Add (or top up) a line, after checking the variant belongs to the
     * product and there is stock to cover the new total quantity.
     *
     * @throws ValidationException
     */
    public function addLine(
        Cart $cart,
        int $productId,
        ?int $variantId,
        int $quantity = 1,
        string $audience = Product::AUDIENCE_INDIVIDUAL,
    ): CartItem {
        $product = $this->forSale($productId, $audience);
        $variant = $this->resolveVariant($product, $variantId);

        /*
         * Matched on the (product, variant) pair, not the product alone:
         * "Rouge / M" and "Bleu / L" are two separate lines of the same
         * product. whereNull is required for the no-variant case — SQL
         * `= NULL` never matches.
         */
        $existing = $cart->items()
            ->where('product_id', $productId)
            ->when($variant === null,
                fn ($q) => $q->whereNull('product_variant_id'),
                fn ($q) => $q->where('product_variant_id', $variant->id),
            )
            ->first();

        $newQuantity = ($existing?->quantity ?? 0) + $quantity;

        $this->assertStockFor($product, $variant, $newQuantity);
        $this->assertMinimumFor($product, $audience, $newQuantity);

        if ($existing) {
            $existing->update(['quantity' => $newQuantity]);

            return $existing;
        }

        return $cart->items()->create([
            'product_id' => $productId,
            'product_variant_id' => $variant?->id,
            'quantity' => $quantity,
        ]);
    }

    /**
     * Check a line could be bought as it stands, without putting it in any
     * cart — for "Buy now". The same rules as addLine(), against the quantity
     * alone since there is no existing line to top up.
     *
     * @return array{0: Product, 1: ProductVariant|null}
     *
     * @throws ValidationException
     */
    public function assertBuyable(string $audience, int $productId, ?int $variantId, int $quantity): array
    {
        $product = $this->forSale($productId, $audience);
        $variant = $this->resolveVariant($product, $variantId);

        $this->assertStockFor($product, $variant, $quantity);
        $this->assertMinimumFor($product, $audience, $quantity);

        return [$product, $variant];
    }

    /**
     * Guard a quantity change on an existing line.
     *
     * Both checks, not just stock: without the minimum a pro buyer could add
     * the required 20, then edit the line down to 1 and walk straight past the
     * bulk rule with wholesale pricing intact.
     *
     * @throws ValidationException
     */
    public function assertQuantity(CartItem $item, int $quantity, string $audience = Product::AUDIENCE_INDIVIDUAL): void
    {
        $item->loadMissing(['product', 'variant']);

        $this->assertStockFor($item->product, $item->variant, $quantity);
        $this->assertMinimumFor($item->product, $audience, $quantity);
    }

    /**
     * Why this basket cannot go to checkout on this price list, or null when
     * it can — the first line under its pro minimum, or a trade-only piece on
     * the retail list.
     *
     * Both happen only when the buyer switches mode with a full basket: the
     * lines were valid when added. CheckoutService refuses them too; this is
     * so the checkout page can send the buyer back to the basket to fix them
     * instead of letting them fill in a form that will be refused.
     */
    public function checkoutIssue(Cart $cart, string $audience): ?string
    {
        foreach ($cart->items as $item) {
            $product = $item->product;

            if (! $product) {
                continue;
            }

            if (! $product->isVisibleTo($audience)) {
                return __('buyer.cart.err_pro_only_line', ['name' => $product->name]);
            }

            $minimum = $product->minOrderQtyFor($audience);

            if ((int) $item->quantity < $minimum) {
                return __('buyer.cart.err_pro_minimum', [
                    'count' => $minimum,
                    'name' => $product->name,
                ]);
            }
        }

        return null;
    }

    /**
     * Hold a line to the minimum its price list demands.
     *
     * Pro prices are open to anyone, on the understanding that the trade buys
     * in bulk: the minimum the admin set on the piece is the whole of that
     * bargain, so it is enforced here rather than left as a display hint.
     * Retail is from one piece.
     *
     * @throws ValidationException
     */
    public function assertMinimumFor(Product $product, string $audience, int $quantity): void
    {
        $minimum = $product->minOrderQtyFor($audience);

        if ($quantity < $minimum) {
            throw ValidationException::withMessages([
                'quantity' => __('buyer.cart.err_pro_minimum', [
                    'count' => $minimum,
                    'name' => $product->name,
                ]),
            ]);
        }
    }

    /**
     * storefront(), not a plain find: a product that is unpublished, or whose
     * seller has been blocked, is not for sale either.
     *
     * Nor is a trade-only piece outside pro mode — the same rule that keeps
     * it off the individual listing, held here too so a stale link or a
     * mode switched mid-visit cannot slip it into a retail basket.
     *
     * @throws ValidationException
     */
    private function forSale(int $productId, string $audience): Product
    {
        $product = Product::storefront()->find($productId);

        if (! $product) {
            throw ValidationException::withMessages([
                'product_id' => 'Ce produit n\'est plus disponible.',
            ]);
        }

        if (! $product->isVisibleTo($audience)) {
            throw ValidationException::withMessages([
                'product_id' => __('buyer.cart.err_pro_only'),
            ]);
        }

        return $product;
    }

    /**
     * Resolve and authorise the variant for a product.
     *
     * Rejects three distinct mistakes that all end in the buyer being charged
     * for something other than what they picked: a variant belonging to a
     * different product, an inactive one, and a missing selection on a
     * product that requires it.
     *
     * @throws ValidationException
     */
    private function resolveVariant(Product $product, ?int $variantId): ?ProductVariant
    {
        if ($variantId === null) {
            if ($product->has_variants) {
                throw ValidationException::withMessages([
                    'variant_id' => 'Veuillez choisir une option avant d\'ajouter au panier.',
                ]);
            }

            return null;
        }

        $variant = ProductVariant::where('id', $variantId)
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->first();

        if (! $variant) {
            throw ValidationException::withMessages([
                'variant_id' => 'Cette option n\'est plus disponible.',
            ]);
        }

        return $variant;
    }

    /**
     * @throws ValidationException
     */
    private function assertStockFor(Product $product, ?ProductVariant $variant, int $quantity): void
    {
        // A variant product keeps products.stock at 0 on purpose, so the
        // variant's own stock is the only meaningful number here.
        $available = $variant !== null
            ? (int) $variant->stock
            : (int) $product->stock;

        if ($quantity > $available) {
            $what = $variant !== null
                ? "{$product->name} ({$variant->label()})"
                : $product->name;

            throw ValidationException::withMessages([
                'quantity' => "Stock insuffisant pour '{$what}' (disponible : {$available}).",
            ]);
        }
    }
}
