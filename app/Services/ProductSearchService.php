<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProductSearchService
{
    /** Shortest term worth querying — one letter matches most of the catalogue. */
    public const MIN_TERM_LENGTH = 2;

    /**
     * What each accented letter is searched as. The last row is the same
     * accents stored as a separate combining mark, as text pasted from a Mac
     * can be.
     */
    private const FOLDS = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae',
        'ç' => 'c',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ñ' => 'n',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'œ' => 'oe',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
        'ý' => 'y', 'ÿ' => 'y',
        "\u{0300}" => '', "\u{0301}" => '', "\u{0302}" => '', "\u{0303}" => '', "\u{0308}" => '', "\u{0327}" => '',
    ];

    /**
     * Narrow a product query to rows matching a free-text term.
     *
     * Shared by the listing and the header's live suggestions so the two can
     * never disagree about what a search means.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function applyTerm(Builder $query, string $term): Builder
    {
        $needle = $this->needle($term);

        return $query->where(function (Builder $q) use ($needle) {
            $q->whereRaw($this->foldedLike('products.name'), [$needle])
                ->orWhereRaw($this->foldedLike('products.description'), [$needle])
                ->orWhereHas(
                    'store',
                    fn ($s) => $s->whereRaw($this->foldedLike('stores.store_name'), [$needle])
                );
        });
    }

    /**
     * The LIKE pattern for a term, to bind to foldedLike(): lower case and
     * without accents, so "EMAILLE" and "émaillé" search for the same thing.
     */
    public function needle(string $term): string
    {
        return '%'.strtr(mb_strtolower($term), self::FOLDS).'%';
    }

    /**
     * A `column LIKE ?` that ignores case and accents in the column.
     *
     * Folded on both sides rather than a bare LIKE: Postgres — which this app
     * runs — treats LIKE as case- and accent-sensitive, so a shopper typing
     * "tajine emaille" matched nothing against "Tajine émaillé".
     *
     * Postgres uses its unaccent extension: the REPLACE() chain below took six
     * times as long over 5,000 products, too slow for suggestions fired as the
     * shopper types. The chain is for the SQLite used locally and in tests,
     * whose LOWER() leaves non-ASCII letters alone — hence the capitals.
     *
     * `$column` goes into the SQL as written: never pass it user input.
     */
    public function foldedLike(string $column): string
    {
        if (DB::getDriverName() === 'pgsql') {
            return "unaccent(LOWER({$column})) LIKE unaccent(?)";
        }

        $sql = "LOWER({$column})";

        foreach (self::FOLDS as $accented => $plain) {
            $sql = "REPLACE({$sql}, '{$accented}', '{$plain}')";

            if (($capital = mb_strtoupper($accented)) !== $accented) {
                $sql = "REPLACE({$sql}, '{$capital}', '{$plain}')";
            }
        }

        return "{$sql} LIKE ?";
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
            ->orderByRaw('CASE WHEN '.$this->foldedLike('products.name').' THEN 0 ELSE 1 END', [
                $this->needle($term),
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
