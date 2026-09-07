<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Adding to a cart, with the variant rules applied in one place.
 *
 * Both the "add to cart" button and the guest-cart merge run through here, so
 * a line that localStorage produced is held to exactly the same checks as one
 * the server just created.
 */
class CartService
{
    /**
     * Add (or top up) a line, after checking the variant belongs to the
     * product and there is stock to cover the new total quantity.
     *
     * @throws ValidationException
     */
    public function addLine(Cart $cart, int $productId, ?int $variantId, int $quantity = 1): CartItem
    {
        $product = Product::find($productId);

        if (! $product) {
            throw ValidationException::withMessages([
                'product_id' => 'Ce produit n\'existe plus.',
            ]);
        }

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
        $this->assertMinimumFor($product, $cart->user, $newQuantity);

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
     * Guard a quantity change on an existing line.
     *
     * Both checks, not just stock: without the minimum a trade buyer could add
     * the required 20, then edit the line down to 1 and walk straight past the
     * bulk rule with wholesale pricing intact.
     *
     * @throws ValidationException
     */
    public function assertQuantity(CartItem $item, int $quantity): void
    {
        $item->loadMissing(['product', 'variant', 'cart.user']);

        $this->assertStockFor($item->product, $item->variant, $quantity);
        $this->assertMinimumFor($item->product, $item->cart?->user, $quantity);
    }

    /**
     * Hold a line to the minimum its buyer's price list demands.
     *
     * Wholesale rates are granted on the understanding that the trade buys in
     * bulk, so the minimum is part of the bargain rather than a display hint —
     * `min_order_qty_professional` had been carried on the product and shown on
     * the page since trade pricing landed, but `minOrderQtyFor()` was never
     * called by anything, so a verified professional could buy a single piece
     * at the wholesale price.
     *
     * Keyed off the *cart owner*, not the request user, so the merge of a
     * localStorage cart is held to the same rule as a live click.
     *
     * @throws ValidationException
     */
    private function assertMinimumFor(Product $product, ?User $user, int $quantity): void
    {
        $minimum = $product->minOrderQtyFor(Product::audienceFor($user));

        if ($quantity < $minimum) {
            throw ValidationException::withMessages([
                'quantity' => "Commande minimum de {$minimum} pièces pour '{$product->name}' au tarif professionnel.",
            ]);
        }
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
