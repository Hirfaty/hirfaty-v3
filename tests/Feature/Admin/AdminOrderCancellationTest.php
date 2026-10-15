<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\City;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Notifications\Admin\CardRefundNeeded;
use App\Notifications\OrderCancelled;
use App\Services\PaymentService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

const CANCEL_CAPTURE_ID = '3C679366HH908993F';

beforeEach(function () {
    config([
        'services.paypal.client_id' => 'test-client',
        'services.paypal.client_secret' => 'test-secret',
        'services.paypal.mode' => 'sandbox',
        'services.paypal.currency' => 'EUR',
    ]);

    Http::preventStrayRequests();
});

/**
 * PayPal's token and refund endpoints. `$refund` is the refund's status, or
 * a PayPal error issue answered with a 422.
 */
function fakePayPalRefunds(string $refund = 'COMPLETED'): void
{
    Http::fake(function (HttpRequest $request) use ($refund) {
        $path = parse_url($request->url(), PHP_URL_PATH);

        if ($path === '/v1/oauth2/token') {
            return Http::response(['access_token' => 'A21-test-token', 'expires_in' => 32400]);
        }

        if ($path === '/v2/payments/captures/'.CANCEL_CAPTURE_ID.'/refund') {
            if (! in_array($refund, ['COMPLETED', 'PENDING'], true)) {
                return Http::response(['name' => 'UNPROCESSABLE_ENTITY', 'details' => [['issue' => $refund]]], 422);
            }

            return Http::response(['id' => '1JU08902781691411', 'status' => $refund, 'amount' => $request['amount']]);
        }

        return Http::response(['name' => 'NOT_FOUND'], 404);
    });
}

/**
 * A buyer (reading English) who bought one 200 MAD piece from each of
 * `$stores` workshops, paid with `$method`. Delivery (40 MAD) rides on the
 * first order, so one workshop comes to 240 MAD — 22.08 EUR through PayPal.
 *
 * @return array{buyer: User, admin: User, reference: string, products: list<Product>}
 */
function paidPurchase($test, string $method, int $stores = 1): array
{
    $buyer = User::factory()->create(['role' => 'buyer', 'email_verified_at' => now(), 'locale' => 'en']);
    $admin = User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);
    $city = City::create(['name' => 'Casablanca', 'region' => 'Casablanca-Settat']);
    $cart = Cart::create(['user_id' => $buyer->id]);

    $products = [];

    foreach (range(1, $stores) as $i) {
        $seller = User::factory()->create(['role' => 'seller', 'email_verified_at' => now()]);
        $store = Store::create([
            'user_id' => $seller->id,
            'store_name' => "Atelier {$i}",
            'slug' => 'atelier-'.Str::random(8),
            'status' => 'active',
        ]);
        $products[] = $product = Product::create([
            'store_id' => $store->id,
            'name' => "Tajine {$i}",
            'slug' => 'tajine-'.Str::random(8),
            'base_price' => 200,
            'stock' => 1,
            'status' => 'published',
        ]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
    }

    $test->actingAs($buyer)->post(route('checkout.store'), [
        'first_name' => 'Yasmine',
        'last_name' => 'Alaoui',
        'shipping_phone' => '0600000000',
        'shipping_address' => '12 Rue Ibn Battouta',
        'city_id' => $city->id,
        'postal_code' => '20000',
        'country' => 'MA',
        'payment_method' => $method,
    ])->assertRedirect();

    $reference = Order::withoutGlobalScope(Order::SETTLED_SCOPE)->value('group_reference');

    if ($method === 'card') {
        confirmCardPayment($reference);
    } else {
        $payments = app(PaymentService::class);
        $mad = $payments->amountFor(Order::withoutGlobalScope(Order::SETTLED_SCOPE)->get());
        $payment = Payment::create([
            'group_reference' => $reference,
            'buyer_id' => $buyer->id,
            'provider' => 'paypal',
            'charge_id' => Payment::newChargeId(),
            'provider_order_id' => '5O190127TN364715T',
            'amount' => round($mad * 0.092, 2),
            'currency' => 'EUR',
            'exchange_rate' => 0.092,
            'status' => 'pending',
        ]);
        $payments->settle($payment, (float) $payment->amount, 'EUR', [
            'provider_status' => 'COMPLETED',
            'provider_transaction_id' => CANCEL_CAPTURE_ID,
        ]);
    }

    expect(Order::where('payment_status', 'paid')->count())->toBe($stores);

    return ['buyer' => $buyer, 'admin' => $admin, 'reference' => $reference, 'products' => $products];
}

function cancelAs($test, User $admin, Order $order, array $data)
{
    return $test->actingAs($admin)
        ->from(route('admin.orders.show', $order->reference))
        ->post(route('admin.orders.cancel', $order->reference), $data);
}

test('cancelling with refund gives a PayPal buyer their money back and says why in their language', function () {
    Notification::fake();
    fakePayPalRefunds();
    $purchase = paidPurchase($this, 'paypal');
    $order = Order::sole();

    cancelAs($this, $purchase['admin'], $order, [
        'reasons' => ['out_of_stock', 'delay'],
        'details' => 'Back in the workshop next month.',
        'refund' => true,
    ])->assertRedirect()->assertSessionHas('success', fn ($message) => str_contains($message, 'Remboursement PayPal de 22,08 EUR effectué'));

    $order->refresh();
    expect($order->status)->toBe('cancelled')
        ->and($order->cancellation_tags)->toBe(['out_of_stock', 'delay'])
        ->and($order->cancellation_reason)->toBe('Back in the workshop next month.')
        ->and($order->cancellation_refund)->toBeTrue()
        ->and($order->payment_status)->toBe('refunded')
        ->and(Payment::sole()->status)->toBe('refunded')
        ->and($purchase['products'][0]->fresh()->stock)->toBe(1);

    Http::assertSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/captures/'.CANCEL_CAPTURE_ID.'/refund')
        && $request['amount'] === ['value' => '22.08', 'currency_code' => 'EUR']);

    // Done automatically: no "refund to make" alert for the desk.
    Notification::assertNotSentTo($purchase['admin'], CardRefundNeeded::class);

    Notification::assertSentTo($purchase['buyer'], OrderCancelled::class, function (OrderCancelled $n) use ($purchase) {
        $html = (string) $n->toMail($purchase['buyer'])->render();

        return str_contains($html, 'The piece is no longer available from the artisan')
            && str_contains($html, 'The making time would be too long')
            && str_contains($html, 'Back in the workshop next month.')
            && str_contains($html, 'refunded to your PayPal account');
    });
});

test('cancelling without refund keeps the money and tells the buyer so', function () {
    Notification::fake();
    fakePayPalRefunds();
    $purchase = paidPurchase($this, 'paypal');
    $order = Order::sole();

    cancelAs($this, $purchase['admin'], $order, ['reasons' => ['duplicate'], 'refund' => false])
        ->assertSessionHas('success');

    $order->refresh();
    expect($order->status)->toBe('cancelled')
        ->and($order->cancellation_refund)->toBeFalse()
        ->and($order->payment_status)->toBe('paid');

    Http::assertNothingSent();
    Notification::assertNotSentTo($purchase['admin'], CardRefundNeeded::class);
    Notification::assertSentTo($purchase['buyer'], OrderCancelled::class, fn (OrderCancelled $n) => str_contains(
        (string) $n->toMail($purchase['buyer'])->render(), 'does not come with a refund'));
});

test('a card refund is left to the desk, with an alert to make it in Payzone', function () {
    Notification::fake();
    $purchase = paidPurchase($this, 'card');

    cancelAs($this, $purchase['admin'], Order::sole(), ['reasons' => ['damaged'], 'refund' => true])
        ->assertSessionHas('success', fn ($message) => str_contains($message, 'back-office Payzone'));

    Http::assertNothingSent();
    Notification::assertSentTo($purchase['admin'], CardRefundNeeded::class, fn ($n) => $n->provider === 'payzone' && $n->amount === 240.0);
    // Payzone's own REFUNDED notification will record it, not this.
    expect(Order::sole()->payment_status)->toBe('paid');
});

test('a refund PayPal refuses becomes an alert for the desk', function () {
    Notification::fake();
    fakePayPalRefunds('TRANSACTION_REFUSED');
    $purchase = paidPurchase($this, 'paypal');

    cancelAs($this, $purchase['admin'], Order::sole(), ['reasons' => ['quality_issue'], 'refund' => true])
        ->assertSessionHas('error', fn ($message) => str_contains($message, 'a échoué'));

    expect(Order::sole()->status)->toBe('cancelled')
        ->and(Order::sole()->payment_status)->toBe('paid');
    Notification::assertSentTo($purchase['admin'], CardRefundNeeded::class, fn ($n) => $n->provider === 'paypal' && $n->currency === 'EUR');
});

test('cancelling one workshop refunds only its share of the purchase', function () {
    Notification::fake();
    fakePayPalRefunds();
    $purchase = paidPurchase($this, 'paypal', stores: 2);
    [$first, $second] = Order::orderBy('id')->get();

    // The second workshop's order: 200 MAD, no delivery → 18.40 EUR of 40.48.
    cancelAs($this, $purchase['admin'], $second, ['reasons' => ['out_of_stock'], 'refund' => true, 'scope' => 'single'])
        ->assertSessionHas('success');

    Http::assertSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/refund')
        && $request['amount'] === ['value' => '18.40', 'currency_code' => 'EUR']);

    expect($second->fresh()->payment_status)->toBe('refunded')
        ->and($first->fresh()->payment_status)->toBe('paid')
        ->and($first->fresh()->status)->not->toBe('cancelled')
        ->and(Payment::sole()->status)->toBe('paid')
        ->and(Payment::sole()->provider_status)->toBe('PARTIALLY_REFUNDED');
});

test('a cancellation must say why', function () {
    $purchase = paidPurchase($this, 'card');

    cancelAs($this, $purchase['admin'], Order::sole(), ['reasons' => [], 'details' => '  ', 'refund' => true])
        ->assertSessionHasErrors('details');

    cancelAs($this, $purchase['admin'], Order::sole(), ['reasons' => ['made_up'], 'refund' => true])
        ->assertSessionHasErrors('reasons.0');

    expect(Order::sole()->status)->not->toBe('cancelled');
});

test('the admin page offers the reasons and shows why an order was cancelled', function () {
    Notification::fake();
    fakePayPalRefunds();
    $purchase = paidPurchase($this, 'paypal');

    cancelAs($this, $purchase['admin'], Order::sole(), ['reasons' => ['address_issue'], 'refund' => true]);

    $this->actingAs($purchase['admin'])
        ->get(route('admin.orders.show', Order::sole()->reference))
        ->assertInertia(fn (Assert $page) => $page
            ->where('cancellationReasons.0.code', 'out_of_stock')
            ->where('group.orders.0.cancellation', ['Livraison impossible à l’adresse indiquée'])
            ->where('group.orders.0.cancellation_refund', true)
            ->where('group.orders.0.payment_status', 'refunded'));

    // And the buyer's tracking page, in the language they read it in.
    $this->actingAs($purchase['buyer'])
        ->withSession(['locale' => 'en'])
        ->get(route('buyer.purchases.tracking', $purchase['reference']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('events.0.status', 'cancelled')
            ->where('events.0.note', 'Delivery to the address given is not possible'));
});
