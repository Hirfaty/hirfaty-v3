<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BuyerProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /*
         * Asked via audienceFor() rather than checking the role directly, so
         * this cannot drift from what CheckoutService bills — both route
         * through the same helper, and both therefore require a *verified*
         * trade account. An unverified one is shown, and charged, retail.
         */
        $isProfessional = Product::audienceFor($request->user()) === Product::AUDIENCE_PROFESSIONAL;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,

            // Retail list — what every audience is billed today.
            'base_price' => $this->base_price,
            'sale_price' => $this->sale_price,

            'stock' => $this->stock,
            'has_variants' => (bool) $this->has_variants,
            'average_rating' => $this->average_rating,

            'category' => $this->category ? [
                'id' => $this->category->id,
                // `name` is a per-locale map on the model; the storefront only
                // ever wants the resolved string, so don't leak the raw map.
                'translated_name' => $this->category->translated_name,
            ] : null,

            'store' => [
                'id' => $this->store?->id,
                'slug' => $this->store?->slug,
                'description' => $this->store?->description,
                'store_name' => $this->store?->store_name,
                // A store without a city must not fatal the whole listing.
                'city_name' => $this->store?->city?->name,
                // For the storefront's seller card. Nullable — a shop that
                // never uploaded one falls back to an initial.
                'logo' => $this->store?->logo,
            ],

            'images' => $this->images,
            'videos' => $this->videos,

            'wishlists_count' => $this->whenCounted('wishlists'),

            /*
             * Wholesale figures are exposed only to professional buyers:
             * publishing a seller's trade price to consumers undercuts their
             * retail price. `professional_price` is a *reference* rate — the
             * checkout still bills the retail list to everyone, so the UI
             * labels it as indicative rather than as "your price".
             */
            $this->mergeWhen($isProfessional, fn () => [
                'professional_price' => $this->professional_price,
                'professional_sale_price' => $this->professional_sale_price,
                'professional_effective_price' => $this->hasProfessionalRate()
                    ? $this->priceFor(Product::AUDIENCE_PROFESSIONAL)
                    : null,
                'min_order_qty_professional' => max(1, (int) $this->min_order_qty_professional),
            ]),
        ];
    }

    /**
     * Whether a distinct trade rate was actually configured. `priceFor()`
     * silently falls back to retail when it wasn't, which would otherwise
     * render as a "pro price" identical to the public one.
     */
    private function hasProfessionalRate(): bool
    {
        return $this->professional_price !== null
            || $this->professional_sale_price !== null;
    }
}
