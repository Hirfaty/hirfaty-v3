<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Services\BuyerAccountService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The buyer's saved pieces, at /wishlists.
 *
 * The listing itself is a placeholder for now; the `products` prop is already
 * the audience-aware, storefront-filtered set the cards will need.
 */
class WishlistController extends Controller
{
    public function __construct(private BuyerAccountService $account) {}

    public function __invoke(Request $request)
    {
        return Inertia::render('buyer/wishlists/index', [
            'products' => $this->account->wishlist($request->user(), $request),
        ]);
    }
}
