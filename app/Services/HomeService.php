<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class HomeService
{
    /**
     * The products showcased in the "Explore our products" strip on the home
     * page.
     *
     * `store.city` is eager loaded because BuyerProductResource reaches through
     * to `store->city->name`; without it every card would fire two extra
     * queries. Products with no store are excluded for the same reason — the
     * resource assumes the relation is there.
     */
    public function featuredProducts(int $limit = 6): Collection
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
