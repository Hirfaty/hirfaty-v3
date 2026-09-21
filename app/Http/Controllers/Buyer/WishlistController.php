<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\BuyerAccountService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The buyer's saved pieces, at /wishlists.
 *
 * `products` is the audience-aware, storefront-filtered set the cards render
 * (see BuyerAccountService::wishlist()); the route sits behind `role:buyer`,
 * so `canWishlist` is always true and every row starts wishlisted by
 * definition.
 */
class WishlistController extends Controller
{
    public function __construct(private BuyerAccountService $account) {}

    public function __invoke(Request $request)
    {
        return Inertia::render('buyer/wishlists/index', [
            'products' => $this->account->wishlist($request->user(), $request),
            'audience' => Product::audienceFor($request->user()),
            'canWishlist' => true,
        ]);
    }
}
