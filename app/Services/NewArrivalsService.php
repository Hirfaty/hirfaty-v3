<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class NewArrivalsService
{
    /**
     * The most recently listed pieces, taking turns across categories (see
     * query()).
     *
     * Recency is read from `id` rather than `created_at`: the seeder and the
     * bulk importer both write a batch inside one second, so a timestamp sort
     * leaves those rows in an arbitrary order that changes between requests —
     * which would shuffle the grid under a shopper paging through it.
     *
     * `$audience` is the price list the visitor is browsing in
     * (PriceModeService::audience()), which decides which pieces are listed.
     */
    public function paginate(string $audience, int $perPage = 12): LengthAwarePaginator
    {
        return $this->query($audience)
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
    public function forHome(string $audience, int $limit = 18): Collection
    {
        return $this->query($audience)
            ->whereHas('store')
            ->take($limit)
            ->get();
    }

    /**
     * The shared shape of both: gated to what this viewer may see, with
     * everything `BuyerProductResource` reads eager loaded, in turns across
     * categories.
     *
     * Not plain newest-first: when the desk files a batch of thirty pieces in
     * one category, that alone would fill the home row and the first pages
     * here. Each piece is ranked by recency within its own category, and the
     * list runs rank by rank — the newest piece of every category, then the
     * second newest of every category, and so on — newest first within a
     * rank. Every published piece still appears, just interleaved.
     *
     * The rank is computed over the same gated set as the outer query, so a
     * hidden or unpublished piece never takes a category's turn.
     *
     * @return Builder<Product>
     */
    private function query(string $audience): Builder
    {
        $ranked = $this->visibleTo(Product::query(), $audience)
            ->select('id')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY category_id ORDER BY id DESC) AS category_rank');

        return $this->visibleTo(Product::query(), $audience)
            ->joinSub($ranked, 'ranked', 'ranked.id', '=', 'products.id')
            ->select('products.*')
            // BuyerProductResource reaches through to `store->city->name`;
            // without the eager load every card fires two extra queries.
            // `variants.media` lets it fold variant photography into
            // `gallery_images` too — see HomeService::featuredProducts() for
            // the same reasoning.
            ->with(['store.city', 'category', 'images', 'variants.media'])
            ->withCount(['wishlists'])
            ->orderBy('ranked.category_rank')
            ->orderByDesc('products.id');
    }

    /**
     * Published, priced, in a selling shop, and open to the audience being
     * browsed. Without the audience gate a trade-only piece would be
     * reachable here by anyone, which is exactly the hole `visibility` exists
     * to close.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    private function visibleTo(Builder $query, string $audience): Builder
    {
        return $query
            ->storefront()
            ->whereIn('visibility', ['both', $audience]);
    }
}
