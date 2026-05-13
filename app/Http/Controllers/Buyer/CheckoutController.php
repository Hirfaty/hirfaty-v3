<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\City;
use App\Services\CheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CheckoutController extends Controller
{
    public function show()
    {
        $cart = Cart::where('user_id', Auth::id())
            ->with(['items.product.images', 'items.product.store'])
            ->firstOrFail();

        if ($cart->items->isEmpty()) {
            return redirect()->route('products.index');
        }

        return Inertia::render('buyer/checkout', [
            'cart' => $cart,
            'cities' => City::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, CheckoutService $checkout)
    {

        $data = $request->validate([
            'shipping_address' => ['required', 'string', 'max:500'],
            'city_id' => ['required', 'exists:cities,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $cart = Cart::where('user_id', Auth::id())
            ->with(['items.product.store'])
            ->firstOrFail();

        if ($cart->items->isEmpty()) {
            return back()->withErrors(['cart' => 'Your cart is empty.']);
        }

        $orders = $checkout->process($cart, $data);

        return redirect()->route('products.index')
            ->with('success', count($orders).' commande(s) passée(s) avec succès !');

    }
}
