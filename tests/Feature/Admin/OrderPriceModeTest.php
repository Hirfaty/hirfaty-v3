<?php

use App\Models\Cart;
use App\Models\City;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Whether a purchase was bought at Particulier or Entreprise prices: written
 * on every order by CheckoutService from the buyer's choice, and shown to the
 * desk on the orders list, the order page and in the export.
 */
beforeEach(function () {
    $seller = User::factory()->create(['role' => 'seller', 'email_verified_at' => now()]);

    $this->store = Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Atelier Grille',
        'slug' => 'atelier-grille-'.Str::random(6),
        'status' => 'active',
    ]);

    $this->product = Product::create([
        'store_id' => $this->store->id,
        'name' => 'Lanterne',
        'slug' => 'lanterne-'.Str::random(8),
        'base_price' => 200,
        'professional_price' => 120,
        'min_order_qty_professional' => 2,
        'stock' => 50,
        'status' => 'published',
        'visibility' => 'both',
    ]);

    $this->admin = User::factory()->create(['role' => 'super_admin']);
});

/** Check out two pieces as `$buyer`, on whatever list the test set. */
function checkoutTwo(User $buyer): void
{
    $city = City::create(['name' => 'Fès '.Str::random(4), 'region' => 'Fès-Meknès']);
    Cart::firstOrCreate(['user_id' => $buyer->id])
        ->items()->create(['product_id' => test()->product->id, 'quantity' => 2]);

    test()->actingAs($buyer)->post(route('checkout.store'), [
        'first_name' => 'Yasmine',
        'last_name' => 'Alaoui',
        'shipping_address' => '1 Rue Test',
        'city_id' => $city->id,
        'postal_code' => '30000',
        'country' => 'MA',
        'shipping_phone' => '0600000000',
        'payment_method' => 'card',
    ])->assertSessionHasNoErrors();
}

test('checkout records a personal purchase as such', function () {
    $buyer = User::factory()->create(['role' => 'buyer', 'email_verified_at' => now()]);

    checkoutTwo($buyer);

    $order = Order::withoutGlobalScope(Order::SETTLED_SCOPE)->first();

    expect($order->price_mode)->toBe('individual')
        ->and((float) $order->total_amount)->toBe(400.0);
});

test('checkout records a business purchase as such', function () {
    $buyer = proBuyer();

    checkoutTwo($buyer);

    $order = Order::withoutGlobalScope(Order::SETTLED_SCOPE)->first();

    expect($order->price_mode)->toBe('professional')
        ->and((float) $order->total_amount)->toBe(240.0);
});

test('the desk sees the price list on the order page', function () {
    checkoutTwo(proBuyer());
    confirmCardPayment();

    $order = Order::first();

    $this->actingAs($this->admin)
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('group.price_mode', 'professional'));
});

test('the orders list filters on the price list', function () {
    $order = fn (?string $mode) => Order::create([
        'buyer_id' => User::factory()->create(['role' => 'buyer'])->id,
        'store_id' => $this->store->id,
        'status' => 'pending',
        'total_amount' => 100,
        'platform_fee' => 20,
        'delivery_fee' => 0,
        'seller_earnings' => 80,
        'shipping_address' => 'Rue',
        'price_mode' => $mode,
    ]);

    $personal = $order('individual');
    $business = $order('professional');
    $unknown = $order(null);

    $references = fn (string $mode) => $this->actingAs($this->admin)
        ->get(route('admin.orders.index', ['price_mode' => $mode]))
        ->assertOk()
        ->inertiaProps('groups');

    expect(collect($references('professional'))->pluck('reference')->all())->toBe([$business->reference])
        ->and(collect($references('individual'))->pluck('reference')->all())->toBe([$personal->reference])
        ->and(collect($references('unknown'))->pluck('reference')->all())->toBe([$unknown->reference]);
});

test('the orders export carries the price list', function () {
    checkoutTwo(proBuyer());
    confirmCardPayment();

    $csv = $this->actingAs($this->admin)
        ->get(route('admin.orders.export', ['format' => 'csv']))
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain('Grille de prix')
        ->and($csv)->toContain('Entreprise');
});
