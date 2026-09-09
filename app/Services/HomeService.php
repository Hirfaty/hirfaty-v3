<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class HomeService
{
    /**
     * The products showcased in the "Explore our products" rail on the home
     * page.
     *
     * Twelve, because that strip is now a horizontal rail paged with arrows
     * rather than a single row of four — the row only has to be long enough
     * that paging through it is worth doing, and twelve is three screens of
     * it on a laptop. Every card is fetched up front rather than paginated:
     * this is a shop window, not a listing, and a dozen rows costs less than
     * the request that fetching them lazily would need.
     *
     * `store.city` is eager loaded because BuyerProductResource reaches through
     * to `store->city->name`; without it every card would fire two extra
     * queries. Products with no store are excluded for the same reason — the
     * resource assumes the relation is there.
     */
    public function featuredProducts(int $limit = 12): Collection
    {
        return Product::query()
            // storefront(false): temporarily includes unpriced products for
            // an internal demo — see Product::scopeStorefront()'s docblock.
            ->storefront(false)
            ->whereHas('store')
            ->with(['store.city', 'category', 'images'])
            ->withCount(['wishlists'])
            ->latest('id')
            ->take($limit)
            ->get();
    }
}
