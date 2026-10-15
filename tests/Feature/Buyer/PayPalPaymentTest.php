<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\City;
use App\Models\Earning;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Notifications\Admin\CardRefundNeeded;
use App\Notifications\NewPurchaseForAdmin;
use App\Notifications\OrderPlaced;
use App\Services\CurrencyService;
use App\Services\OrderStatusService;
use App\Services\PaymentService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

const PAYPAL_API = 'https://api-m.sandbox.paypal.com';
const PAYPAL_ORDER_ID = '5O190127TN364715T';
const PAYPAL_CAPTURE_ID = '3C679366HH908993F';

beforeEach(function () {
    config([
        'services.paypal.mode' => 'sandbox',
        'services.paypal.client_id' => 'test-client',
        'services.paypal.client_secret' => 'test-secret',
        'services.paypal.webhook_id' => 'WH-TEST',
        'services.paypal.currency' => 'EUR',
        'services.payzone.expire_after_minutes' => 60,
        'hirfaty.admin_email' => 'desk@hirfaty.test',
    ]);

    // Nothing in this file may reach PayPal for real.
    Http::preventStrayRequests();
});

/**
 * PayPal's API as these tests need it. `$capture` is the capture's status
 * (COMPLETED, PENDING, DECLINED) or a PayPal error issue
 * (INSTRUMENT_DECLINED…), answered with a 422.
 */
function fakePayPal(string $capture = 'COMPLETED', string $verification = 'SUCCESS'): void
{
    Http::fake(function (HttpRequest $request) use ($capture, $verification) {
        $path = parse_url($request->url(), PHP_URL_PATH);

        if ($path === '/v1/oauth2/token') {
            return Http::response(['access_token' => 'A21-test-token', 'expires_in' => 32400]);
        }

        if ($path === '/v2/checkout/orders' && $request->method() === 'POST') {
            return Http::response([
                'id' => PAYPAL_ORDER_ID,
                'status' => 'PAYER_ACTION_REQUIRED',
                'links' => [
                    ['rel' => 'self', 'href' => PAYPAL_API.'/v2/checkout/orders/'.PAYPAL_ORDER_ID, 'method' => 'GET'],
                    ['rel' => 'payer-action', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token='.PAYPAL_ORDER_ID, 'method' => 'GET'],
                ],
            ], 200);
        }

        if ($path === '/v2/checkout/orders/'.PAYPAL_ORDER_ID.'/capture') {
            if (! in_array($capture, ['COMPLETED', 'PENDING', 'DECLINED'], true)) {
                return Http::response([
                    'name' => 'UNPROCESSABLE_ENTITY',
                    'details' => [['issue' => $capture]],
                    'message' => 'The requested action could not be performed.',
                ], 422);
            }

            return Http::response(paypalCapturedOrder($capture));
        }

        if ($path === '/v1/notifications/verify-webhook-signature') {
            return Http::response(['verification_status' => $verification]);
        }

        return Http::response(['name' => 'NOT_FOUND'], 404);
    });
}

/** The order PayPal returns from a capture, for the latest attempt. */
function paypalCapturedOrder(string $status = 'COMPLETED'): array
{
    $payment = Payment::where('provider', 'paypal')->latest('id')->firstOrFail();

    return [
        'id' => PAYPAL_ORDER_ID,
        'status' => $status === 'COMPLETED' ? 'COMPLETED' : 'APPROVED',
        'payment_source' => ['paypal' => ['email_address' => 'buyer@example.com']],
        'purchase_units' => [[
            'reference_id' => $payment->group_reference,
            'payments' => ['captures' => [paypalCapture($payment, $status)]],
        ]],
    ];
}

function paypalCapture(Payment $payment, string $status = 'COMPLETED', ?float $amount = null): array
{
    return array_filter([
        'id' => PAYPAL_CAPTURE_ID,
        'status' => $status,
        'status_details' => $status === 'PENDING' ? ['reason' => 'PENDING_REVIEW'] : null,
        'amount' => [
            'currency_code' => $payment->currency,
            'value' => number_format($amount ?? (float) $payment->amount, 2, '.', ''),
        ],
        'custom_id' => $payment->charge_id,
        'invoice_id' => $payment->charge_id,
        'supplementary_data' => ['related_ids' => ['order_id' => PAYPAL_ORDER_ID]],
    ]);
}

/**
 * A buyer with one 200 MAD piece (stock 1) in their cart, and the checkout
 * fields that ship it to Casablanca and pay with PayPal. Delivery falls back
 * to 40 MAD, so the purchase comes to 240 MAD — 22.08 EUR at the fallback
 * rate (0.092).
 *
 * @return array{buyer: User, product: Product, payload: array<string, mixed>}
 */
function paypalBasket(): array
{
    $buyer = User::factory()->create(['role' => 'buyer', 'email_verified_at' => now()]);
    $city = City::create(['name' => 'Casablanca', 'region' => 'Casablanca-Settat']);

    $seller = User::factory()->create(['role' => 'seller', 'email_verified_at' => now()]);
    $store = Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Atelier Zellige',
        'slug' => 'atelier-zellige-'.Str::random(6),
        'status' => 'active',
    ]);

    $product = Product::create([
        'store_id' => $store->id,
        'name' => 'Tajine',
        'slug' => 'tajine-'.Str::random(6),
        'base_price' => 200,
        'stock' => 1,
        'status' => 'published',
    ]);

    $cart = Cart::create(['user_id' => $buyer->id]);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

    return [
        'buyer' => $buyer,
        'product' => $product,
        'payload' => [
            'first_name' => 'Yasmine',
            'last_name' => 'Alaoui',
            'shipping_phone' => '0600000000',
            'shipping_address' => '12 Rue Ibn Battouta',
            'city_id' => $city->id,
            'postal_code' => '20000',
            'country' => 'MA',
            'payment_method' => 'paypal',
        ],
    ];
}

/** Check out with PayPal, go to PayPal, and return the attempt it opened. */
function paypalLaunch($test, array $basket): Payment
{
    $test->actingAs($basket['buyer'])
        ->post(route('checkout.store'), $basket['payload'])
        ->assertRedirect();

    $reference = Order::withoutGlobalScope(Order::SETTLED_SCOPE)->latest('id')->value('group_reference');

    $test->actingAs($basket['buyer'])
        ->get(route('checkout.pay', $reference))
        ->assertRedirect('https://www.sandbox.paypal.com/checkoutnow?token='.PAYPAL_ORDER_ID);

    return Payment::where('group_reference', $reference)->latest('id')->firstOrFail();
}

/** The buyer's browser back from PayPal, having approved. */
function paypalReturn($test, Payment $payment): TestResponse
{
    return $test->get(route('payments.paypal.return', [
        $payment->charge_id,
        'token' => PAYPAL_ORDER_ID,
        'PayerID' => 'FSMVU44LF3YUS',
    ]));
}

/** A webhook delivery, with the headers PayPal signs it with. */
function paypalWebhook($test, string $type, array $resource, array $headers = []): TestResponse
{
    $body = json_encode([
        'id' => 'WH-'.Str::random(12),
        'event_type' => $type,
        'resource_type' => str_starts_with($type, 'CHECKOUT.') ? 'checkout-order' : 'capture',
        'resource' => $resource,
    ], JSON_UNESCAPED_SLASHES);

    return $test->call('POST', route('payments.paypal.webhook'), server: array_merge([
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PAYPAL_AUTH_ALGO' => 'SHA256withRSA',
        'HTTP_PAYPAL_CERT_URL' => 'https://api.sandbox.paypal.com/v1/notifications/certs/CERT-360caa42',
        'HTTP_PAYPAL_TRANSMISSION_ID' => 'dfb3be50-fd74-11e8-8c3c-4ba6',
        'HTTP_PAYPAL_TRANSMISSION_SIG' => 'thy4/U002quzxFavHPwbfJGcc46E8rc5jzgyeafWm5mICTBdY',
        'HTTP_PAYPAL_TRANSMISSION_TIME' => now()->toIso8601String(),
    ], $headers), content: $body);
}

function paypalOrders(string $reference)
{
    return Order::withoutGlobalScope(Order::SETTLED_SCOPE)->where('group_reference', $reference)->get();
}

test('checkout offers PayPal beside card once it is configured, with what PayPal will charge', function () {
    $basket = paypalBasket();

    $this->actingAs($basket['buyer'])->get(route('checkout.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('paymentMethods', ['card', 'paypal'])
            // Reading dirhams, which PayPal cannot take: charged in euros.
            ->where('paypalCharge.currency', 'EUR')
            ->where('paypalCharge.rate', 0.092));

    config(['services.paypal.client_secret' => null]);

    $this->actingAs($basket['buyer'])->get(route('checkout.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('paymentMethods', ['card'])
            ->where('paypalCharge', null));

    $this->actingAs($basket['buyer'])
        ->post(route('checkout.store'), $basket['payload'])
        ->assertSessionHasErrors('payment_method');
});

test('a PayPal checkout holds the stock and sends the buyer to approve an order priced in euros', function () {
    Notification::fake();
    fakePayPal();
    $basket = paypalBasket();

    $payment = paypalLaunch($this, $basket);
    $order = paypalOrders($payment->group_reference)->sole();

    expect($order->payment_method)->toBe('paypal')
        ->and($order->payment_status)->toBe('pending')
        ->and($basket['product']->fresh()->stock)->toBe(0)
        // Hidden like any unpaid online purchase.
        ->and(Order::count())->toBe(0)
        ->and($payment->provider)->toBe('paypal')
        ->and($payment->currency)->toBe('EUR')
        ->and((float) $payment->amount)->toBe(22.08)
        ->and((float) $payment->exchange_rate)->toBe(0.092)
        ->and($payment->provider_order_id)->toBe(PAYPAL_ORDER_ID)
        ->and($payment->status)->toBe('pending');

    Http::assertSent(function (HttpRequest $request) use ($payment) {
        if ($request->url() !== PAYPAL_API.'/v2/checkout/orders') {
            return false;
        }

        $unit = $request['purchase_units'][0];
        $context = $request['payment_source']['paypal']['experience_context'];

        return $request['intent'] === 'CAPTURE'
            && $request->header('Authorization')[0] === 'Bearer A21-test-token'
            && $unit['amount'] === ['currency_code' => 'EUR', 'value' => '22.08']
            && $unit['custom_id'] === $payment->charge_id
            && $unit['reference_id'] === $payment->group_reference
            && $context['shipping_preference'] === 'NO_SHIPPING'
            && str_contains($context['return_url'], $payment->charge_id)
            && str_contains($context['cancel_url'], 'outcome=cancel');
    });

    Notification::assertNothingSent();
});

test('a buyer reading a currency PayPal takes is charged in it, at the rate quoted at checkout', function () {
    fakePayPal();
    $basket = paypalBasket();

    $this->withSession([CurrencyService::SESSION_KEY => 'USD']);
    $payment = paypalLaunch($this, $basket);

    expect($payment->currency)->toBe('USD')
        ->and((float) $payment->amount)->toBe(23.76)
        ->and((float) $payment->exchange_rate)->toBe(0.099);
});

test('returning from PayPal captures the money and places the purchase exactly once', function () {
    Notification::fake();
    fakePayPal();
    $basket = paypalBasket();
    $payment = paypalLaunch($this, $basket);
    $reference = $payment->group_reference;

    paypalReturn($this, $payment)
        ->assertRedirect(route('checkout.payment', [$reference, 'outcome' => 'success']));

    // PayPal's own word on the same capture, arriving afterwards, changes nothing.
    paypalWebhook($this, 'PAYMENT.CAPTURE.COMPLETED', paypalCapture($payment->fresh()))->assertOk();

    $order = Order::sole();
    expect($order->payment_status)->toBe('paid')
        ->and($order->status)->toBe('confirmed')
        ->and($order->statusEvents()->pluck('status')->all())->toBe(['pending', 'confirmed'])
        ->and($payment->fresh()->status)->toBe('paid')
        ->and($payment->fresh()->provider_transaction_id)->toBe(PAYPAL_CAPTURE_ID)
        ->and(Earning::where('order_id', $order->id)->count())->toBe(1)
        ->and(CartItem::count())->toBe(0);

    Notification::assertSentToTimes($basket['buyer'], OrderPlaced::class, 1);
    Notification::assertSentOnDemandTimes(NewPurchaseForAdmin::class, 1);

    // Both emails say it was PayPal, not a card.
    Notification::assertSentTo($basket['buyer'], OrderPlaced::class, fn (OrderPlaced $n) => str_contains(
        (string) $n->toMail($basket['buyer'])->render(), 'PayPal'));
    Notification::assertSentOnDemand(NewPurchaseForAdmin::class, fn (NewPurchaseForAdmin $n, array $channels, object $notifiable) => str_contains(
        (string) $n->toMail($notifiable)->render(), 'Payée avec PayPal'));

    // Captured with the attempt's own idempotency key.
    Http::assertSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/capture')
        && $request->header('PayPal-Request-Id')[0] === 'capture-'.$payment->charge_id);

    $this->actingAs($basket['buyer'])
        ->get(route('checkout.payment', [$reference, 'outcome' => 'success']))
        ->assertRedirect(route('checkout.confirmation', $reference));
});

test('a declined PayPal payment leaves the purchase open for another try', function () {
    fakePayPal('INSTRUMENT_DECLINED');
    $basket = paypalBasket();
    $payment = paypalLaunch($this, $basket);

    paypalReturn($this, $payment)
        ->assertRedirect(route('checkout.payment', [$payment->group_reference, 'outcome' => 'failure']));

    expect($payment->fresh()->status)->toBe('failed')
        ->and($payment->fresh()->provider_status)->toBe('INSTRUMENT_DECLINED')
        ->and(paypalOrders($payment->group_reference)->sole()->status)->toBe('pending');

    $this->actingAs($basket['buyer'])
        ->get(route('checkout.payment', [$payment->group_reference, 'outcome' => 'failure']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('payment.method', 'paypal')
            ->where('payment.state', 'awaiting')
            ->where('payment.last_attempt', 'failed')
            ->where('payment.charged.currency', 'EUR'));
});

test('a purchase released while the buyer was on PayPal is never captured', function () {
    fakePayPal();
    $basket = paypalBasket();
    $payment = paypalLaunch($this, $basket);

    $this->travel(61)->minutes();
    app(PaymentService::class)->expireStale();

    paypalReturn($this, $payment)
        ->assertRedirect(route('checkout.payment', [$payment->group_reference, 'outcome' => 'failure']));

    Http::assertNotSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/capture'));

    expect($payment->fresh()->status)->toBe('expired')
        ->and(Order::count())->toBe(0)
        ->and($basket['product']->fresh()->stock)->toBe(1);
});

test('cancelling on PayPal brings the buyer back to try again', function () {
    fakePayPal();
    $basket = paypalBasket();
    $payment = paypalLaunch($this, $basket);

    $this->get(route('payments.paypal.return', [$payment->charge_id, 'outcome' => 'cancel', 'token' => PAYPAL_ORDER_ID]))
        ->assertRedirect(route('checkout.payment', [$payment->group_reference, 'outcome' => 'cancel']));

    Http::assertNotSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/capture'));

    expect($payment->fresh()->status)->toBe('pending')
        ->and($payment->fresh()->provider_status)->toBe('PAYER_CANCELLED');
});

test('an APPROVED webhook takes the money for a buyer who never came back', function () {
    Notification::fake();
    fakePayPal();
    $basket = paypalBasket();
    $payment = paypalLaunch($this, $basket);

    paypalWebhook($this, 'CHECKOUT.ORDER.APPROVED', [
        'id' => PAYPAL_ORDER_ID,
        'status' => 'APPROVED',
        'purchase_units' => [['reference_id' => $payment->group_reference, 'custom_id' => $payment->charge_id]],
    ])->assertOk();

    expect(Order::sole()->payment_status)->toBe('paid');
    Notification::assertSentToTimes($basket['buyer'], OrderPlaced::class, 1);

    // The event went back to PayPal for verification byte for byte.
    Http::assertSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/verify-webhook-signature')
        && $request['webhook_id'] === 'WH-TEST'
        && $request['webhook_event']['event_type'] === 'CHECKOUT.ORDER.APPROVED'
        && $request['auth_algo'] === 'SHA256withRSA');
});

test('a webhook PayPal cannot verify is refused', function () {
    fakePayPal(verification: 'FAILURE');
    $basket = paypalBasket();
    $payment = paypalLaunch($this, $basket);

    paypalWebhook($this, 'PAYMENT.CAPTURE.COMPLETED', paypalCapture($payment))->assertStatus(400);

    // Unsigned altogether: refused without asking PayPal.
    paypalWebhook($this, 'PAYMENT.CAPTURE.COMPLETED', paypalCapture($payment), ['HTTP_PAYPAL_TRANSMISSION_SIG' => ''])
        ->assertStatus(400);

    expect($payment->fresh()->status)->toBe('pending')
        ->and(Order::count())->toBe(0);
});

test('a capture PayPal holds back settles once PayPal completes it', function () {
    Notification::fake();
    fakePayPal('PENDING');
    $basket = paypalBasket();
    $payment = paypalLaunch($this, $basket);

    paypalReturn($this, $payment)
        ->assertRedirect(route('checkout.payment', [$payment->group_reference, 'outcome' => 'success']));

    expect($payment->fresh()->status)->toBe('pending')
        ->and($payment->fresh()->provider_status)->toBe('PENDING PENDING_REVIEW')
        ->and(Order::count())->toBe(0);
    Notification::assertNothingSent();

    paypalWebhook($this, 'PAYMENT.CAPTURE.COMPLETED', paypalCapture($payment->fresh()))->assertOk();

    expect(Order::sole()->payment_status)->toBe('paid');
    Notification::assertSentToTimes($basket['buyer'], OrderPlaced::class, 1);
});

test('less money than the attempt asked for is flagged instead of placing the purchase', function () {
    Notification::fake();
    $admin = User::factory()->create(['role' => 'super_admin']);
    fakePayPal();
    $basket = paypalBasket();
    $payment = paypalLaunch($this, $basket);

    paypalWebhook($this, 'PAYMENT.CAPTURE.COMPLETED', paypalCapture($payment, amount: 2.00))->assertOk();

    expect(Order::count())->toBe(0);
    Notification::assertSentTo($admin, CardRefundNeeded::class, fn ($n) => $n->provider === 'paypal' && $n->currency === 'EUR');
});

test('a refund made from PayPal is recorded, and a partial one only noted', function () {
    fakePayPal();
    $basket = paypalBasket();
    $payment = paypalLaunch($this, $basket);
    paypalReturn($this, $payment);

    $refund = fn (string $value) => [
        'id' => '1JU08902781691411',
        'status' => 'COMPLETED',
        'amount' => ['currency_code' => 'EUR', 'value' => $value],
        'seller_payable_breakdown' => ['total_refunded_amount' => ['currency_code' => 'EUR', 'value' => $value]],
        // A refund made from the dashboard carries no custom_id — only its capture.
        'links' => [['rel' => 'up', 'href' => PAYPAL_API.'/v2/payments/captures/'.PAYPAL_CAPTURE_ID, 'method' => 'GET']],
    ];

    paypalWebhook($this, 'PAYMENT.CAPTURE.REFUNDED', $refund('10.00'))->assertOk();

    expect($payment->fresh()->status)->toBe('paid')
        ->and($payment->fresh()->provider_status)->toBe('PARTIALLY_REFUNDED')
        ->and(Order::sole()->payment_status)->toBe('paid');

    paypalWebhook($this, 'PAYMENT.CAPTURE.REFUNDED', $refund('22.08'))->assertOk();

    expect($payment->fresh()->status)->toBe('refunded')
        ->and(Order::sole()->payment_status)->toBe('refunded');
});

test('cancelling an order paid with PayPal asks the desk for a refund in euros', function () {
    Notification::fake();
    $admin = User::factory()->create(['role' => 'super_admin']);
    fakePayPal();
    $basket = paypalBasket();
    paypalReturn($this, paypalLaunch($this, $basket));

    app(OrderStatusService::class)->transition(Order::sole(), 'cancelled');

    Notification::assertSentTo($admin, CardRefundNeeded::class, fn ($n) => $n->amount === 22.08
        && $n->currency === 'EUR'
        && $n->provider === 'paypal'
        && $n->transactionId === PAYPAL_CAPTURE_ID);

    $this->actingAs($admin)
        ->get(route('admin.orders.show', Order::sole()->reference))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.payment_method', 'paypal')
            ->where('payment.provider', 'paypal')
            ->where('payment.currency', 'EUR'));
});

test('another buyer cannot send someone else\'s PayPal purchase to PayPal', function () {
    fakePayPal();
    $basket = paypalBasket();
    $this->actingAs($basket['buyer'])->post(route('checkout.store'), $basket['payload']);
    $reference = Order::withoutGlobalScope(Order::SETTLED_SCOPE)->value('group_reference');

    $stranger = User::factory()->create(['role' => 'buyer', 'email_verified_at' => now()]);

    $this->actingAs($stranger)
        ->get(route('checkout.pay', $reference))
        ->assertRedirect(route('checkout.payment', $reference));

    expect(Payment::count())->toBe(0);
});
