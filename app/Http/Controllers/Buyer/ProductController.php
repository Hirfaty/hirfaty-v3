<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Resources\BuyerProductResource;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductView;
use App\Models\Wishlist;
use App\Services\ProductScoringService;
use Auth;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function __construct(private ProductScoringService $scoring) {}

    /** Sort keys the listing accepts; anything else falls back to relevance. */
    private const SORTS = ['score', 'latest', 'price_asc', 'price_desc', 'rating'];

    public function index(Request $request)
    {
        $audience = Product::audienceFor($request->user());

        $query = Product::query()
            ->storefront()
            // A product may be listed for consumers, for the trade, or both.
            // Without this the storefront showed wholesale-only items to
            // guests — `visibility` existed but was never enforced.
            ->whereIn('visibility', ['both', $audience])
            ->with(['store.city', 'category', 'images'])
            ->withCount(['wishlists', 'orderItems']);

        if ($search = trim((string) $request->input('search'))) {
            /*
             * Case-folded on both sides rather than a bare LIKE: Postgres —
             * which this app runs — treats LIKE as case-sensitive, so a
             * shopper typing "tajine" matched nothing against "Tajine".
             * LOWER() is understood by Postgres, MySQL and the SQLite used
             * in tests alike, unlike ILIKE.
             */
            $needle = '%'.mb_strtolower($search).'%';

            $query->where(function ($q) use ($needle) {
                $q->whereRaw('LOWER(products.name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(products.description) LIKE ?', [$needle])
                    ->orWhereHas(
                        'store',
                        fn ($s) => $s->whereRaw('LOWER(stores.store_name) LIKE ?', [$needle])
                    );
            });
        }

        if ($catId = $request->input('category_id')) {
            // Selecting a root category should also surface its children,
            // otherwise the top-level pills match almost nothing.
            $query->where(function ($q) use ($catId) {
                $q->where('category_id', $catId)
                    ->orWhereHas('category', fn ($c) => $c->where('parent_id', $catId));
            });
        }

        if ($max = $request->input('max_price')) {
            $query->where('base_price', '<=', $max);
        }

        if ($min = $request->input('min_rating')) {
            $query->where('average_rating', '>=', $min);
        }

        if ($request->boolean('in_stock')) {
            $query->where('stock', '>', 0);
        }

        $sort = in_array($request->input('sort'), self::SORTS, true)
            ? $request->input('sort')
            : 'score';

        // Order on the figure the card actually shows, not on base_price —
        // otherwise a discounted piece sorts by its struck-through price and
        // appears out of sequence to the shopper.
        $effectivePrice = 'COALESCE(products.sale_price, products.base_price)';

        $query = match ($sort) {
            'latest' => $query->latest(),
            'price_asc' => $query->orderByRaw("{$effectivePrice} ASC"),
            'price_desc' => $query->orderByRaw("{$effectivePrice} DESC"),
            'rating' => $query->orderByDesc('average_rating'),
            default => $this->scoring->apply($query),
        };

        $paginator = $query->paginate(12)->withQueryString();

        return Inertia::render('buyer/products/index', [
            /*
             * Merged rather than replaced so "load more" appends a page
             * instead of swapping the grid out. Only the items are merged —
             * the paginator's own `links` array would otherwise accumulate
             * duplicates on every append, so the meta is sent separately and
             * replaced each time.
             */
            'products' => Inertia::merge(
                BuyerProductResource::collection($paginator->items())->resolve($request)
            ),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'has_more' => $paginator->hasMorePages(),
            ],
            // Drives the category strip under the hero. Top level only — the
            // strip is a browse row, not a full taxonomy tree. `translated_name`
            // is appended by the model accessor, since `name` is a per-locale
            // object that must never be rendered raw.
            'categories' => Category::whereNull('parent_id')
                ->orderBy('id')
                ->get()
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'slug' => $category->slug,
                    'translated_name' => $category->translated_name,
                ]),
            'filters' => [
                'search' => $request->input('search', ''),
                'category_id' => $request->input('category_id', ''),
                'max_price' => $request->input('max_price', ''),
                'min_rating' => $request->input('min_rating', ''),
                'in_stock' => $request->boolean('in_stock'),
                'sort' => $sort,
            ],
            // Drives which price list the grid renders, and the pro banner.
            'audience' => $audience,
            'priceCeiling' => $this->priceCeiling($audience),
        ]);
    }

    /**
     * Upper bound for the price filter, rounded up to a clean step so the
     * slider's maximum always sits just past the dearest listed piece.
     */
    private function priceCeiling(string $audience): int
    {
        $max = (float) Product::storefront()
            ->whereIn('visibility', ['both', $audience])
            ->max('base_price');

        return max(100, (int) (ceil($max / 100) * 100));
    }

    public function show(string $slug)
    {
        $product = Product::storefront()
            ->where('slug', $slug)
            ->with([
                'store.city',
                'store.user',
                'category',
                'images',
                'videos',
                // Only sellable variants reach the selector — an inactive one
                // would render as a pickable option that the cart then refuses.
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('position'),
                'variants.options',
                'variants.media',
            ])
            ->withCount('wishlists')
            ->firstOrFail();

        $this->recordView($product->id);

        $inWishlist = Auth::check() ? Wishlist::where('user_id', Auth::id())->where('product_id', $product->id)->exists() : false;
        $inCart = Auth::check()
        ? CartItem::whereHas('cart', function ($q) {
            $q->where('user_id', Auth::id());
        })->where('product_id', $product->id)->exists()
        : false;

        return Inertia::render('buyer/products/show', [
            'product' => new BuyerProductResource($product),
            'inWishlist' => $inWishlist,
            'inCart' => $inCart,
            'moreFromStore' => BuyerProductResource::collection($this->moreFromStore($product)),
            'relatedProducts' => BuyerProductResource::collection($this->relatedProducts($product)),
        ]);
    }

    /**
     * Other pieces by the same artisan.
     *
     * @return Collection<int, Product>
     */
    private function moreFromStore(Product $product)
    {
        return Product::storefront()
            ->where('store_id', $product->store_id)
            ->where('id', '!=', $product->id)
            ->with(['images'])
            ->latest()
            ->limit(4)
            ->get();
    }

    /**
     * Fallback row for the bottom of the detail page: same category, different
     * shop. A store with a single product would otherwise end the page on an
     * empty "more from this shop" strip.
     *
     * @return Collection<int, Product>
     */
    private function relatedProducts(Product $product)
    {
        return Product::storefront()
            ->where('id', '!=', $product->id)
            ->where('store_id', '!=', $product->store_id)
            ->when(
                $product->category_id,
                fn ($q) => $q->where('category_id', $product->category_id),
            )
            ->with(['images', 'store'])
            ->latest()
            ->limit(4)
            ->get();
    }

    private function recordView(int $productId): void
    {
        $alreadyViewed = ProductView::where('product_id', $productId)
            ->where(function ($q) {
                $q->where('user_id', Auth::id())
                    ->orWhere('ip_address', request()->ip());
            })
            ->where('viewed_at', '>=', now()->subHour())
            ->exists();

        if (! $alreadyViewed) {
            ProductView::create([
                'product_id' => $productId,
                'user_id' => Auth::id(),
                'ip_address' => request()->ip(),
                'viewed_at' => now(),
            ]);
        }
    }
}
