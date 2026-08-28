<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Resources\BuyerCartResource;
use App\Models\Cart;
use App\Models\CartItem;
use Auth;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CartController extends Controller
{
    public function show()
    {
        $cart = Cart::firstOrCreate(['user_id' => Auth::id()]);
        $cart->load(['items.product.images', 'items.product.store.city']);

        return Inertia::render('buyer/cart', [
            'cart' => new BuyerCartResource($cart),
        ]);
    }

    public function add(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['integer', 'min:1'],
        ]);

        $cart = Cart::firstOrCreate(['user_id' => Auth::id()]);

        $item = $cart->items()->where('product_id', $data['product_id'])->first();

        if ($item) {
            $item->increment('quantity', $data['quantity'] ?? 1);
        } else {
            $cart->items()->create([
                'product_id' => $data['product_id'],
                'quantity' => $data['quantity'] ?? 1,
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
        CartItem::destroy($cartItem->getKey());

        return back()->with('success', 'Item removed.');
    }

    public function merge(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $cart = Cart::firstOrCreate(['user_id' => Auth::id()]);
        foreach ($data['items'] as $item) {
            $existing = $cart->items()->where('product_id', $item['product_id'])->first();

            if ($existing) {
                // Add guest quantity on top of existing DB quantity
                $existing->increment('quantity', $item['quantity']);
            } else {
                $cart->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }
        }

        return back()->with('success', 'Cart merged successfully.');
    }
}
