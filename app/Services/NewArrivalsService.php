<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NewArrivalsService
{
    /**
     * The most recently listed pieces, newest first.
     *
     * Ordered on `id` rather than `created_at`: the seeder and the bulk
     * importer both write a batch inside one second, so a timestamp sort
     * leaves those rows in an arbitrary order that changes between requests —
     * which would shuffle the grid under a shopper paging through it.
     *
     * The audience gate is the same one the main listing applies. Without it
     * a trade-only piece would be reachable here by anyone, which is exactly
     * the hole `visibility` exists to close.
     */
    public function paginate(?User $viewer, int $perPage = 12): LengthAwarePaginator
    {
        $audience = Product::audienceFor($viewer);

        return Product::query()
            // storefront(false): temporarily includes unpriced products for
            // an internal demo — see Product::scopeStorefront()'s docblock.
            ->storefront(false)
            ->whereIn('visibility', ['both', $audience])
            // BuyerProductResource reaches through to `store->city->name`;
            // without the eager load every card fires two extra queries.
            // `variants.media` lets it fold variant photography into
            // `gallery_images` too — see HomeService::featuredProducts() for
            // the same reasoning.
            ->with(['store.city', 'category', 'images', 'variants.media'])
            ->withCount(['wishlists'])
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
