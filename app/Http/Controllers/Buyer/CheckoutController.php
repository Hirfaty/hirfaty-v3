<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\CheckoutRequest;
use App\Http\Resources\CartLineResource;
use App\Models\Cart;
use App\Models\City;
use App\Services\CheckoutDestinationService;
use App\Services\CheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CheckoutController extends Controller
{
    public function show(Request $request)
    {
        $cart = Cart::where('user_id', Auth::id())
            ->with([
                'items.product.images',
                'items.product.store',
                'items.variant.options',
                'items.variant.media',
            ])
            ->firstOrFail();

        if ($cart->items->isEmpty()) {
            return redirect()->route('products.index');
        }

        $user = $request->user();

        return Inertia::render('buyer/checkout', [
            /*
             * The same resource the basket uses, so the two screens cannot
             * quote different figures. This page used to read
             * `sale_price ?? base_price` off the product itself, which ignored
             * both variant overrides and the trade price list.
             */
            'items' => CartLineResource::collection($cart->items)->resolve($request),

            'cities' => City::orderBy('name')->get(['id', 'name']),

            // The buyer's address book, default first (User::addresses orders
            // on is_default), so the page can pre-select without re-sorting.
            'addresses' => $user->addresses()->with('city:id,name')->get()
                ->map(fn ($address) => [
                    'id' => $address->id,
                    'label' => $address->label,
                    'recipient_name' => $address->recipient_name,
                    'recipient_phone' => $address->recipient_phone,
                    'address_line' => $address->address_line,
                    'city_id' => $address->city_id,
                    'city_name' => $address->city?->name,
                    'is_default' => $address->is_default,
                ]),

            /*
             * Fallback for the phone field when the chosen address carries
             * none — an address saved before the number was mandatory, or a
             * brand-new one. Saves the buyer retyping what the account
             * already knows.
             */
            'accountPhone' => $user->phone_number,
        ]);
    }

    public function store(
        CheckoutRequest $request,
        CheckoutService $checkout,
        CheckoutDestinationService $destinations,
    ) {
        $cart = Cart::where('user_id', Auth::id())
            ->with(['items.product.store'])
            ->firstOrFail();

        if ($cart->items->isEmpty()) {
            return back()->withErrors(['cart' => __('buyer.checkout.err_empty')]);
        }

        // Where it ships, resolved from either the address book or the typed
        // form, before CheckoutService works out what it costs.
        $destination = $destinations->resolve($request->user(), $request->validated());

        $orders = $checkout->process($cart, $destination);

        return redirect()->route('buyer.account.orders.index')
            ->with('success', __('buyer.checkout.placed', ['count' => count($orders)]));
    }
}
