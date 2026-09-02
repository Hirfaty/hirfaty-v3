<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One purchasable variant, shaped for the storefront selector.
 *
 * @mixin ProductVariant
 */
class ProductVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Same choke point as BuyerProductResource and CheckoutService, so the
        // price shown on a variant is the price that variant is billed at.
        $audience = Product::audienceFor($request->user());

        return [
            'id' => $this->id,
            'sku' => $this->sku,

            'stock' => (int) $this->stock,
            'in_stock' => (int) $this->stock > 0,

            // What this variant actually costs the current viewer.
            'price' => $this->priceFor($audience),
            'original_price' => $this->originalPriceFor($audience),

            // "Rouge / M" — for the cart line and the selected-variant summary.
            'label' => $this->label(),

            'options' => $this->whenLoaded('options', fn () => $this->options->map(fn ($option) => [
                'type' => $option->type,
                'name' => $option->name,
                'value' => $option->value,
            ])->values()),

            /*
             * Variant photography, when the seller uploaded any. Drives the
             * gallery swap on selection; the page falls back to the product's
             * own images when a variant has none, so this may be empty.
             */
            'images' => $this->whenLoaded('media', fn () => $this->media
                ->where('type', 'image')
                ->map(fn ($m) => ['id' => $m->id, 'url' => $m->url])
                ->values()
            ),
        ];
    }

    /**
     * The struck-through "was" price, or null when there is nothing to strike.
     *
     * Only meaningful when this variant sets no override of its own: an
     * override replaces the product's whole price list, and a variant has no
     * separate sale price, so there is no before/after pair to show. When it
     * does inherit, a discount exists only if the product's sale price is
     * genuinely below its list price.
     */
    private function originalPriceFor(string $audience): ?float
    {
        $override = $audience === Product::AUDIENCE_PROFESSIONAL
            ? $this->professional_price_override
            : $this->price_override;

        if ($override !== null) {
            return null;
        }

        $product = $this->product;

        if (! $product) {
            return null;
        }

        [$list, $sale] = $audience === Product::AUDIENCE_PROFESSIONAL
            ? [$product->professional_price, $product->professional_sale_price]
            : [$product->base_price, $product->sale_price];

        if ($list === null || $sale === null) {
            return null;
        }

        return (float) $sale < (float) $list ? (float) $list : null;
    }
}
