<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
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
     * @throws ValidationException
     */
    public function assertStock(CartItem $item, int $quantity): void
    {
        $item->loadMissing(['product', 'variant']);

        $this->assertStockFor($item->product, $item->variant, $quantity);
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
