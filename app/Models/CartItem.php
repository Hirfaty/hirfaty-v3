<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $cart_id
 * @property int $product_id
 * @property int|null $product_variant_id Null when the product has no variants.
 * @property int $quantity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Cart $cart
 * @property-read Product $product
 * @property-read ProductVariant|null $variant
 *
 * @mixin \Eloquent
 */
class CartItem extends Model
{
    protected $fillable = ['cart_id', 'product_id', 'product_variant_id', 'quantity'];

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->with(['images', 'store']);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->with('options');
    }

    /**
     * Units available for this line: the variant's own stock when one is
     * chosen, otherwise the product's. Reading products.stock for a variant
     * product would always give 0 — the admin zeroes it precisely because
     * stock is tracked per variant instead.
     */
    public function availableStock(): int
    {
        return $this->product_variant_id !== null
            ? (int) ($this->variant?->stock ?? 0)
            : (int) ($this->product?->stock ?? 0);
    }

    /**
     * Unit price for the given audience, letting the chosen variant override
     * the product's list price when it defines one. ProductVariant::priceFor()
     * already falls back to the parent product, so this stays correct for a
     * variant that sets no override.
     */
    public function unitPriceFor(string $audience): float
    {
        $source = $this->variant ?? $this->product;

        return (float) ($source?->priceFor($audience) ?? 0);
    }

    /**
     * Human label for the chosen variant ("Rouge / M"), or null when the
     * product has no variants.
     */
    public function variantLabel(): ?string
    {
        return $this->variant?->label();
    }
}
