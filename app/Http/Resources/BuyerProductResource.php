<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Services\HtmlSanitizer;
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
            // Sanitised editor HTML, for the product page; cards show the text.
            'description' => $this->description,
            'description_text' => HtmlSanitizer::toText($this->description),

            // Retail list — what every audience is billed today.
            'base_price' => $this->base_price,
            'sale_price' => $this->sale_price,

            /*
             * For a variant product this is 0 by design (the admin zeroes it;
             * stock lives on each variant), so the storefront must read
             * `variants` for availability instead — `in_stock` below already
             * accounts for that, use it rather than comparing `stock` to 0.
             */
            'stock' => $this->stock,
            'has_variants' => (bool) $this->has_variants,
            'in_stock' => $this->isInStock(),
            'average_rating' => $this->average_rating,

            /*
             * Only present on the detail page, which eager-loads them. The
             * listing skips the joins, and whenLoaded keeps the key absent
             * there rather than shipping an empty array that would read as
             * "this product has no variants".
             */
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),

            // Selector rows ("Couleur", then "Taille"), derived server-side so
            // ordering is stable rather than depending on object key order.
            'option_groups' => $this->when(
                $this->relationLoaded('variants'),
                fn () => $this->optionGroups(),
            ),

            /*
             * Packed weight and dimensions, as the admin entered them, for
             * the detail page's spec rows. Sent wherever variants are loaded,
             * like `option_groups`. Every figure is nullable — the page shows
             * only what was filled in — and a variant's own figures
             * (ProductVariantResource) replace these axis by axis.
             */
            'measurements' => $this->when(
                $this->relationLoaded('variants'),
                fn () => [
                    'weight' => $this->figure($this->weight),
                    'weight_unit' => $this->weight_unit ?? 'kg',
                    'length' => $this->figure($this->length),
                    'width' => $this->figure($this->width),
                    'height' => $this->figure($this->height),
                    'dimension_unit' => $this->dimension_unit ?? 'cm',
                ],
            ),

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
                /*
                 * Drives the "Vérifié par Hirfaty" mark beside the shop name.
                 *
                 * This is the same approval that lets a shop sell at all
                 * (Store::isApproved, granted by StoreVerificationService),
                 * not a second flag — so in practice every shop a buyer can
                 * reach carries it. Exposed as a boolean rather than the raw
                 * `verification_status` so the storefront never learns the
                 * internal state names, and so splitting "cleared to sell"
                 * from "trusted" later only changes this one line.
                 */
                'is_verified' => (bool) $this->store?->isApproved(),
                // A store without a city must not fatal the whole listing.
                'city_name' => $this->store?->city?->translated_name,
                // For the storefront's seller card. Nullable — a shop that
                // never uploaded one falls back to an initial.
                'logo' => $this->store?->logo,
            ],

            'images' => $this->images,
            // Only the detail page plays videos, and it eager-loads them. A
            // card reading this lazily fired one media query per product.
            'videos' => $this->relationLoaded('videos') ? $this->videos : [],

            /*
             * Own images plus every variant's, folded into one deduplicated
             * list. Only present when the caller eager-loaded `variants.media`
             * (currently just the home rail): computing this from a lazy
             * `variants` load would fire a query per card, and most callers
             * of this resource (the listing grid, the detail page's own
             * gallery) already have their own reason to keep a variant's
             * photography scoped to that variant rather than merged in.
             */
            'gallery_images' => $this->when(
                $this->relationLoaded('variants'),
                fn () => $this->images
                    ->concat($this->variants->flatMap(
                        fn ($variant) => $variant->relationLoaded('media')
                            ? $variant->media->where('type', 'image')
                            : []
                    ))
                    ->unique('id')
                    ->map(fn ($media) => [
                        'id' => $media->id,
                        'url' => $media->url,
                        'type' => $media->type,
                    ])
                    ->values(),
            ),

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
     * A decimal column as a number, or null when blank or zero — "0 kg" is an
     * unfilled field, not a measurement worth showing.
     */
    private function figure(mixed $value): ?float
    {
        return $value !== null && (float) $value > 0 ? (float) $value : null;
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

    /**
     * Is anything actually buyable? A variant product's own stock column is
     * always 0, so availability has to come from its variants.
     */
    private function isInStock(): bool
    {
        if (! $this->has_variants) {
            return (int) $this->stock > 0;
        }

        if (! $this->relationLoaded('variants')) {
            // Cheap existence check for the listing, which doesn't load them.
            return $this->variants()->where('is_active', true)->where('stock', '>', 0)->exists();
        }

        return $this->variants->contains(fn ($v) => $v->is_active && (int) $v->stock > 0);
    }

    /**
     * Collapse the variants' options into one row per axis, preserving
     * first-seen order, e.g.
     *
     *   [{ name: "Couleur", type: "color",
     *      values: [{ value: "Rouge" }, { value: "Bleu" }] }, ...]
     *
     * The frontend renders a selector per group and resolves the chosen
     * combination back to a concrete variant.
     *
     * Grouping on `name` is why the axis has to be stored as the axis: when
     * the admin wrote the label into it, every value became its own group and
     * no variant could satisfy them all.
     *
     * Ordering rides on variant `position`, which the admin's generator emits
     * in axis-value order — so "S, M, L" stays "S, M, L" rather than being
     * re-sorted alphabetically.
     *
     * @return array<int, array<string, mixed>>
     */
    private function optionGroups(): array
    {
        $groups = [];

        foreach ($this->variants->sortBy('position') as $variant) {
            if (! $variant->relationLoaded('options')) {
                continue;
            }

            foreach ($variant->options as $option) {
                $groups[$option->name] ??= [
                    'name' => $option->name,
                    'type' => $option->type,
                    'values' => [],
                ];

                // Keyed by value while building so a value shared by several
                // variants (Rouge/S, Rouge/M) is listed once.
                $groups[$option->name]['values'][$option->value] ??= [
                    'value' => $option->value,
                ];
            }
        }

        return array_values(array_map(function (array $group) {
            $group['values'] = array_values($group['values']);

            return $group;
        }, $groups));
    }
}
