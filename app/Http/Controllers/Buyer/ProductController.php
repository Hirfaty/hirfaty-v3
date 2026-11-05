<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Resources\BuyerProductResource;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductView;
use App\Models\Wishlist;
use App\Services\CategoryService;
use App\Services\NewArrivalsService;
use App\Services\ProductDetailService;
use App\Services\ProductScoringService;
use App\Services\ProductSearchService;
use App\Services\ReviewService;
use App\Services\SeoService;
use App\Services\WishlistService;
use Auth;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function __construct(
        private ProductScoringService $scoring,
        private WishlistService $wishlist,
        private ProductSearchService $search,
        private ReviewService $reviews,
        private SeoService $seo,
        private ProductDetailService $details,
        private CategoryService $categories,
    ) {}

    public function index(Request $request)
    {
        $audience = Product::audienceFor($request->user());

        $query = Product::query()
            ->storefront()
            // A product may be listed for consumers, for the trade, or both.
            // Without this the storefront showed wholesale-only items to
            // guests — `visibility` existed but was never enforced.
            ->whereIn('visibility', ['both', $audience])
            // `variants.media` lets BuyerProductResource fold each variant's
            // own photography into `gallery_images`, so the grid's hover
            // gallery cycles a variant product's full set of photos rather
            // than only the ones attached to the product itself.
            ->with(['store.city', 'category', 'images', 'variants.media'])
            ->withCount(['wishlists', 'orderItems']);

        if ($search = trim((string) $request->input('search'))) {
            // Shared with the header's live suggestions, so the panel and the
            // listing it hands off to can never disagree on what matches.
            $this->search->applyTerm($query, $search);
        }

        $activeCategory = null;

        if ($catId = $request->input('category_id')) {
            $activeCategory = $this->categories->filterListing($query, (int) $catId);
        }

        $paginator = $this->scoring->apply($query)->paginate(12)->withQueryString();

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
            // Drives the filter rail beside the grid. A full tree rather than
            // top-level pills: shoppers filter by a craft or by one of its
            // subcategories, and the rail renders each parent's `children` as
            // the nested set. `translated_name` is appended by the model
            // accessor, since `name` is a per-locale object that must never
            // be rendered raw; the same shape (minus `image_url`) as the
            // header's `navCategories`.
            'categories' => Category::roots()
                ->ordered()
                ->with('children')
                ->get()
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'slug' => $category->slug,
                    'translated_name' => $category->translated_name,
                    'children' => $category->children
                        ->map(fn (Category $child) => [
                            'id' => $child->id,
                            'slug' => $child->slug,
                            'translated_name' => $child->translated_name,
                        ])
                        ->all(),
                ]),
            'filters' => [
                'search' => $request->input('search', ''),
                'category_id' => $request->input('category_id', ''),
            ],
            // Drives which price list the grid renders.
            'audience' => $audience,
            /*
             * Which hearts start filled. Scoped to the page just rendered
             * rather than the buyer's whole list, so a long-standing wishlist
             * doesn't grow the payload of every visit.
             *
             * Not merged like `products`: "load more" re-requests only
             * `products` and `pagination`, so this would go stale — hence the
             * `only:` list on the client stays as it is and appended tiles
             * fall back to unfilled until the next full visit.
             */
            'wishlisted' => $this->wishlist->idsFor($request->user(), $paginator->items()),
            // The wishlist route sits behind `role:buyer`; anyone else is sent
            // to log in rather than issuing a request that would 403.
            'canWishlist' => (bool) $request->user()?->isBuyer(),
        ])->withViewData('seo', fn () => $this->seo->forCatalogue($activeCategory));
    }

    /**
     * The newest pieces in the catalogue, on their own page.
     *
     * Separate from index() rather than an ordering on it: the listing no
     * longer offers a sort control, and this is a destination in the nav with
     * copy of its own — not a state of the marketplace page.
     */
    public function newArrivals(Request $request, NewArrivalsService $newArrivals)
    {
        $paginator = $newArrivals->paginate($request->user());

        return Inertia::render('buyer/products/new', [
            // Merged rather than replaced so "load more" appends a page
            // instead of swapping the grid out — same contract as index().
            'products' => Inertia::merge(
                BuyerProductResource::collection($paginator->items())->resolve($request)
            ),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'has_more' => $paginator->hasMorePages(),
            ],
            'audience' => Product::audienceFor($request->user()),
            'wishlisted' => $this->wishlist->idsFor($request->user(), $paginator->items()),
            'canWishlist' => (bool) $request->user()?->isBuyer(),
        ]);
    }

    public function show(Request $request, string $slug)
    {
        $audience = Product::audienceFor($request->user());

        $product = Product::storefront()
            ->where('slug', $slug)
            // The same gate the listing applies. Without it `visibility` was
            // only enforced on the grid: a trade-only piece stayed reachable
            // by its slug, so a consumer could open it and add it to the cart
            // through a link, a search engine or a browser's history.
            ->whereIn('visibility', ['both', $audience])
            ->with($this->details->relations())
            ->withCount('wishlists')
            ->firstOrFail();

        $this->recordView($product->id);

        $inWishlist = Auth::check() ? Wishlist::where('user_id', Auth::id())->where('product_id', $product->id)->exists() : false;
        $inCart = Auth::check()
        ? CartItem::whereHas('cart', function ($q) {
            $q->where('user_id', Auth::id());
        })->where('product_id', $product->id)->exists()
        : false;

        $reviews = $this->reviews->paginateFor($product);

        return Inertia::render('buyer/products/show', [
            ...$this->details->props($product, $request),
            'inWishlist' => $inWishlist,
            'inCart' => $inCart,
            'myReview' => $this->reviews->stateForProduct($request->user(), $product),
        ])->withViewData('seo', fn () => $this->seo->forProduct(
            $product,
            $this->reviews->summaryFor($product),
            $reviews->items(),
        ));
    }

    /**
     * Add or remove a product from the signed-in buyer's wishlist.
     *
     * The `products.wishlist` route has pointed here since it was added, but
     * the method itself was never written — so the heart on the product page
     * threw on every click and no wishlist could ever be populated.
     *
     * firstOrCreate/delete rather than a blind create: the button is a toggle,
     * and wishlists carries a (user_id, product_id) unique index that a second
     * click would otherwise trip.
     */
    public function toggleWishlist(Product $product)
    {
        $existing = Wishlist::where('user_id', Auth::id())
            ->where('product_id', $product->id)
            ->first();

        if ($existing) {
            $existing->delete();

            return back()->with('success', 'Retiré de vos coups de cœur.');
        }

        Wishlist::create([
            'user_id' => Auth::id(),
            'product_id' => $product->id,
        ]);

        return back()->with('success', 'Ajouté à vos coups de cœur.');
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
