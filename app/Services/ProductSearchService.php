<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ProductSearchService
{
    /** Shortest term worth querying — one letter matches most of the catalogue. */
    public const MIN_TERM_LENGTH = 2;

    /**
     * Narrow a product query to rows matching a free-text term.
     *
     * Case-folded on both sides rather than a bare LIKE: Postgres — which this
     * app runs — treats LIKE as case-sensitive, so a shopper typing "tajine"
     * matched nothing against "Tajine". LOWER() is understood by Postgres,
     * MySQL and the SQLite used in tests alike, unlike ILIKE.
     *
     * Shared by the listing and the header's live suggestions so the two can
     * never disagree about what a search means.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function applyTerm(Builder $query, string $term): Builder
    {
        $needle = '%'.mb_strtolower($term).'%';

        return $query->where(function (Builder $q) use ($needle) {
            $q->whereRaw('LOWER(products.name) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(products.description) LIKE ?', [$needle])
                ->orWhereHas(
                    'store',
                    fn ($s) => $s->whereRaw('LOWER(stores.store_name) LIKE ?', [$needle])
                );
        });
    }

    /**
     * The handful of pieces shown under the header's search field as the
     * shopper types.
     *
     * Deliberately not the listing's paginated payload: this is a preview, so
     * it carries only what a suggestion row draws, and it applies the same
     * storefront + audience gates the listing does — a trade-only piece must
     * not leak to a consumer through the search box either.
     *
     * @return array<int, array<string, mixed>>
     */
    public function suggest(?User $viewer, string $term, int $limit = 6): array
    {
        $term = trim($term);

        if (mb_strlen($term) < self::MIN_TERM_LENGTH) {
            return [];
        }

        $audience = Product::audienceFor($viewer);

        $query = Product::query()
            ->storefront()
            ->whereIn('visibility', ['both', $audience])
            ->with(['store', 'category', 'images']);

        $products = $this->applyTerm($query, $term)
            /*
             * A name hit outranks a description or shop-name hit: someone
             * typing "tajine" wants the tajines, not every product whose blurb
             * mentions one. `id` breaks ties so the list is stable between
             * keystrokes rather than reshuffling under the cursor.
             */
            ->orderByRaw('CASE WHEN LOWER(products.name) LIKE ? THEN 0 ELSE 1 END', [
                '%'.mb_strtolower($term).'%',
            ])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $products->map(fn (Product $product) => [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => $product->priceFor($audience),
            'image' => $product->images->first()?->url,
            'store_name' => $product->store?->store_name,
            // `name` is a per-locale map on the model; only ever ship the
            // resolved string.
            'category_name' => $product->category?->translated_name,
        ])->all();
    }

    /**
     * How many pieces the term matches in total, so the panel can offer
     * "see all N results" rather than implying the preview is the whole set.
     */
    public function countFor(?User $viewer, string $term): int
    {
        $term = trim($term);

        if (mb_strlen($term) < self::MIN_TERM_LENGTH) {
            return 0;
        }

        $audience = Product::audienceFor($viewer);

        return $this->applyTerm(
            Product::query()
                ->storefront()
                ->whereIn('visibility', ['both', $audience]),
            $term
        )->count();
    }
}
