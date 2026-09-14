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
     * Thirteen: five for the swipable spotlight carousel and eight for the
     * 4 x 2 grid beside it. Every card is fetched up front rather than
     * paginated: this is a shop window, not a listing, and thirteen rows
     * costs less than the request that fetching them lazily would need.
     *
     * Ordered at random rather than newest-first. The rail is a shop window
     * onto the whole catalogue, and sorting it by id turned it into a "just
     * added" shelf: the same products for every visitor until someone
     * publishes something, and no exposure at all for a seller who listed
     * early. Rerolling per request also means a second visit shows a
     * different set, which is the point of a window.
     *
     * `inRandomOrder()` sorts the whole matching set, so it is only cheap
     * while the catalogue is small — if this starts showing up in query
     * timings, sample ids first and fetch by them instead.
     *
     * `store.city` is eager loaded because BuyerProductResource reaches through
     * to `store->city->name`; without it every card would fire two extra
     * queries. Products with no store are excluded for the same reason — the
     * resource assumes the relation is there.
     *
     * `variants.media` is eager loaded so BuyerProductResource can fold each
     * variant's own photography into `gallery_images` — otherwise the rail's
     * hover gallery only ever cycles a product's own images, and a variant
     * product whose only extra photo lives on a colour variant would show a
     * single static frame.
     */
    public function featuredProducts(int $limit = 13): Collection
    {
        return Product::query()
            // storefront(false): temporarily includes unpriced products for
            // an internal demo — see Product::scopeStorefront()'s docblock.
            ->storefront(false)
            ->whereHas('store')
            ->with(['store.city', 'category', 'images', 'variants.media'])
            ->withCount(['wishlists'])
            ->inRandomOrder()
            ->take($limit)
            ->get();
    }
}
