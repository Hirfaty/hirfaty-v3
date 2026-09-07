<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Resources\BuyerProductResource;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * A shop's public page.
 *
 * The storefront had none: the admin panel and the product page both wanted to
 * link to one, and the only /seller/{slug} route that existed sits behind the
 * `role:seller` middleware — so following either link 403'd for the shoppers
 * the link was for. This is that missing page.
 *
 * Distinct from Seller\* controllers, which are the artisan's own back office.
 * Nothing here requires auth.
 */
class StoreController extends Controller
{
    public function show(Request $request, string $slug)
    {
        $store = Store::query()
            // A shop an admin has switched off should read as gone, not as an
            // empty shop — 404 rather than an eerie page with no products.
            ->where('status', 'active')
            ->where('slug', $slug)
            ->with(['city:id,name', 'category'])
            ->firstOrFail();

        $audience = Product::audienceFor($request->user());

        $products = Product::query()
            ->where('store_id', $store->id)
            ->storefront()
            /*
             * The same gate the listing and the product page apply. Without it
             * a shop page would be the one place a trade-only piece leaked to
             * consumers.
             */
            ->whereIn('visibility', ['both', $audience])
            ->with(['store.city', 'category', 'images'])
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('buyer/stores/show', [
            'store' => [
                'id' => $store->id,
                'slug' => $store->slug,
                'store_name' => $store->store_name,
                'description' => $store->description,
                'type' => $store->type,
                'logo' => $store->logo,
                'banner' => $store->banner,
                'city_name' => $store->city?->name,
                // `name` is a per-locale map on the model; only the resolved
                // string may reach the page.
                'category_name' => $store->category?->translated_name,
                'created_at' => $store->created_at,
            ],

            'products' => Inertia::merge(
                BuyerProductResource::collection($products->items())->resolve($request)
            ),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
                'has_more' => $products->hasMorePages(),
            ],

            // Drives which price list the grid renders, as on the listing.
            'audience' => $audience,
        ]);
    }
}
