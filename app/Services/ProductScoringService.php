<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\Product;
use App\Models\ProductView;
use App\Models\Wishlist;


class ProductScoringService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function apply(Builder $query): Builder {
        $thirtyDaysAgo = now()->subDays(30);

        return $query->addSelect([
            DB::raw("
                (
                    -- Wishlist score
                    (SELECT COUNT(*) FROM wishlists WHERE wishlists.product_id = products.id) * 5
                    +
                    -- Orders score
                    (SELECT COUNT(*) FROM order_items WHERE order_items.product_id = products.id) * 10
                    +
                    -- Rating score
                    (products.average_rating * 15)
                    +
                    -- Recent views score (last 30 days)
                    (SELECT COUNT(*) FROM product_views
                        WHERE product_views.product_id = products.id
                        AND product_views.viewed_at >= '{$thirtyDaysAgo}') * 1
                    +
                    -- Recency score (newer products get up to 20 points)
                    GREATEST(0, 20 - DATEDIFF(NOW(), products.created_at))
                ) AS score
            "),
        ])->orderByDesc('score');
    }

    // ─── Product detail ──────────────────────────────────────
    public function show(string $slug)
    {
        $product = Product::where('slug', $slug)
            ->where('status', 'published')
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
            'product'    => $product,
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
                'user_id'    => auth()->id(),
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
 
         if (!$alreadyViewed) {
             ProductView::create([
                 'product_id' => $productId,
                 'user_id'    => auth()->id(),
                 'ip_address' => request()->ip(),
                 'viewed_at'  => now(),
             ]);
         }
     }
}
