<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\CheckoutRequest;
use App\Http\Resources\CartLineResource;
use App\Models\Address;
use App\Models\Cart;
use App\Models\City;
use App\Services\BuyerAccountService;
use App\Services\CheckoutDestinationService;
use App\Services\CheckoutService;
use App\Services\CurrencyService;
use App\Services\DeliveryFeeService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CheckoutController extends Controller
{
    public function show(Request $request, DeliveryFeeService $deliveryFees)
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

        /*
         * The carriers the buyer can check out with, plus each one's rate
         * card. Delivery is priced per Moroccan city and per foreign country,
         * so the page needs the figures to move the total the moment the
         * destination changes — asking the server on every change would put a
         * round-trip between picking a city and seeing what it costs.
         * CheckoutService re-resolves the same fee against the chosen service
         * when the order is placed, so what is here is a quote and never the
         * bill.
         */
        $delivery = $deliveryFees->cards();

        return Inertia::render('buyer/checkout', [
            /*
             * The same resource the basket uses, so the two screens cannot
             * quote different figures. This page used to read
             * `sale_price ?? base_price` off the product itself, which ignored
             * both variant overrides and the trade price list.
             */
            'items' => CartLineResource::collection($cart->items)->resolve($request),

            // Only the cities the rate card serves — the admin desk closes a
            // city by removing its rate (see DeliveryRateService).
            'cities' => City::orderBy('name')
                ->when(
                    ($cityIds = $deliveryFees->deliverableCityIds()) !== null,
                    fn ($query) => $query->whereIn('id', $cityIds),
                )
                ->get(['id', 'name']),

            // The buyer's address book, default first (User::addresses orders
            // on is_default), so the page can pre-select without re-sorting.
            // Addresses in a destination the card no longer serves are left
            // out: they could not be priced.
            'addresses' => $deliveryFees->scopeToDeliverable($user->addresses()->with('city:id,name'))->get()
                ->map(fn ($address) => [
                    'id' => $address->id,
                    'label' => $address->label,
                    'recipient_name' => $address->recipient_name,
                    'recipient_phone' => $address->recipient_phone,
                    'address_line' => $address->address_line,
                    'city_id' => $address->city_id,
                    'city_name' => $address->city?->name,
                    'postal_code' => $address->postal_code,
                    'country' => $address->country,
                    'is_default' => $address->is_default,
                ]),

            /*
             * What the account already knows, so the form opens part-filled.
             * Every field here is nullable: signup collects only a password now
             * (RegistrationCodeService::complete), so a buyer can reach
             * checkout with nothing but an email — the page treats these as
             * blanks to fill rather than values to display.
             *
             * `email` is along for display only. It is the buyer's sign-in
             * identity, locked on /account for the same reason; letting
             * checkout edit it would either do nothing or change how they log
             * in.
             */
            'customer' => [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone_number,
            ],

            /*
             * Whitelisted server-side in CheckoutRequest and shared from there,
             * so the page can only offer a method the server will actually
             * accept — card only while Payzone is configured.
             */
            'paymentMethods' => CheckoutRequest::paymentMethods(),

            'defaultCountry' => Address::DEFAULT_COUNTRY,

            /*
             * `services` is ordered by sort_order — the order the page offers
             * them in — while `rates` is keyed by slug, so the two do not line
             * up by position.
             */
            'deliveryServices' => $delivery['services'],
            'deliveryRates' => $delivery['rates'],
        ]);
    }

    /**
     * "Apply" on the discount code field.
     *
     * A deliberate stub. There is no coupon table, model or redemption rule in
     * this app yet, and inventing one here would mean deciding — silently and
     * in a controller — how a basket-wide discount is split across the several
     * per-store orders CheckoutService creates, and which party absorbs it in
     * each seller's Earning row. That is a pricing decision, not a checkout
     * one.
     *
     * So the field is real, reachable and honest about rejecting everything,
     * rather than absent from a design that calls for it or, worse, wired to
     * something that appears to discount and does not.
     */
    public function applyCoupon(Request $request)
    {
        $request->validate(['code' => ['required', 'string', 'max:40']]);

        /*
         * Into its own error bag, not the shared one: the checkout form lives
         * on the same page, and an unknown code must not light up as a
         * validation failure against the delivery fields the buyer has already
         * filled in correctly.
         */
        return back()->withErrors(
            ['code' => __('buyer.checkout.err_coupon_unknown')],
            'coupon',
        );
    }

    public function store(
        CheckoutRequest $request,
        CheckoutService $checkout,
        CheckoutDestinationService $destinations,
        CurrencyService $currencies,
        PaymentService $payments,
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

        /*
         * What the buyer was reading while they filled this in. Recorded on
         * the order so the desk can answer "they say they saw €38" months
         * later, once the rate has moved.
         *
         * Resolved here rather than trusted from the form: a currency posted
         * by the client would let anyone claim any rate, and this is written
         * to the order as a fact about what we showed.
         */
        $currency = $currencies->resolve($request);
        $destination['display_currency'] = $currency;
        // The raw multiplier, not `convert(1.0, ...)` — that would round the
        // rate to the currency's display decimals and skew everything derived
        // from it.
        $destination['display_rate'] = $currencies->rateFor($currency);

        // A card purchase this buyer left unpaid is still holding its stock;
        // this checkout replaces it.
        $payments->releaseOpenPurchases($request->user());

        $orders = $checkout->process($cart, $destination);

        /*
         * Off to Payzone — every purchase is paid by card before it is placed.
         * A full page visit rather than an Inertia redirect: the launch page
         * is a plain HTML form that posts itself to the paywall, which an XHR
         * could not follow. The confirmation page comes after the payment.
         */
        return Inertia::location(route('checkout.pay', $orders[0]->group_reference));
    }

    /**
     * The page a buyer lands on once checkout goes through.
     *
     * A real page rather than a one-shot flash: it survives a reload, and the
     * link in a browser's history still shows what was bought. Scoped to the
     * buyer by BuyerAccountService, so another buyer's reference 404s.
     */
    public function confirmation(Request $request, string $purchase, BuyerAccountService $account)
    {
        $shaped = $account->purchase($request->user(), $purchase) ?? abort(404);

        return Inertia::render('buyer/checkout-confirmation', [
            'purchase' => $shaped,
            'email' => $request->user()->email,
        ]);
    }
}
