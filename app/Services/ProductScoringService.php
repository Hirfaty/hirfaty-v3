<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductView;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Inertia\Inertia;

class ProductScoringService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Rank a product query by a blend of demand signals and freshness.
     *
     * Recency is expressed as CASE buckets over plain timestamp comparisons
     * rather than a date-difference function: the original used MySQL's
     * DATEDIFF/GREATEST, which do not exist on Postgres (production) and
     * behave differently on SQLite (tests) — so every listing that fell back
     * to this default sort was erroring out. Comparisons against bound
     * timestamps are the one formulation all three drivers agree on.
     */
    public function apply(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        // addSelect() on a query with no columns yet would select *only* the
        // score and drop every product column, so pin the base columns first.
        if ($query->getQuery()->columns === null) {
            $query->select("{$table}.*");
        }

        return $query->selectRaw(
            '(
                (SELECT COUNT(*) FROM wishlists WHERE wishlists.product_id = products.id) * 5
                + (SELECT COUNT(*) FROM order_items WHERE order_items.product_id = products.id) * 10
                + (products.average_rating * 15)
                + (SELECT COUNT(*) FROM product_views
                     WHERE product_views.product_id = products.id
                       AND product_views.viewed_at >= ?) * 1
                + CASE
                    WHEN products.created_at >= ? THEN 20
                    WHEN products.created_at >= ? THEN 12
                    WHEN products.created_at >= ? THEN 6
                    ELSE 0
                  END
            ) AS score',
            [
                now()->subDays(30),
                now()->subDays(3),
                now()->subDays(14),
                now()->subDays(30),
            ]
        )->orderByDesc('score');
    }

    /**
     * Popular pieces to browse next, for the bottom of the cart page.
     *
     * Same visibility/pricing gate as the listing (`storefront()` +
     * `visibility`), ranked by the same demand score, with whatever is
     * already in the basket excluded so the row never just repeats it.
     *
     * @param  array<int, int>  $excludeProductIds
     */
    public function suggestionsFor(?User $user, array $excludeProductIds = [], int $limit = 8): Collection
    {
        $audience = Product::audienceFor($user);

        $query = Product::query()
            ->storefront()
            ->whereIn('visibility', ['both', $audience])
            ->when(
                $excludeProductIds !== [],
                fn ($q) => $q->whereNotIn('id', $excludeProductIds),
            )
            ->with(['store.city', 'category', 'images', 'variants.media'])
            ->withCount(['wishlists', 'orderItems']);

        return $this->apply($query)->limit($limit)->get();
    }

    // ─── Product detail ──────────────────────────────────────
    public function show(string $slug)
    {
        $product = Product::storefront()
            ->where('slug', $slug)
            ->with(['store.city', 'category', 'images', 'videos'])
            ->withCount('wishlists')
            ->firstOrFail();

        // Record the view
        $this->recordView($product->id);

        // Is it in the current user's wishlist?
        $inWishlist = auth()->check()
            ? Wishlist::where('user_id', auth()->id())
                ->where('product_id', $product->id)
                ->exists()
            : false;

        return Inertia::render('Buyer/Products/Show', [
            'product' => $product,
            'inWishlist' => $inWishlist,
        ]);
    }

    // ─── Toggle wishlist ─────────────────────────────────────
    public function toggleWishlist(Product $product)
    {
        $existing = Wishlist::where('user_id', auth()->id())
            ->where('product_id', $product->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $inWishlist = false;
        } else {
            Wishlist::create([
                'user_id' => auth()->id(),
                'product_id' => $product->id,
            ]);
            $inWishlist = true;
        }

        return back()->with('inWishlist', $inWishlist);
    }

    // ─── Private ─────────────────────────────────────────────
    private function recordView(int $productId): void
    {
        // Avoid counting the same user/IP multiple times per hour
        $alreadyViewed = ProductView::where('product_id', $productId)
            ->where(function ($q) {
                $q->where('user_id', auth()->id())
                    ->orWhere('ip_address', request()->ip());
            })
            ->where('viewed_at', '>=', now()->subHour())
            ->exists();

        if (! $alreadyViewed) {
            ProductView::create([
                'product_id' => $productId,
                'user_id' => auth()->id(),
                'ip_address' => request()->ip(),
                'viewed_at' => now(),
            ]);
        }
    }
}
