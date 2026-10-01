<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * A seller's own catalogue, read-only.
 *
 * Sellers no longer create or change listings — Hirfaty's own team files
 * every product on their behalf through Admin\ProductController, after
 * contacting the artisan directly. What is left here is the shop's view of
 * what it has on sale: what is published, what is out of stock, how a piece
 * is performing.
 *
 * The create/edit/destroy actions (and the `store.sellable` gate that used to
 * guard them) were removed rather than hidden, so there is no seller-facing
 * route into the catalogue that a crafted request could still reach.
 */
class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->store) {
            abort(403, 'Seller store not found.');
        }

        $products = $user
            ->store
            ->products()
            ->with(['category', 'images'])
            ->latest()
            ->paginate(20);

        return Inertia::render('seller/products/index', [
            'products' => $products,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        $this->authorizeProduct($product);

        $product->load(['media', 'category']);

        return Inertia::render('seller/products/show', [
            'product' => $product,
            'stats' => [
                'total_wishlists' => $product->wishlists()->count(),
                'total_orders' => $product->orderItems()->sum('quantity'),
            ],
        ]);
    }

    // ─── Private helpers ─────────────────────────────────────────

    private function authorizeProduct(Product $product): void
    {
        // Make sure this product belongs to the logged-in seller's store
        $user = Auth::user();
        if (! $user || ! $user->store || $product->store_id !== $user->store->id) {
            abort(403);
        }
    }
}
