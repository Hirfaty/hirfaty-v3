<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Auth;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CartController extends Controller
{
    public function show()
    {
        $cart = Cart::firstOrCreate(['user_id' => Auth::id()]);
        $cart->load(['items.product.images', 'items.product.store']);

        return Inertia::render('buyer/cart', ['cart' => $cart]);
    }

    public function add(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity'   => ['integer', 'min:1'],
        ]);

        $cart = Cart::firstOrCreate(['user_id' => Auth::id()]);

        $item = $cart->items()->where('product_id', $data['product_id'])->first();

        if ($item) {
            $item->increment('quantity', $data['quantity'] ?? 1);
        } else {
            $cart->items()->create([
                'product_id' => $data['product_id'],
                'quantity'   => $data['quantity'] ?? 1,
            ]);
        }

        return back()->with('success', 'Added to cart.');
    }

    public function update(Request $request, CartItem $cartItem)
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $cartItem->update(['quantity' => $data['quantity']]);

        return back();
    }

    public function remove(CartItem $cartItem)
    {
        $cartItem->delete();

        return back()->with('success', 'Item removed.');
    }
}