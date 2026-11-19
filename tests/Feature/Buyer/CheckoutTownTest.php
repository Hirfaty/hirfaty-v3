<?php

use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\City;
use App\Models\DeliveryRate;
use App\Models\DeliveryService;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\DhlDeliveryRateSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The town of an address abroad: asked beside the country at checkout (no
 * city list exists outside Morocco), suggested from DHL by postcode, and kept
 * on the order (`shipping_city`) and the saved address (`city_name`).
 */

beforeEach(function () {
    Notification::fake();
    Http::preventStrayRequests();

    $cathedis = DeliveryService::create(['slug' => 'cathedis', 'name' => 'Cathedis', 'sort_order' => 1]);
    $this->casa = City::create(['name' => 'Casablanca', 'region' => 'Casablanca-Settat']);
    DeliveryRate::create([
        'delivery_service_id' => $cathedis->id,
        'country_code' => 'MA',
        'country_name' => 'Maroc',
        'city_id' => $this->casa->id,
        'fee' => 20,
    ]);

    (new DhlDeliveryRateSeeder)->run();
});

function townBuyer(): User
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
        'name' => 'Bol',
        'slug' => 'bol-'.Str::random(8),
        'base_price' => 200,
        'stock' => 20,
        'status' => 'published',
        'weight' => 1,
        'weight_unit' => 'kg',
    ]);

    $buyer = User::factory()->create(['role' => 'buyer', 'email_verified_at' => now()]);
    $cart = Cart::create(['user_id' => $buyer->id]);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

    return $buyer;
}

/** @return array<string, mixed> */
function abroadCheckout(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Claire',
        'last_name' => 'Martin',
        'shipping_phone' => '+33600000000',
        'shipping_address' => '10 rue de Rivoli',
        'postal_code' => '75001',
        'city_name' => '  Paris ',
        'country' => 'FR',
        'payment_method' => 'card',
    ], $overrides);
}

test('an address abroad needs its town', function () {
    $this->actingAs(townBuyer())
        ->from(route('checkout.show'))
        ->post(route('checkout.store'), abroadCheckout(['city_name' => '']))
        ->assertSessionHasErrors(['city_name' => 'Indiquez la ville.']);

    expect(Order::withoutGlobalScope(Order::SETTLED_SCOPE)->count())->toBe(0);
});

test('the order keeps the town abroad, on its own and on the label line', function () {
    $this->actingAs(townBuyer())
        ->post(route('checkout.store'), abroadCheckout(['save_address' => true]))
        ->assertSessionHasNoErrors();

    $order = Order::withoutGlobalScope(Order::SETTLED_SCOPE)->sole();

    expect($order->shipping_city)->toBe('Paris')
        ->and($order->shipping_address)->toBe('Claire Martin, 10 rue de Rivoli, Paris 75001')
        ->and($order->shipping_country)->toBe('FR');

    $address = Address::sole();
    expect($address->city_name)->toBe('Paris')
        ->and($address->city_id)->toBeNull()
        ->and($address->toShippingLine())->toBe('Claire Martin, 10 rue de Rivoli, Paris 75001');
});

test('a saved address abroad brings its town back to the next checkout', function () {
    $buyer = townBuyer();
    $address = $buyer->addresses()->create([
        'recipient_name' => 'Claire Martin',
        'address_line' => '10 rue de Rivoli',
        'city_name' => 'Paris',
        'postal_code' => '75001',
        'country' => 'FR',
        'is_default' => true,
    ]);

    $this->actingAs($buyer)
        ->get(route('checkout.show'))
        ->assertInertia(fn (Assert $page) => $page->where('addresses.0.city_name', 'Paris')->etc());

    $this->actingAs($buyer)->post(route('checkout.store'), [
        'first_name' => 'Claire',
        'last_name' => 'Martin',
        'shipping_phone' => '+33600000000',
        'address_id' => $address->id,
        'payment_method' => 'card',
    ])->assertSessionHasNoErrors();

    expect(Order::withoutGlobalScope(Order::SETTLED_SCOPE)->sole()->shipping_city)->toBe('Paris');
});

test('Morocco names its city from the list, with no town to type', function () {
    $this->actingAs(townBuyer())->post(route('checkout.store'), [
        'first_name' => 'Yasmine',
        'last_name' => 'Alaoui',
        'shipping_phone' => '0600000000',
        'shipping_address' => '2 rue Ibn Battouta',
        'postal_code' => '20000',
        'city_id' => $this->casa->id,
        'city_name' => 'ignored',
        'country' => 'MA',
        'payment_method' => 'card',
    ])->assertSessionHasNoErrors();

    $order = Order::withoutGlobalScope(Order::SETTLED_SCOPE)->sole();

    expect($order->shipping_city)->toBe('Casablanca')
        ->and($order->city_id)->toBe($this->casa->id);
});

test('checkout suggests the towns DHL knows for a postcode', function () {
    config([
        'services.dhl_express.url' => 'https://express.api.dhl.com/mydhlapi/test',
        'services.dhl_express.username' => 'hirfatyMA',
        'services.dhl_express.password' => 'secret',
        'services.dhl_express.account_number' => '950000000',
    ]);
    Http::fake(['express.api.dhl.com/mydhlapi/test/address-validate*' => Http::response(['address' => [
        ['countryCode' => 'FR', 'postalCode' => '69001', 'cityName' => 'LYON'],
        ['countryCode' => 'FR', 'postalCode' => '69001', 'cityName' => 'LYON 01'],
    ]])]);

    $this->actingAs(townBuyer())
        ->getJson(route('checkout.towns', ['country' => 'FR', 'postal_code' => '69001']))
        ->assertOk()
        ->assertExactJson(['towns' => ['Lyon', 'Lyon 01']]);
});

test('without DHL, the town field simply has no suggestions', function () {
    $this->actingAs(townBuyer())
        ->getJson(route('checkout.towns', ['country' => 'FR', 'postal_code' => '69001']))
        ->assertOk()
        ->assertExactJson(['towns' => []]);

    Http::assertNothingSent();
});
