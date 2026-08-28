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
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function __construct(private ProductScoringService $scoring) {}

    public function index(Request $request)
    {
        $query = Product::query()
            ->where('status', 'published')
            ->with(['store', 'category', 'images'])
            ->withCount(['wishlists', 'orderItems']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('store', fn ($q) => $q->where('store_name', 'like', "%{$search}%"));
            });
        }

        if ($catId = $request->input('category_id')) {
            $query->where('category_id', $catId);
        }

        if ($max = $request->input('max_price')) {
            $query->where('base_price', '<=', $max);
        }

        if ($min = $request->input('min_rating')) {
            $query->where('average_rating', '>=', $min);
        }

        $sort = $request->input('sort', 'score');
        $query = match ($sort) {
            'latest' => $query->latest(),
            'price_asc' => $query->orderBy('base_price', 'asc'),
            'price_desc' => $query->orderBy('base_price', 'desc'),
            'rating' => $query->orderByDesc('average_rating'),
            default => $this->scoring->apply($query),
        };

        return Inertia::render('buyer/products/index', [
            'products' => BuyerProductResource::collection(
                $query->paginate(12)->withQueryString()
            ),
            'categories' => Category::whereNull('parent_id')->get(),
            'filters' => $request->only(['search', 'category_id', 'max_price', 'min_rating', 'sort']),
        ]);
    }

    public function show(string $slug)
    {
        $product = Product::where('slug', $slug)
            ->where('status', 'published')
            ->with([
                'store.city',
                'store.user',
                'category',
                'images',
                'videos',
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
        // Other products from the same store
        $moreFromStore = Product::where('store_id', $product->store_id)
            ->where('id', '!=', $product->id)
            ->where('status', 'published')
            ->with(['images'])
            ->limit(4)
            ->get();

        return Inertia::render('buyer/products/show', [
            'product' => new BuyerProductResource($product),
            'inWishlist' => $inWishlist,
            'inCart' => $inCart,
            'moreFromStore' => BuyerProductResource::collection($moreFromStore),
        ]);
    }

    public function toggleWishlist(Product $product)
    {
        return $this->scoring->toggleWishlist($product);
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
