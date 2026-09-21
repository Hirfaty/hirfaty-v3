<?php

use App\Models\City;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * `orders.id` is a single sequence shared across every store, so exposing it
 * would leak the platform's total order volume. These cover the opaque
 * reference that replaces it in the UI and in URLs.
 */
function makeOrder(array $attributes = []): Order
{
    $buyer = User::factory()->create(['role' => 'buyer']);
    $seller = User::factory()->create(['role' => 'seller']);

    $store = Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Ref Store',
        'slug' => Str::slug('ref-store-'.Str::random(6)),
        'status' => 'active',
        'city_id' => City::first()?->id ?? City::create(['name' => 'Fès'])->id,
    ]);

    return Order::create(array_merge([
        'buyer_id' => $buyer->id,
        'store_id' => $store->id,
        'status' => 'pending',
        'total_amount' => 100,
        'platform_fee' => 20,
        'delivery_fee' => 20,
        'seller_earnings' => 100,
        'shipping_address' => '1 Rue Test',
    ], $attributes));
}

it('assigns a reference automatically on create', function () {
    $order = makeOrder();

    expect($order->reference)->toMatch('/^HRF-[0-9A-Z]{4}-[0-9A-Z]{4}$/');
});

it('never uses ambiguous characters in a reference', function () {
    // Crockford base32 drops I, L, O and U so a reference can be read aloud
    // or retyped from a screenshot without guesswork.
    $body = str_replace(['HRF-', '-'], '', makeOrder()->reference);

    expect($body)->not->toContain('I')
        ->and($body)->not->toContain('L')
        ->and($body)->not->toContain('O')
        ->and($body)->not->toContain('U');
});

it('generates a distinct reference per order', function () {
    $references = collect(range(1, 10))->map(fn () => makeOrder()->reference);

    expect($references->unique())->toHaveCount(10);
});

it('does not overwrite a reference that was supplied explicitly', function () {
    $order = makeOrder(['reference' => 'HRF-TEST-0001']);

    expect($order->reference)->toBe('HRF-TEST-0001');
});

it('resolves route model binding by reference, not id', function () {
    $order = makeOrder();
    $admin = User::factory()->create([
        'role' => 'super_admin',
        'email_verified_at' => now(),
    ]);

    expect($order->getRouteKeyName())->toBe('reference');

    $this->actingAs($admin)
        ->get(route('admin.orders.show', $order))
        ->assertOk();
});

it('does not expose an order under its numeric id', function () {
    $order = makeOrder();
    $admin = User::factory()->create([
        'role' => 'super_admin',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get("/admin/orders/{$order->id}")
        ->assertNotFound();
});

it('finds an order by reference through admin search', function () {
    $order = makeOrder();
    $admin = User::factory()->create([
        'role' => 'super_admin',
        'email_verified_at' => now(),
    ]);

    // Lower-cased on purpose: references are stored uppercase, and an admin
    // retyping one should not have to match the casing.
    $this->actingAs($admin)
        ->get(route('admin.orders.index', ['search' => strtolower($order->reference)]))
        ->assertOk();
});
