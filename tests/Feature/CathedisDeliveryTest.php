<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\City;
use App\Models\DeliveryRate;
use App\Models\DeliveryService;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\DeliveryFeeService;
use Database\Seeders\CathedisDeliveryRateSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Cathedis in Morocco: its card (CathedisDeliveryRateSeeder), the migration
 * that took Morocco over from Amana on a live database, and checkout billing
 * a Moroccan basket at the locality's Cathedis tariff.
 */

function cathedis(): DeliveryService
{
    (new CathedisDeliveryRateSeeder)->run();

    return DeliveryService::where('slug', CathedisDeliveryRateSeeder::SLUG)->firstOrFail();
}

function cathedisFee(DeliveryService $service, string $city): ?float
{
    $fee = DeliveryRate::where('delivery_service_id', $service->id)
        ->where('city_id', City::where('name', $city)->value('id'))
        ->value('fee');

    return $fee === null ? null : (float) $fee;
}

/** A verified buyer with one published product in their cart. */
function cathedisBuyer(): User
{
    $seller = User::factory()->create(['role' => 'seller', 'email_verified_at' => now()]);
    $store = Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Atelier '.Str::random(4),
        'slug' => 'atelier-'.Str::random(8),
        'status' => 'active',
    ]);
    $product = Product::create([
        'store_id' => $store->id,
        'name' => 'Tajine',
        'slug' => 'tajine-'.Str::random(8),
        'base_price' => 300,
        'stock' => 20,
        'status' => 'published',
    ]);

    $buyer = User::factory()->create(['role' => 'buyer', 'email_verified_at' => now()]);
    $cart = Cart::create(['user_id' => $buyer->id]);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

    return $buyer;
}

// ── The card ────────────────────────────────────────────────────────────

test('the card prices every Cathedis locality at its tier', function () {
    $service = cathedis();

    expect($service->isWeightPriced())->toBeFalse()
        ->and($service->is_active)->toBeTrue()
        ->and(DeliveryRate::where('delivery_service_id', $service->id)->count())->toBe(366)
        ->and(DeliveryRate::where('delivery_service_id', $service->id)->where('country_code', '<>', 'MA')->count())->toBe(0)
        ->and(cathedisFee($service, 'Casablanca'))->toBe(20.0)
        ->and(cathedisFee($service, 'Errahma'))->toBe(30.0)
        ->and(cathedisFee($service, 'Rabat'))->toBe(35.0)
        ->and(cathedisFee($service, 'Settat'))->toBe(40.0)
        ->and(cathedisFee($service, 'Zagora'))->toBe(45.0)
        ->and(City::where('name', 'Goulmima')->first()->nameFor('ar'))->toBe('كلميمة');
});

test('a city already in the registry is reused, not added twice', function () {
    $fes = City::create(['name' => 'Fes', 'region' => 'Fès-Meknès']);

    $service = cathedis();

    expect(City::where('name', 'Fès')->exists())->toBeFalse()
        ->and(cathedisFee($service, 'Fes'))->toBe(35.0)
        ->and(City::count())->toBe(366);
});

test('re-running the card keeps the desk’s edits', function () {
    $service = cathedis();
    $casa = City::where('name', 'Casablanca')->value('id');
    DeliveryRate::where('delivery_service_id', $service->id)->where('city_id', $casa)->update(['fee' => 25]);

    cathedis();

    expect(cathedisFee($service, 'Casablanca'))->toBe(25.0)
        ->and(DeliveryRate::where('delivery_service_id', $service->id)->count())->toBe(366);
});

// ── Checkout ────────────────────────────────────────────────────────────

test('a Moroccan basket ships with Cathedis at the city’s tariff', function () {
    Notification::fake();
    cathedis();

    $this->actingAs(cathedisBuyer())
        ->post(route('checkout.store'), [
            'first_name' => 'Yasmine',
            'last_name' => 'Alaoui',
            'shipping_phone' => '+212600000000',
            'shipping_address' => '12 rue Moulay Youssef',
            'postal_code' => '20000',
            'country' => 'MA',
            'city_id' => City::where('name', 'Casablanca')->value('id'),
            'payment_method' => 'card',
        ])
        ->assertSessionHasNoErrors();

    $order = Order::withoutGlobalScope(Order::SETTLED_SCOPE)->sole();

    expect((float) $order->delivery_fee)->toBe(20.0)
        ->and($order->delivery_service_name)->toBe('Cathedis');
});

// ── Going live ──────────────────────────────────────────────────────────

test('the migration hands Morocco to Cathedis on a live database, and skips a fresh one', function () {
    $migration = require database_path('migrations/2026_10_29_120000_load_cathedis_rate_card.php');

    // A fresh database — every test starts as one — gets nothing.
    $migration->up();
    expect(DeliveryService::count())->toBe(0);

    $amana = DeliveryService::create(['slug' => 'amana', 'name' => 'Amana', 'sort_order' => 1]);
    $casa = City::create(['name' => 'Casablanca', 'region' => 'Casablanca-Settat']);
    DeliveryRate::create(['delivery_service_id' => $amana->id, 'country_code' => 'MA', 'country_name' => 'Maroc', 'city_id' => $casa->id, 'fee' => 40]);

    $migration->up();

    $fees = app(DeliveryFeeService::class);

    expect($amana->fresh()->is_active)->toBeFalse()
        ->and($fees->servicesServing('MA', $casa->id)->pluck('slug')->all())->toBe(['cathedis'])
        ->and($fees->defaultService()->slug)->toBe('cathedis')
        ->and($fees->resolve('MA', $casa->id))->toBe(20.0)
        // Amana's card stays on file, switched off.
        ->and(DeliveryRate::where('delivery_service_id', $amana->id)->count())->toBe(1);

    $migration->down();

    expect($amana->fresh()->is_active)->toBeTrue()
        ->and(DeliveryService::where('slug', 'cathedis')->exists())->toBeFalse();
});

test('the admin delivery page leaves a switched-off carrier out', function () {
    DeliveryService::create(['slug' => 'amana', 'name' => 'Amana', 'sort_order' => 1, 'is_active' => false]);
    $service = cathedis();

    $this->actingAs(User::factory()->create(['role' => 'super_admin']))
        ->get(route('admin.delivery-rates.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('services', 1)
            ->where('services.0.slug', 'cathedis')
            ->where('selectedService', $service->id));
});
