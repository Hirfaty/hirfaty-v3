<?php

namespace App\Http\Resources;

use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One basket line, priced for whoever is looking at it.
 *
 * The cart page used to read `sale_price ?? base_price` off the product
 * itself. That ignored both the chosen variant's override and the viewer's
 * price list, so a verified trade buyer was quoted retail in the basket and
 * then billed wholesale at the till — and the header dropdown, which already
 * routes through CartItem::unitPriceFor(), disagreed with the cart page on the
 * same screen.
 *
 * Everything money-related here therefore goes through the same helpers
 * CheckoutService uses, so what the basket totals and what the card is charged
 * cannot drift apart.
 *
 * @mixin CartItem
 */
class CartLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $audience = Product::audienceFor($request->user());

        $unit = $this->unitPriceFor($audience);
        $qty = (int) $this->quantity;

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'name' => $this->product?->name ?? '',
            'slug' => $this->product?->slug,

            // Grouping key for the per-store delivery fee: checkout splits one
            // basket into an order per store and charges Amana once each.
            'store_id' => $this->product?->store_id,
            'store_name' => $this->product?->store?->store_name ?? '',

            /*
             * The chosen variant's own photo wins when the seller uploaded one
             * — same precedence as the product page gallery and the header
             * dropdown, so a "Rouge" line doesn't show the default colour.
             */
            'image' => $this->variant?->media->first()?->url
                ?? $this->product?->images->first()?->url,

            // "Rouge / M", or null on a product without variants.
            'variant_label' => $this->variantLabel(),

            'quantity' => $qty,

            /*
             * Null when the product has no agreed price yet (an admin may
             * catalogue a piece before the price is settled). Checkout refuses
             * such a line outright, so the page flags it rather than quietly
             * totalling it as free.
             */
            'unit_price' => $unit,
            'line_total' => $unit === null ? null : $unit * $qty,
            // The struck-through "was", or null when nothing is discounted.
            'original_unit_price' => $this->originalUnitPrice($audience),

            // Variant-aware: a variant product keeps products.stock at 0 by
            // design, so the stepper must read the variant's own figure.
            'available_stock' => $this->availableStock(),

            // Above 1 only for a verified trade account. The stepper floors on
            // it so the buyer can't dial below what CartService will accept.
            'min_quantity' => $this->product?->minOrderQtyFor($audience) ?? 1,
        ];
    }

    /**
     * The undiscounted list price for this line, or null when there is no
     * before/after pair to show.
     *
     * A variant override replaces the product's whole price list rather than
     * discounting it, so an overridden line has nothing to strike through.
     * Otherwise the comparison has to follow whichever track priceFor()
     * actually used — a product with no trade rate falls back to retail, and
     * striking the (absent) professional price there would render nothing.
     */
    private function originalUnitPrice(string $audience): ?float
    {
        $product = $this->product;

        if (! $product) {
            return null;
        }

        if ($this->variant !== null) {
            $override = $audience === Product::AUDIENCE_PROFESSIONAL
                ? $this->variant->professional_price_override
                : $this->variant->price_override;

            if ($override !== null) {
                return null;
            }
        }

        $onProList = $audience === Product::AUDIENCE_PROFESSIONAL
            && ($product->professional_sale_price ?? $product->professional_price) !== null;

        [$list, $sale] = $onProList
            ? [$product->professional_price, $product->professional_sale_price]
            : [$product->base_price, $product->sale_price];

        if ($list === null || $sale === null) {
            return null;
        }

        return (float) $sale < (float) $list ? (float) $list : null;
    }
}
