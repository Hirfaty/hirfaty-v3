<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Resources\BuyerProductResource;
use App\Models\Wishlist;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class WishlistController extends Controller
{
    public function index()
    {
        $wishlists = Wishlist::where('user_id', Auth::id())
            ->with(['product.store', 'product.category', 'product.images'])
            ->latest()
            ->get();

        $products = $wishlists->map(fn($w) => $w->product);

        return Inertia::render('buyer/account/wishlist', [
            'products' => BuyerProductResource::collection($products),
        ]);
    }
}
