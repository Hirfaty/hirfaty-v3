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
use App\Services\OrderStatusService;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config([
        'services.payzone.merchant_account' => 'hirfaty_Test',
        'services.payzone.secret_key' => 'test-secret',
        'services.payzone.notification_key' => 'test-notify',
        'services.payzone.paywall_url' => 'https://payment-sandbox.payzone.ma/pwv4/launch',
        'services.payzone.callback_base_url' => null,
        'services.payzone.expire_after_minutes' => 60,
        'hirfaty.admin_email' => 'desk@hirfaty.test',
    ]);
});

function payzoneBuyer(): User
{
    return User::factory()->create(['role' => 'buyer', 'email_verified_at' => now()]);
}

/**
 * A buyer with one 200 MAD piece (stock 1) in their cart, and the checkout
 * fields that ship it to Casablanca. Delivery falls back to 40 MAD with no
 * rate card seeded, so the purchase comes to 240 MAD.
 *
 * @return array{buyer: User, product: Product, payload: array<string, mixed>}
 */
function payzoneBasket(int $stock = 1): array
{
    $buyer = payzoneBuyer();
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
        'stock' => $stock,
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
            'payment_method' => 'card',
        ],
    ];
}

/** Check out by card and return the purchase reference. */
function payzoneCheckout($test, array $basket): string
{
    $test->actingAs($basket['buyer'])
        ->post(route('checkout.store'), $basket['payload'])
        ->assertRedirect();

    return Order::withoutGlobalScope(Order::SETTLED_SCOPE)->latest('id')->value('group_reference');
}

/** Send the buyer to the payment page and return the attempt it created. */
function payzoneLaunch($test, User $buyer, string $reference): Payment
{
    $test->actingAs($buyer)->get(route('checkout.pay', $reference))->assertOk();

    return Payment::where('group_reference', $reference)->latest('id')->firstOrFail();
}

/**
 * A notification as Payzone sends it, signed with the notification key.
 *
 * @param  array<string, mixed>  $overrides
 */
function payzoneNotify($test, Payment $payment, string $status = 'CHARGED', array $overrides = [], ?string $key = null): TestResponse
{
    $state = $status === 'CHARGED' ? 'APPROVED' : 'DECLINED';

    $body = json_encode(array_merge([
        'id' => $payment->charge_id,
        'orderId' => $payment->group_reference,
        'internalId' => '2347897968234687234',
        'status' => $status,
        'merchantAccount' => 'hirfaty_Test',
        'lineItem' => ['amount' => (float) $payment->amount, 'currency' => 'MAD', 'description' => 'Hirfaty'],
        'transactions' => [[
            'timestamp' => now()->toIso8601String(),
            'gatewayProvidedId' => 'dd317ae1-f9cb-47ef-8a8b-10e8a754c5a2',
            'type' => 'CHARGE',
            'state' => $state,
            'amount' => (float) $payment->amount,
            'currency' => 'MAD',
            'resultCode' => $status === 'CHARGED' ? 0 : 1,
            'responseText' => $status === 'CHARGED' ? 'Success' : 'Declined',
        ]],
        'paymentType' => 'CREDIT_CARD',
        'paymentMethod' => 'Visa',
    ], $overrides));

    return $test->call(
        'POST',
        route('payments.payzone.callback'),
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CALLBACK_SIGNATURE' => hash_hmac('sha256', $body, $key ?? 'test-notify'),
        ],
        content: $body,
    );
}

function unsettledOrders(string $reference)
{
    return Order::withoutGlobalScope(Order::SETTLED_SCOPE)->where('group_reference', $reference)->get();
}

test('checkout offers card payment only, and only once Payzone is configured', function () {
    $basket = payzoneBasket();

    $this->actingAs($basket['buyer'])->get(route('checkout.show'))
        ->assertInertia(fn (Assert $page) => $page->where('paymentMethods', ['card']));

    config(['services.payzone.secret_key' => null]);

    $this->actingAs($basket['buyer'])->get(route('checkout.show'))
        ->assertInertia(fn (Assert $page) => $page->where('paymentMethods', []));
});

test('a card checkout holds the stock but places nothing until Payzone confirms', function () {
    Notification::fake();
    $basket = payzoneBasket();

    $response = $this->actingAs($basket['buyer'])->post(route('checkout.store'), $basket['payload']);

    $order = Order::withoutGlobalScope(Order::SETTLED_SCOPE)->sole();
    $response->assertRedirect(route('checkout.pay', $order->group_reference));

    expect($order->payment_method)->toBe('card')
        ->and($order->payment_status)->toBe('pending')
        // Held, so nobody else can buy the last piece meanwhile…
        ->and($basket['product']->fresh()->stock)->toBe(0)
        // …but invisible to every screen, unearned, and unannounced.
        ->and(Order::count())->toBe(0)
        ->and(Earning::count())->toBe(0)
        // The cart is kept in case the payment never happens.
        ->and(CartItem::count())->toBe(1);

    Notification::assertNothingSent();
});

test('the launch page posts a payload signed with the paywall secret', function () {
    $basket = payzoneBasket();
    $reference = payzoneCheckout($this, $basket);

    $response = $this->actingAs($basket['buyer'])->get(route('checkout.pay', $reference));
    $response->assertOk()->assertSee('https://payment-sandbox.payzone.ma/pwv4/launch', false);

    $payment = Payment::sole();
    expect((float) $payment->amount)->toBe(240.0)
        ->and($payment->status)->toBe('pending');

    preg_match('/name="payload" value="([^"]+)"/', $response->getContent(), $payload);
    preg_match('/name="signature" value="([^"]+)"/', $response->getContent(), $signature);

    $json = html_entity_decode($payload[1], ENT_QUOTES);
    $data = json_decode($json, true);

    expect($signature[1])->toBe(hash('sha256', 'test-secret'.$json))
        ->and($data['merchantAccount'])->toBe('hirfaty_Test')
        ->and($data['chargeId'])->toBe($payment->charge_id)
        ->and($data['orderId'])->toBe($reference)
        ->and($data['price'])->toBe('240.00')
        ->and($data['currency'])->toBe('MAD')
        ->and($data['mode'])->toBe('DEEP_LINK')
        ->and($data['callbackUrl'])->toBe(route('payments.payzone.callback'))
        ->and($data['successUrl'])->toContain($payment->charge_id);
});

test('a signed CHARGED notification places the purchase exactly once', function () {
    Notification::fake();
    $basket = payzoneBasket();
    $reference = payzoneCheckout($this, $basket);
    $payment = payzoneLaunch($this, $basket['buyer'], $reference);

    payzoneNotify($this, $payment)->assertOk()->assertJson(['status' => 'OK']);
    // Payzone resends until it gets a 200; a repeat must change nothing.
    payzoneNotify($this, $payment)->assertOk();

    $order = Order::sole();
    expect($order->payment_status)->toBe('paid')
        // Paid is confirmed: no "pending" step waiting on the desk…
        ->and($order->status)->toBe('confirmed')
        // …and the history says so, once, after the checkout's own line.
        ->and($order->statusEvents()->pluck('status')->all())->toBe(['pending', 'confirmed'])
        ->and($payment->fresh()->status)->toBe('paid')
        ->and($payment->fresh()->card_brand)->toBe('Visa')
        ->and(Earning::where('order_id', $order->id)->count())->toBe(1)
        ->and(CartItem::count())->toBe(0);

    Notification::assertSentToTimes($basket['buyer'], OrderPlaced::class, 1);
    Notification::assertSentOnDemandTimes(NewPurchaseForAdmin::class, 1);

    // Back from Payzone, the buyer is taken straight to the confirmation.
    $this->actingAs($basket['buyer'])
        ->get(route('checkout.payment', $reference))
        ->assertRedirect(route('checkout.confirmation', $reference));
});

test('a notification with a bad signature is refused', function () {
    $basket = payzoneBasket();
    $reference = payzoneCheckout($this, $basket);
    $payment = payzoneLaunch($this, $basket['buyer'], $reference);

    payzoneNotify($this, $payment, key: 'wrong-key')->assertForbidden();

    expect($payment->fresh()->status)->toBe('pending')
        ->and(Order::count())->toBe(0);
});

test('a declined card leaves the purchase open for another try', function () {
    $basket = payzoneBasket();
    $reference = payzoneCheckout($this, $basket);
    $payment = payzoneLaunch($this, $basket['buyer'], $reference);

    payzoneNotify($this, $payment, 'DECLINED')->assertOk();

    expect($payment->fresh()->status)->toBe('failed')
        ->and(unsettledOrders($reference)->first()->status)->toBe('pending');

    $this->actingAs($basket['buyer'])
        ->get(route('checkout.payment', [$reference, 'outcome' => 'failure']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('buyer/checkout-payment')
            ->where('payment.state', 'awaiting')
            ->where('payment.last_attempt', 'failed'));

    // Each trip to the payment page is a new charge id, as Payzone requires.
    $retry = payzoneLaunch($this, $basket['buyer'], $reference);
    expect($retry->charge_id)->not->toBe($payment->charge_id);
});

test('cancelling an unpaid card purchase hands the stock back and keeps the cart', function () {
    $basket = payzoneBasket();
    $reference = payzoneCheckout($this, $basket);

    $this->actingAs($basket['buyer'])
        ->post(route('checkout.payment.cancel', $reference))
        ->assertRedirect(route('cart.show'));

    $order = unsettledOrders($reference)->sole();
    expect($order->status)->toBe('cancelled')
        ->and($order->payment_status)->toBe('failed')
        ->and($basket['product']->fresh()->stock)->toBe(1)
        ->and(CartItem::count())->toBe(1);
});

test('checking out again releases what an abandoned card purchase was holding', function () {
    $basket = payzoneBasket();
    $first = payzoneCheckout($this, $basket);

    // The only piece is held by the abandoned attempt — without the release
    // this second checkout would be refused as out of stock.
    $second = payzoneCheckout($this, $basket);

    expect($second)->not->toBe($first)
        ->and(unsettledOrders($first)->sole()->status)->toBe('cancelled')
        ->and(unsettledOrders($second)->sole()->status)->toBe('pending')
        ->and($basket['product']->fresh()->stock)->toBe(0);
});

test('unpaid card purchases expire and release their stock', function () {
    $basket = payzoneBasket();
    $reference = payzoneCheckout($this, $basket);
    payzoneLaunch($this, $basket['buyer'], $reference);

    $this->travel(30)->minutes();
    expect(app(PaymentService::class)->expireStale())->toBe(0);

    $this->travel(31)->minutes();
    $this->artisan('payments:expire-stale')->assertSuccessful();

    expect(unsettledOrders($reference)->sole()->status)->toBe('cancelled')
        ->and(Payment::sole()->status)->toBe('expired')
        ->and($basket['product']->fresh()->stock)->toBe(1);

    $this->actingAs($basket['buyer'])
        ->get(route('checkout.payment', $reference))
        ->assertInertia(fn (Assert $page) => $page->where('payment.state', 'released'));
});

test('money arriving for an expired purchase is flagged for a refund', function () {
    Notification::fake();
    $admin = User::factory()->create(['role' => 'super_admin']);
    $basket = payzoneBasket();
    $reference = payzoneCheckout($this, $basket);
    $payment = payzoneLaunch($this, $basket['buyer'], $reference);

    $this->travel(61)->minutes();
    app(PaymentService::class)->expireStale();

    payzoneNotify($this, $payment)->assertOk();

    expect(Order::count())->toBe(0)
        ->and($basket['product']->fresh()->stock)->toBe(1);

    Notification::assertSentTo($admin, CardRefundNeeded::class);
    Notification::assertNotSentTo($basket['buyer'], OrderPlaced::class);
});

test('cancelling an order already paid by card asks the desk for a refund', function () {
    Notification::fake();
    $admin = User::factory()->create(['role' => 'super_admin']);
    $basket = payzoneBasket();
    $reference = payzoneCheckout($this, $basket);
    payzoneNotify($this, payzoneLaunch($this, $basket['buyer'], $reference));

    app(OrderStatusService::class)->transition(Order::sole(), 'cancelled');

    Notification::assertSentTo($admin, CardRefundNeeded::class, fn ($n) => $n->amount === 240.0);
});

test('the return from Payzone hands the buyer to their payment page', function () {
    $basket = payzoneBasket();
    $reference = payzoneCheckout($this, $basket);
    $payment = payzoneLaunch($this, $basket['buyer'], $reference);

    // A cross-site POST is turned into a GET so the session cookie comes along.
    $this->post(route('payments.payzone.return', [$payment->charge_id, 'outcome' => 'success']))
        ->assertStatus(303)
        ->assertRedirect(route('payments.payzone.return', [$payment->charge_id, 'outcome' => 'success']));

    $this->actingAs($basket['buyer'])
        ->get(route('payments.payzone.return', [$payment->charge_id, 'outcome' => 'success']))
        ->assertRedirect(route('checkout.payment', [$reference, 'outcome' => 'success']));

    // Still waiting for the notification: the page waits rather than claiming success.
    $this->actingAs($basket['buyer'])
        ->get(route('checkout.payment', [$reference, 'outcome' => 'success']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('payment.state', 'awaiting')
            ->where('payment.outcome', 'success'));
});

test('another buyer cannot pay for, view or cancel someone else\'s purchase', function () {
    $basket = payzoneBasket();
    $reference = payzoneCheckout($this, $basket);
    $stranger = payzoneBuyer();

    $this->actingAs($stranger)->get(route('checkout.payment', $reference))->assertNotFound();
    $this->actingAs($stranger)->get(route('checkout.pay', $reference))->assertRedirect(route('checkout.payment', $reference));
    $this->actingAs($stranger)->post(route('checkout.payment.cancel', $reference));

    expect(Payment::count())->toBe(0)
        ->and(unsettledOrders($reference)->sole()->status)->toBe('pending');
});
