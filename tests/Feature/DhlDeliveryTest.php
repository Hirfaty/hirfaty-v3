<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\City;
use App\Models\DeliveryRate;
use App\Models\DeliveryService;
use App\Models\DeliveryWeightBand;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\DeliveryFeeService;
use App\Services\ProductShippingService;
use App\Services\WeightPricingService;
use Database\Seeders\DhlDeliveryRateSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * DHL Express abroad, Amana in Morocco: DHL's card (DhlDeliveryRateSeeder),
 * its weight pricing (WeightPricingService) and checkout choosing the carrier
 * by destination (DeliveryFeeService::serviceFor).
 */

/** Both carriers as a live database has them: Amana for Casablanca, DHL abroad. */
function dhlCarriers(): array
{
    $amana = DeliveryService::create(['slug' => 'amana', 'name' => 'Amana', 'sort_order' => 1]);
    $casa = City::create(['name' => 'Casablanca', 'region' => 'Casablanca-Settat']);

    DeliveryRate::create([
        'delivery_service_id' => $amana->id,
        'country_code' => 'MA',
        'country_name' => 'Maroc',
        'city_id' => $casa->id,
        'fee' => 40,
    ]);

    (new DhlDeliveryRateSeeder)->run();

    return [$amana, DeliveryService::where('slug', DhlDeliveryRateSeeder::SLUG)->firstOrFail(), $casa];
}

/** A published product with a shipping profile, in a store of its own. */
function dhlProduct(array $shipping = []): Product
{
    $seller = User::factory()->create(['role' => 'seller', 'email_verified_at' => now()]);
    $store = Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Atelier '.Str::random(4),
        'slug' => 'atelier-'.Str::random(8),
        'status' => 'active',
    ]);

    return Product::create(array_merge([
        'store_id' => $store->id,
        'name' => 'Vase',
        'slug' => 'vase-'.Str::random(8),
        'base_price' => 300,
        'stock' => 20,
        'status' => 'published',
    ], $shipping));
}

/** A verified buyer with `$product` × `$quantity` in their cart. */
function dhlBuyerWith(Product $product, int $quantity = 1): User
{
    $buyer = User::factory()->create(['role' => 'buyer', 'email_verified_at' => now()]);
    $cart = Cart::create(['user_id' => $buyer->id]);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => $quantity]);

    return $buyer;
}

/** @return array<string, mixed> */
function dhlCheckout(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Yasmine',
        'last_name' => 'Alaoui',
        'shipping_phone' => '+33600000000',
        'shipping_address' => '12 rue de la Paix, Paris',
        'postal_code' => '75002',
        'country' => 'FR',
        'payment_method' => 'card',
    ], $overrides);
}

/** A piece the way ProductShippingService::pieces() hands one over. */
function dhlPiece(?float $weight, ?array $dimensions = null): array
{
    $volumetric = $dimensions === null ? null
        : $dimensions['length'] * $dimensions['width'] * $dimensions['height'] / ProductShippingService::VOLUMETRIC_DIVISOR;

    return [
        'weight' => $weight,
        'chargeable' => $weight === null ? null : max($weight, $volumetric ?? 0),
        'dimensions' => $dimensions,
    ];
}

function dhlZoneBands(DeliveryService $dhl, int $zone)
{
    return DeliveryWeightBand::where('delivery_service_id', $dhl->id)->where('zone', $zone)->get();
}

// ── The card ────────────────────────────────────────────────────────────

test('the card puts every DHL destination in its zone', function () {
    [, $dhl] = dhlCarriers();

    expect($dhl->isWeightPriced())->toBeTrue()
        ->and(DeliveryRate::where('delivery_service_id', $dhl->id)->count())->toBe(229)
        ->and(DeliveryRate::where('delivery_service_id', $dhl->id)->whereNull('zone')->count())->toBe(0);

    $zoneOf = fn (string $code) => DeliveryRate::where('delivery_service_id', $dhl->id)
        ->where('country_code', $code)->value('zone');

    expect($zoneOf('FR'))->toBe(1)
        ->and($zoneOf('GB'))->toBe(2)
        ->and($zoneOf('DZ'))->toBe(3)
        ->and($zoneOf('US'))->toBe(4)
        ->and($zoneOf('JP'))->toBe(5)
        ->and($zoneOf('ZA'))->toBe(6)
        ->and($zoneOf('BR'))->toBe(7)
        // DHL's own codes are filed under the ISO code an address carries.
        ->and($zoneOf('CW'))->toBe(7)
        ->and($zoneOf('XK'))->toBe(6)
        ->and($zoneOf('XC'))->toBeNull();
});

test('re-running the card keeps the desk’s edits', function () {
    [, $dhl] = dhlCarriers();

    DeliveryRate::where('delivery_service_id', $dhl->id)->where('country_code', 'FR')->update(['zone' => 2]);
    $band = dhlZoneBands($dhl, 1)->firstWhere('up_to', 0.5);
    $band->update(['price' => 99]);

    (new DhlDeliveryRateSeeder)->run();

    expect(DeliveryRate::where('delivery_service_id', $dhl->id)->count())->toBe(229)
        ->and(DeliveryRate::where('country_code', 'FR')->where('delivery_service_id', $dhl->id)->value('zone'))->toBe(2)
        ->and((float) $band->fresh()->price)->toBe(99.0)
        ->and(DeliveryWeightBand::where('delivery_service_id', $dhl->id)->count())->toBe(7 * 25);
});

test('the grid reproduces DHL’s published prices', function (float $kg, array $prices) {
    [, $dhl] = dhlCarriers();
    $pricing = app(WeightPricingService::class);

    foreach ($prices as $index => $price) {
        expect(round($pricing->gridPrice(dhlZoneBands($dhl, $index + 1), $kg), 2))->toBe($price);
    }
})->with([
    '0.5 kg' => [0.5, [72.08, 100.70, 126.14, 172.78, 190.80, 225.78, 279.84]],
    '2.5 kg' => [2.5, [305.28, 510.92, 642.36, 778.04, 818.32, 915.84, 1034.56]],
    '10 kg' => [10.0, [1094.98, 1857.12, 2295.96, 2972.24, 3028.42, 3364.44, 3859.46]],
    '11 kg' => [11.0, [1162.82, 1990.68, 2459.20, 3211.80, 3278.58, 3625.20, 4205.02]],
    '15 kg' => [15.0, [1434.18, 2524.92, 3112.16, 4170.04, 4279.22, 4668.24, 5587.26]],
    '20 kg' => [20.0, [1773.38, 3192.72, 3928.36, 5367.84, 5530.02, 5972.04, 7315.06]],
    '21 kg' => [21.0, [1841.22, 3319.92, 4078.88, 5556.52, 5718.70, 6167.08, 7569.46]],
    '25 kg' => [25.0, [2112.58, 3828.72, 4680.96, 6311.24, 6473.42, 6947.24, 8587.06]],
    '30 kg' => [30.0, [2451.78, 4464.72, 5433.56, 7254.64, 7416.82, 7922.44, 9859.06]],
    '40 kg' => [40.0, [3140.78, 5302.12, 6800.96, 8622.04, 8784.22, 9480.64, 11830.66]],
    '50 kg' => [50.0, [3829.78, 6139.52, 8168.36, 9989.44, 10151.62, 11038.84, 13802.26]],
    '70 kg' => [70.0, [5207.78, 7814.32, 10903.16, 12724.24, 12886.42, 14155.24, 17745.46]],
]);

test('a weight between two lines is billed at the next one up', function () {
    [, $dhl] = dhlCarriers();
    $pricing = app(WeightPricingService::class);
    $zone1 = dhlZoneBands($dhl, 1);

    expect($pricing->gridPrice($zone1, 0.2))->toBe(72.08)
        ->and($pricing->gridPrice($zone1, 0.51))->toBe(136.74)
        // 10 kg + one started 0.5 kg step.
        ->and(round($pricing->gridPrice($zone1, 10.2), 2))->toBe(1128.90)
        // 70 kg + six started kg at the 70–300 kg rate.
        ->and(round($pricing->gridPrice($zone1, 75.5), 2))->toBe(5678.42)
        // Past 300 kg the last stretch's rate applies.
        ->and(round($pricing->gridPrice($zone1, 301), 2))->toBe(round(5207.78 + 230 * 78.44 + 83.74, 2));
});

// ── Weighing a basket ───────────────────────────────────────────────────

test('each unit is a piece, billed on the greater of its actual and volumetric weight', function () {
    $light = dhlProduct(['weight' => 0.8, 'length' => 40, 'width' => 30, 'height' => 20]);
    $heavy = dhlProduct(['weight' => 1500, 'weight_unit' => 'g', 'length' => 10, 'width' => 10, 'height' => 10]);

    $cart = Cart::create(['user_id' => User::factory()->create()->id]);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $light->id, 'quantity' => 2]);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $heavy->id, 'quantity' => 1]);

    $pieces = app(ProductShippingService::class)->pieces($cart->items()->with(['product', 'variant'])->get());
    $dhl = new DeliveryService(['default_piece_weight' => 1]);

    // 40 × 30 × 20 / 5000 = 4.8 kg beats 0.8 kg, twice; 1500 g is 1.5 kg.
    expect($pieces)->toHaveCount(3)
        ->and(app(WeightPricingService::class)->billedWeight($dhl, $pieces))->toBe(11.1);
});

test('a piece with no weight on file counts as the default weight, or its volume when bigger', function () {
    $dhl = new DeliveryService(['default_piece_weight' => 1]);
    $pricing = app(WeightPricingService::class);

    expect($pricing->billedWeight($dhl, [dhlPiece(null), dhlPiece(null)]))->toBe(2.0)
        ->and($pricing->billedWeight($dhl, [[
            'weight' => null,
            'chargeable' => null,
            'dimensions' => ['length' => 50, 'width' => 50, 'height' => 50],
        ]]))->toBe(25.0);
});

test('bulky and heavy pieces carry DHL’s handling surcharges', function () {
    $dhl = new DeliveryService(['handling_fee' => 225, 'overweight_fee' => 1124, 'default_piece_weight' => 1]);
    $pricing = app(WeightPricingService::class);

    $bowl = dhlPiece(1.0, ['length' => 30, 'width' => 30, 'height' => 15]);
    $rug = dhlPiece(8.0, ['length' => 200, 'width' => 40, 'height' => 40]);
    $wide = dhlPiece(5.0, ['length' => 90, 'width' => 85, 'height' => 10]);
    $table = dhlPiece(30.0, ['length' => 60, 'width' => 60, 'height' => 50]);
    $chest = dhlPiece(75.0, ['length' => 90, 'width' => 60, 'height' => 60]);

    expect($pricing->pieceSurcharges($dhl, [$bowl]))->toBe(0.0)
        // Longest side over 100 cm.
        ->and($pricing->pieceSurcharges($dhl, [$rug]))->toBe(225.0)
        // Second side over 80 cm.
        ->and($pricing->pieceSurcharges($dhl, [$wide]))->toBe(225.0)
        // Over 25 kg.
        ->and($pricing->pieceSurcharges($dhl, [$table]))->toBe(225.0)
        // Over 70 kg: the overweight fee, instead of handling.
        ->and($pricing->pieceSurcharges($dhl, [$chest]))->toBe(1124.0)
        ->and($pricing->pieceSurcharges($dhl, [$rug, $rug, $bowl]))->toBe(450.0);
});

test('the quote adds the fuel surcharge on transport and the fixed fee per shipment', function () {
    [, $dhl] = dhlCarriers();
    $pricing = app(WeightPricingService::class);
    $vase = [dhlPiece(1.2, ['length' => 30, 'width' => 20, 'height' => 10])];

    // 1.2 kg is billed at the 1.5 kg line: 192.92, plus 180 per shipment.
    expect($pricing->quoteForZone($dhl, 1, $vase))->toBe(372.92);

    $dhl->update(['fuel_surcharge_percent' => 20]);

    // Fuel on the 192.92 only, not on the fixed fee.
    expect($pricing->quoteForZone($dhl, 1, $vase))->toBe(411.50);

    // A rolled rug: billed on its 64 kg volume, plus handling for its length.
    $dhl->update(['fuel_surcharge_percent' => 0]);
    $rug = [dhlPiece(8.0, ['length' => 200, 'width' => 40, 'height' => 40])];

    expect($pricing->quoteForZone($dhl, 1, $rug))->toBe(round(2451.78 + 34 * 68.90 + 225 + 180, 2))
        ->and($pricing->quoteForZone($dhl, 9, $rug))->toBeNull();
});

// ── Checkout ────────────────────────────────────────────────────────────

test('checkout offers Morocco and every DHL country', function () {
    dhlCarriers();
    $fees = app(DeliveryFeeService::class);

    expect($fees->shippableCountryCodes())->toHaveCount(230)
        ->toContain('MA', 'FR', 'US', 'JP');
});

test('checkout quotes each DHL country for the basket on the page', function () {
    dhlCarriers();
    $product = dhlProduct(['weight' => 1.2, 'length' => 30, 'width' => 20, 'height' => 10]);

    $this->actingAs(dhlBuyerWith($product))
        ->get(route('checkout.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('buyer/checkout')
            ->where('deliveryServices.0.slug', 'amana')
            ->where('deliveryServices.1.slug', 'dhl')
            ->where('deliveryRates.dhl.countries', fn ($countries) => collect($countries)->firstWhere('code', 'FR')['fee'] === 372.92
                // Zone 4 at 1.5 kg: 493.96 + 180.
                && collect($countries)->firstWhere('code', 'US')['fee'] === 673.96)
            ->where('deliveryRates.dhl.cityFees', []));
});

test('a basket abroad ships with DHL, billed on its weight', function () {
    Notification::fake();
    dhlCarriers();
    $product = dhlProduct(['weight' => 1.2, 'length' => 30, 'width' => 20, 'height' => 10]);

    $this->actingAs(dhlBuyerWith($product))
        ->post(route('checkout.store'), dhlCheckout())
        ->assertSessionHasNoErrors();

    $order = Order::withoutGlobalScope(Order::SETTLED_SCOPE)->sole();

    expect((float) $order->delivery_fee)->toBe(372.92)
        ->and($order->delivery_service_name)->toBe('DHL Express');
});

test('a basket for Morocco still ships with Amana at its city rate', function () {
    Notification::fake();
    [, , $casa] = dhlCarriers();
    $product = dhlProduct(['weight' => 1.2]);

    $this->actingAs(dhlBuyerWith($product))
        ->post(route('checkout.store'), dhlCheckout([
            'country' => 'MA',
            'city_id' => $casa->id,
            'postal_code' => '20000',
        ]))
        ->assertSessionHasNoErrors();

    $order = Order::withoutGlobalScope(Order::SETTLED_SCOPE)->sole();

    expect((float) $order->delivery_fee)->toBe(40.0)
        ->and($order->delivery_service_name)->toBe('Amana');
});

test('a carrier that does not ship to the destination is refused, not swapped', function () {
    [$amana, $dhl, $casa] = dhlCarriers();
    $buyer = dhlBuyerWith(dhlProduct(['weight' => 1]));

    $this->actingAs($buyer)
        ->post(route('checkout.store'), dhlCheckout(['delivery_service_id' => $amana->id]))
        ->assertSessionHasErrors('delivery_service_id');

    $this->actingAs($buyer)
        ->post(route('checkout.store'), dhlCheckout([
            'country' => 'MA',
            'city_id' => $casa->id,
            'delivery_service_id' => $dhl->id,
        ]))
        ->assertSessionHasErrors('delivery_service_id');

    expect(Order::withoutGlobalScope(Order::SETTLED_SCOPE)->count())->toBe(0);
});

// ── Going live ──────────────────────────────────────────────────────────

test('the migration hands every country abroad to DHL on a live database, and skips a fresh one', function () {
    $migration = require database_path('migrations/2026_10_26_120100_load_dhl_express_rate_card.php');

    // A fresh database — every test starts as one — gets nothing.
    $migration->up();
    expect(DeliveryService::count())->toBe(0);

    $amana = DeliveryService::create(['slug' => 'amana', 'name' => 'Amana', 'sort_order' => 1]);
    $casa = City::create(['name' => 'Casablanca', 'region' => 'Casablanca-Settat']);
    $casaRate = DeliveryRate::create(['delivery_service_id' => $amana->id, 'country_code' => 'MA', 'country_name' => 'Maroc', 'city_id' => $casa->id, 'fee' => 40]);
    DeliveryRate::create(['delivery_service_id' => $amana->id, 'country_code' => 'FR', 'country_name' => 'France', 'city_id' => null, 'fee' => 20]);

    $migration->up();

    expect(DeliveryService::where('slug', 'dhl')->exists())->toBeTrue()
        ->and(DeliveryRate::where('delivery_service_id', $amana->id)->pluck('id')->all())->toBe([$casaRate->id])
        ->and(app(DeliveryFeeService::class)->servicesServing('FR', null)->pluck('slug')->all())->toBe(['dhl']);
});

// ── The admin desk ──────────────────────────────────────────────────────

test('the DHL card shows its zones, grid and pricing fees', function () {
    dhlCarriers();
    $admin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($admin)
        ->get(route('admin.delivery-rates.index', ['service' => 'dhl']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/delivery-rates/index')
            ->has('rates', 229)
            ->where('pricing.shipment_fee', 180)
            ->where('pricing.fuel_surcharge_percent', 0)
            ->where('grid.zones', [1, 2, 3, 4, 5, 6, 7])
            ->has('grid.brackets', 20)
            ->has('grid.steps', 5)
            ->where('grid.steps.0.from', 10)
            ->where('availableCities', []));
});

test('the desk moves a country to another zone, but only one with a grid', function () {
    [, $dhl] = dhlCarriers();
    $admin = User::factory()->create(['role' => 'super_admin']);
    $france = DeliveryRate::where('delivery_service_id', $dhl->id)->where('country_code', 'FR')->sole();

    $this->actingAs($admin)
        ->patch(route('admin.delivery-rates.update', $france), ['zone' => 3])
        ->assertSessionHasNoErrors();

    $this->actingAs($admin)
        ->patch(route('admin.delivery-rates.update', $france), ['zone' => 9])
        ->assertSessionHasErrors('zone');

    expect($france->fresh()->zone)->toBe(3);
});

test('the desk re-prices a line of the grid', function () {
    [, $dhl] = dhlCarriers();
    $admin = User::factory()->create(['role' => 'super_admin']);
    $band = dhlZoneBands($dhl, 1)->firstWhere('up_to', 1.5);

    $this->actingAs($admin)
        ->patch(route('admin.delivery-weight-bands.update', $band), ['price' => '200'])
        ->assertSessionHasNoErrors();

    $vase = [dhlPiece(1.2)];

    expect(app(WeightPricingService::class)->quoteForZone($dhl->fresh(), 1, $vase))->toBe(380.0);
});

test('the desk sets the fuel surcharge and the fees around the grid', function () {
    [$amana, $dhl] = dhlCarriers();
    $admin = User::factory()->create(['role' => 'super_admin']);

    $settings = [
        'fuel_surcharge_percent' => '27.5',
        'shipment_fee' => '180',
        'default_piece_weight' => '1.5',
        'handling_fee' => '214',
        'overweight_fee' => '1060',
    ];

    $this->actingAs($admin)
        ->patch(route('admin.delivery-services.pricing.update', $dhl), $settings)
        ->assertSessionHasNoErrors();

    $dhl->refresh();

    expect((float) $dhl->fuel_surcharge_percent)->toBe(27.5)
        ->and((float) $dhl->default_piece_weight)->toBe(1.5)
        ->and((float) $dhl->handling_fee)->toBe(214.0);

    $this->actingAs($admin)
        ->patch(route('admin.delivery-services.pricing.update', $dhl), [...$settings, 'fuel_surcharge_percent' => '150', 'default_piece_weight' => '0'])
        ->assertSessionHasErrors(['fuel_surcharge_percent', 'default_piece_weight']);

    // Amana prices per destination: it has no such fees.
    $this->actingAs($admin)
        ->patch(route('admin.delivery-services.pricing.update', $amana), $settings)
        ->assertNotFound();
});

test('a country added to DHL takes a zone, and DHL takes no Moroccan city', function () {
    [, $dhl] = dhlCarriers();
    $admin = User::factory()->create(['role' => 'super_admin']);
    DeliveryRate::where('delivery_service_id', $dhl->id)->where('country_code', 'PT')->delete();
    $fes = City::create(['name' => 'Fès', 'region' => 'Fès-Meknès']);

    $this->actingAs($admin)
        ->post(route('admin.delivery-rates.store'), [
            'delivery_service_id' => $dhl->id,
            'type' => 'country',
            'country_code' => 'pt',
            'country_name' => 'Portugal',
            'zone' => 2,
        ])
        ->assertSessionHasNoErrors();

    $portugal = DeliveryRate::where('delivery_service_id', $dhl->id)->where('country_code', 'PT')->sole();

    expect($portugal->zone)->toBe(2)
        ->and($portugal->fee)->toBeNull();

    $this->actingAs($admin)
        ->post(route('admin.delivery-rates.store'), [
            'delivery_service_id' => $dhl->id,
            'type' => 'city',
            'city_id' => $fes->id,
            'fee' => 40,
        ])
        ->assertSessionHasErrors('type');
});
