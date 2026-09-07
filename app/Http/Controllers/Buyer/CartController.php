<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Resources\CartLineResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Services\CartService;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CartController extends Controller
{
    public function __construct(private readonly CartService $cart) {}

    public function show(Request $request)
    {
        $cart = Cart::firstOrCreate(['user_id' => Auth::id()]);
        // `variant.media` too: a line shows the chosen variant's own photo when
        // the seller uploaded one, and without the eager load that is an extra
        // query per basket line.
        $cart->load([
            'items.product.images',
            'items.product.store',
            'items.variant.options',
            'items.variant.media',
        ]);

        return Inertia::render('buyer/cart', [
            // Priced through CartLineResource rather than handed over raw, so
            // the page cannot recompute a total the checkout disagrees with.
            'items' => CartLineResource::collection($cart->items)->resolve($request),
        ]);
    }

    public function add(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['integer', 'min:1'],
            // Existence/ownership/active checks live in CartService, which
            // needs the product anyway to decide whether a variant is even
            // required — keeping them there avoids two sources of truth.
            'variant_id' => ['nullable', 'integer'],
        ]);

        $cart = Cart::firstOrCreate(['user_id' => Auth::id()]);

        $this->cart->addLine(
            $cart,
            (int) $data['product_id'],
            isset($data['variant_id']) ? (int) $data['variant_id'] : null,
            (int) ($data['quantity'] ?? 1),
        );

        return back()->with('success', 'Added to cart.');
    }

    public function update(Request $request, CartItem $cartItem)
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        // Stop a quantity bump sailing past available stock: otherwise the
        // cart sits on an impossible quantity until checkout rejects the
        // entire basket, which reads as a broken checkout rather than as one
        // line being over-ordered. Also holds a trade line to its bulk minimum.
        $this->cart->assertQuantity($cartItem, (int) $data['quantity']);

        $cartItem->update(['quantity' => $data['quantity']]);

        return back();
    }

    public function remove(CartItem $cartItem)
    {
        $cartItem->delete();

        return back()->with('success', 'Item removed.');
    }

    public function merge(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $cart = Cart::firstOrCreate(['user_id' => Auth::id()]);

        foreach ($data['items'] as $item) {
            /*
             * A guest cart is unvalidated client state that may have sat in
             * localStorage for weeks — a variant can have been deactivated or
             * sold out meanwhile. Drop lines that no longer hold rather than
             * failing the whole merge, which would strand every other item
             * the buyer had collected.
             */
            try {
                $this->cart->addLine(
                    $cart,
                    (int) $item['product_id'],
                    isset($item['variant_id']) ? (int) $item['variant_id'] : null,
                    (int) $item['quantity'],
                );
            } catch (ValidationException) {
                continue;
            }
        }

        return back()->with('success', 'Cart merged successfully.');
    }
}
