<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Services\ProductScoringService;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function __construct(private ProductScoringService $scoring) {

    }

    public function index(Request $request)
    {
        $query = Product::query()
                ->where('status', 'published')
                ->with(['store', 'category', 'images'])
                ->withCount(['wishlists', 'orderItems']);

        //Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('store', fn($q) => $q->where('store_name', 'like', "%{$search}%"));
            });
        }

        //Filter
        if ($category = $request->input('category_id')) {
            $query->where('category_id', $category);
        }

        if ($min = $request->input('min_price')) {
            $query->where('base_price', '>=', $min);
        }

        if ($max = $request->input('max_price')) {
            $query->where('base_price', '<=', $max);
        }

        //Sort
        // ─── Sorting ─────────────────────────────────────────
        $sort = $request->input('sort', 'score'); // default: score

        $query = match($sort) {
            'latest'     => $query->latest(),
            'price_asc'  => $query->orderBy('base_price', 'asc'),
            'price_desc' => $query->orderBy('base_price', 'desc'),
            'rating'     => $query->orderByDesc('average_rating'),
            default      => $this->scoring->apply($query), // score
        };

        $products = $query->paginate(24)->withQueryString();

        return Inertia::render('buyer/products/index', [
            'products' => $products,
            'filters'  => $request->only(['search', 'category_id', 'city_id', 'sort', 'min_price', 'max_price', 'on_sale']),
        ]);
    }
}
