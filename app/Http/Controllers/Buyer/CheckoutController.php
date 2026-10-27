<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\CheckoutRequest;
use App\Http\Resources\CartLineResource;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Services\BuyerAccountService;
use App\Services\BuyNowService;
use App\Services\CheckoutDestinationService;
use App\Services\CheckoutService;
use App\Services\CityService;
use App\Services\CurrencyService;
use App\Services\DeliveryFeeService;
use App\Services\PaymentService;
use App\Services\PayPalPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CheckoutController extends Controller
{
    public function show(
        Request $request,
        DeliveryFeeService $deliveryFees,
        CurrencyService $currencies,
        PayPalPaymentService $paypal,
    ) {
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

        return $this->page($request, $cart, false, $deliveryFees, $currencies, $paypal);
    }

    /**
     * "Buy now" on a product page: hold that one piece for checkout and go
     * there, leaving the cart alone. Refused with the same messages as adding
     * it to the cart, which the product page shows as a toast.
     */
    public function startBuyNow(Request $request, BuyNowService $buyNow)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'variant_id' => ['nullable', 'integer'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $buyNow->start(
            $request->session(),
            $request->user(),
            (int) $data['product_id'],
            isset($data['variant_id']) ? (int) $data['variant_id'] : null,
            (int) $data['quantity'],
        );

        return redirect()->route('checkout.buy_now');
    }

    /**
     * Checkout for the piece "Buy now" is holding — the same page as the
     * cart's, over a one-line cart that is never saved (BuyNowService).
     */
    public function buyNow(
        Request $request,
        BuyNowService $buyNow,
        DeliveryFeeService $deliveryFees,
        CurrencyService $currencies,
        PayPalPaymentService $paypal,
    ) {
        $cart = $buyNow->cart($request->session(), $request->user());

        // Nothing held, or the piece went off sale since: the cart is the
        // nearest sensible place.
        if (! $cart) {
            return redirect()->route('cart.show');
        }

        return $this->page($request, $cart, true, $deliveryFees, $currencies, $paypal);
    }

    /**
     * The checkout page, over either the buyer's cart or a buy-now line.
     */
    private function page(
        Request $request,
        Cart $cart,
        bool $buyNow,
        DeliveryFeeService $deliveryFees,
        CurrencyService $currencies,
        PayPalPaymentService $paypal,
    ) {
        $user = $request->user();

        /*
         * The carriers the buyer can check out with, plus each one's rate
         * card. Delivery is priced per Moroccan city and per foreign country —
         * abroad, by this basket's weight too — so the page needs the figures
         * to move the total the moment the destination changes; asking the
         * server on every change would put a round-trip between picking a
         * country and seeing what it costs. CheckoutService re-resolves the
         * same fee against the chosen service when the order is placed, so
         * what is here is a quote and never the bill.
         */
        $delivery = $deliveryFees->cards($cart->items);

        $methods = CheckoutRequest::paymentMethods();

        return Inertia::render('buyer/checkout', [
            /*
             * The same resource the basket uses, so the two screens cannot
             * quote different figures. This page used to read
             * `sale_price ?? base_price` off the product itself, which ignored
             * both variant overrides and the trade price list.
             */
            'items' => CartLineResource::collection($cart->items)->resolve($request),

            // Only the cities a rate card serves — the admin desk closes a
            // city by removing its rate (see DeliveryRateService).
            'cities' => app(CityService::class)->options($deliveryFees->deliverableCityIds()),

            // The buyer's address book, default first (User::addresses orders
            // on is_default), so the page can pre-select without re-sorting.
            // Addresses in a destination the card no longer serves are left
            // out: they could not be priced.
            'addresses' => $deliveryFees->scopeToDeliverable($user->addresses()->with('city:id,name,name_translations'))->get()
                ->map(fn ($address) => [
                    'id' => $address->id,
                    'label' => $address->label,
                    'recipient_name' => $address->recipient_name,
                    'recipient_phone' => $address->recipient_phone,
                    'address_line' => $address->address_line,
                    'city_id' => $address->city_id,
                    'city_name' => $address->city?->translated_name,
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
             * accept — card while Payzone is configured, PayPal while PayPal is.
             */
            'paymentMethods' => $methods,

            /*
             * What PayPal would charge this buyer, since it cannot take MAD:
             * the currency they are reading when PayPal takes it, else EUR,
             * with the rate — the page states the figure before they choose.
             * The same resolution prices the attempt (PayPalPaymentService).
             */
            'paypalCharge' => in_array(Order::PAYMENT_PAYPAL, $methods, true)
                ? $paypal->quoteFor($currencies->resolve($request))
                : null,

            'defaultCountry' => Address::DEFAULT_COUNTRY,

            /*
             * `services` is ordered by sort_order — the order the page offers
             * them in — while `rates` is keyed by slug, so the two do not line
             * up by position.
             */
            'deliveryServices' => $delivery['services'],
            'deliveryRates' => $delivery['rates'],

            /*
             * Checking out one piece straight from its page rather than the
             * cart: the form says so when it posts, and "back" leads to the
             * product instead of a basket that does not hold it.
             */
            'buyNow' => $buyNow ? [
                'product_slug' => $cart->items->first()?->product?->slug,
            ] : null,
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
        BuyNowService $buyNow,
    ) {
        /*
         * A buy-now checkout bills the piece held in the session, never the
         * cart — whatever else the basket holds stays there for later.
         */
        $buyNowCart = $request->boolean('buy_now')
            ? $buyNow->cart($request->session(), $request->user())
            : null;

        if ($request->boolean('buy_now') && ! $buyNowCart) {
            return back()->withErrors(['cart' => __('buyer.checkout.err_buy_now_gone')]);
        }

        $cart = $buyNowCart ?? Cart::where('user_id', Auth::id())
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

        // Card or PayPal — validated against what is on offer right now.
        $destination['payment_method'] = $request->validated('payment_method');

        // The carrier picked on the page, if any — CheckoutService checks it
        // ships to the destination.
        $destination['delivery_service_id'] = $request->validated('delivery_service_id');

        // An online purchase this buyer left unpaid is still holding its
        // stock; this checkout replaces it.
        $payments->releaseOpenPurchases($request->user());

        $orders = $checkout->process($cart, $destination);

        if ($buyNowCart) {
            $buyNow->placed($request->session(), $buyNowCart, $orders[0]->group_reference);
        }

        /*
         * Off to Payzone or PayPal — every purchase is paid online before it
         * is placed. A full page visit rather than an Inertia redirect: the
         * launch route answers with a self-posting form (Payzone) or a
         * redirect to paypal.com, neither of which an XHR could follow. The
         * confirmation page comes after the payment.
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
