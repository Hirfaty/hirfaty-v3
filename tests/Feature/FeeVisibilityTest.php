<?php

use App\Models\City;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;

/**
 * Hirfaty's cut, and what an artisan is left with after it, must not reach
 * any client.
 *
 * The figures are still computed and stored — CheckoutService writes
 * `platform_fee` / `seller_earnings` on every order and an Earning row per
 * store — but no screen reports them, and `Order::$hidden` keeps them out of
 * serialised payloads so they are not a devtools panel away either. These
 * tests fail the moment either column is put back on the wire.
 */
beforeEach(function () {
    $this->city = City::create(['name' => 'Fès', 'region' => 'Fès-Meknès']);

    $this->seller = User::factory()->create(['role' => 'seller']);
    $this->store = Store::create([
        'user_id' => $this->seller->id,
        'store_name' => 'Atelier Test',
        'slug' => 'atelier-test',
        'type' => 'artist',
        'city_id' => $this->city->id,
    ]);

    $this->buyer = User::factory()->create(['role' => 'buyer']);

    $this->order = Order::create([
        'buyer_id' => $this->buyer->id,
        'store_id' => $this->store->id,
        'city_id' => $this->city->id,
        'status' => 'delivered',
        'total_amount' => 200,
        'platform_fee' => 40,
        'delivery_fee' => 20,
        'seller_earnings' => 140,
        'shipping_address' => 'Adresse de test',
    ]);
});

test('the fee columns are still written and readable server-side', function () {
    // The point is that the ledger is intact — this is a display decision,
    // not a data one. If this fails, the money maths itself broke.
    $order = $this->order->fresh();

    expect((float) $order->platform_fee)->toBe(40.0)
        ->and((float) $order->seller_earnings)->toBe(140.0);
});

test('an order never serialises what the platform took or the seller kept', function () {
    $json = $this->order->fresh()->toArray();

    expect($json)->not->toHaveKey('platform_fee')
        ->and($json)->not->toHaveKey('seller_earnings')
        // Order money still travels: the buyer paid it and every screen shows it.
        ->and($json)->toHaveKey('total_amount')
        ->and($json)->toHaveKey('delivery_fee');
});

test('the seller order screen carries no fee or payout figure', function () {
    $response = $this->actingAs($this->seller)
        ->get(route('seller.orders.show', $this->order->reference));

    $response->assertSuccessful();

    // Walk the whole prop payload rather than asserting on known keys — this
    // catches the figures arriving under a relation (order.earning, items...)
    // as well as on the order itself.
    $encoded = json_encode($response->viewData('page')['props']);

    expect($encoded)->not->toContain('platform_fee')
        ->and($encoded)->not->toContain('seller_earnings')
        ->and($encoded)->not->toContain('net_amount');
});

test('the seller sales screen reports order money and nothing derived from the fee', function () {
    $response = $this->actingAs($this->seller)->get(route('seller.sales.index'));

    $response->assertSuccessful();

    $props = $response->viewData('page')['props'];

    // Delivered order value is reported as-is, not minus commission.
    expect((float) $props['summary']['delivered_total'])->toBe(200.0);

    $encoded = json_encode($props);

    expect($encoded)->not->toContain('platform_fee')
        ->and($encoded)->not->toContain('seller_earnings')
        ->and($encoded)->not->toContain('net_amount');
});

test('the seller dashboard reports order money and nothing derived from the fee', function () {
    $response = $this->actingAs($this->seller)->get(route('seller.dashboard'));

    $response->assertSuccessful();

    $props = $response->viewData('page')['props'];

    expect((float) $props['stats']['total_sales'])->toBe(200.0)
        ->and($props['stats'])->not->toHaveKey('total_revenue')
        ->and($props['stats'])->not->toHaveKey('pending_payout');

    $encoded = json_encode($props);

    expect($encoded)->not->toContain('platform_fee')
        ->and($encoded)->not->toContain('seller_earnings')
        ->and($encoded)->not->toContain('net_amount');
});

test('the admin seller page shows sales instead of an earnings summary', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);

    $response = $this->actingAs($admin)->get(route('admin.users.show', $this->seller));

    $response->assertSuccessful();

    $props = $response->viewData('page')['props'];

    expect($props)->toHaveKey('sales')
        ->and($props)->not->toHaveKey('earnings')
        ->and((float) $props['sales']['delivered_total'])->toBe(200.0);

    $encoded = json_encode($props);

    expect($encoded)->not->toContain('platform_fee')
        ->and($encoded)->not->toContain('seller_earnings')
        ->and($encoded)->not->toContain('net_amount');
});
