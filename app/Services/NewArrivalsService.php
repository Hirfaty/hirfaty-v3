<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

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
        return $this->query($viewer)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * The same pieces, for the home page's single scrolling row.
     *
     * Fetched whole rather than paginated: the row holds eighteen — three
     * slides of six, see `NewArrivals.tsx` — the visitor scrolls between them
     * by hand, and eighteen rows cost far less than the round trip that
     * turning a page lazily would need. The same reasoning
     * `HomeService::featuredProducts()` gives for the rail above it.
     *
     * `whereHas('store')` because `BuyerProductResource` reaches through to
     * `store->city->name` and assumes the relation is there — the paginated
     * page gets away without it only because nothing has yet published a
     * product with no shop.
     *
     * @return Collection<int, Product>
     */
    public function forHome(?User $viewer, int $limit = 18): Collection
    {
        return $this->query($viewer)
            ->whereHas('store')
            ->take($limit)
            ->get();
    }

    /**
     * The shared shape of both: newest first, gated to what this viewer may
     * see, with everything `BuyerProductResource` reads eager loaded.
     *
     * @return Builder<Product>
     */
    private function query(?User $viewer): Builder
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
            ->orderByDesc('id');
    }
}
