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
use Database\Seeders\DhlDeliveryRateSeeder;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Delivery abroad priced with DHL Express's own rates for the account
 * (DhlRateService): the page asks checkout.delivery_quote once it knows the
 * destination, and checkout bills the same figure. When DHL cannot answer,
 * both fall back on the rate card.
 */

beforeEach(function () {
    config([
        'services.dhl_express.url' => 'https://express.api.dhl.com/mydhlapi/test',
        'services.dhl_express.username' => 'hirfatyMA',
        'services.dhl_express.password' => 'secret',
        'services.dhl_express.account_number' => '950000000',
        'services.dhl_express.product_code' => 'P',
        'services.dhl_express.live_rates' => true,
        'services.dhl_express.shipper' => [
            'company' => 'Hirfaty', 'name' => 'Desk', 'phone' => '+212522000000', 'email' => 'desk@hirfaty.test',
            'address' => '10 Rue des Artisans', 'city' => 'Casablanca', 'postal_code' => '20000', 'country' => 'MA',
        ],
    ]);

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

/** A buyer with one 5 kg, 40×40×10 cm piece in their cart. */
function liveRateBuyer(int $quantity = 1): User
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
        'name' => 'Plateau',
        'slug' => 'plateau-'.Str::random(8),
        'base_price' => 900,
        'stock' => 20,
        'status' => 'published',
        'weight' => 5,
        'weight_unit' => 'kg',
        'length' => 40,
        'width' => 40,
        'height' => 10,
    ]);

    $buyer = User::factory()->create(['role' => 'buyer', 'email_verified_at' => now()]);
    $cart = Cart::create(['user_id' => $buyer->id]);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => $quantity]);

    return $buyer;
}

/** DHL's rates answer: Express Worldwide at `$price` MAD, beside a dearer product. */
function fakeDhlRates(float $price = 1215.9, int $status = 200): void
{
    Http::fake(['express.api.dhl.com/mydhlapi/test/rates' => $status === 200
        ? Http::response(['products' => [
            ['productCode' => 'M', 'productName' => 'EXPRESS 10:30', 'totalPrice' => [['currencyType' => 'BILLC', 'priceCurrency' => 'MAD', 'price' => 1397.8]]],
            ['productCode' => 'P', 'productName' => 'EXPRESS WORLDWIDE', 'totalPrice' => [
                ['currencyType' => 'BILLC', 'priceCurrency' => 'MAD', 'price' => $price],
                ['currencyType' => 'BASEC', 'priceCurrency' => 'EUR', 'price' => 110.52],
            ]],
        ]])
        : Http::response(['detail' => '996: The requested product(s) not available'], $status)]);
}

test('the page is told which carrier it prices live', function () {
    fakeDhlRates();

    $this->actingAs(liveRateBuyer())
        ->get(route('checkout.show'))
        ->assertInertia(fn (Assert $page) => $page->where('liveQuotedServices', ['dhl'])->etc());

    config(['services.dhl_express.live_rates' => false]);

    $this->actingAs(liveRateBuyer())
        ->get(route('checkout.show'))
        ->assertInertia(fn (Assert $page) => $page->where('liveQuotedServices', [])->etc());
});

test('abroad, the quote is DHL’s own price for the basket', function () {
    fakeDhlRates();

    $this->actingAs(liveRateBuyer(quantity: 2))
        ->getJson(route('checkout.delivery_quote', ['country' => 'FR', 'postal_code' => '75001']))
        ->assertOk()
        ->assertJson(['fee' => 1215.9, 'live' => true]);

    Http::assertSent(function (HttpRequest $request) {
        $receiver = $request['customerDetails']['receiverDetails'];

        return $request->method() === 'POST'
            && $request['productCode'] === 'P'
            && $request['nextBusinessDay'] === true
            && $request['accounts'] === [['typeCode' => 'shipper', 'number' => '950000000']]
            && $request['customerDetails']['shipperDetails']['countryCode'] === 'MA'
            && $receiver['countryCode'] === 'FR' && $receiver['postalCode'] === '75001'
            // One box per unit, at its weight and dimensions.
            && $request['packages'] == [
                ['weight' => 5, 'dimensions' => ['length' => 40, 'width' => 40, 'height' => 10]],
                ['weight' => 5, 'dimensions' => ['length' => 40, 'width' => 40, 'height' => 10]],
            ];
    });
});

test('before the postcode is typed, DHL is asked for the capital, so the price shows at once', function () {
    fakeDhlRates();

    $this->actingAs(liveRateBuyer())
        ->getJson(route('checkout.delivery_quote', ['country' => 'FR']))
        ->assertJson(['fee' => 1215.9, 'live' => true]);

    Http::assertSent(fn (HttpRequest $request) => $request['customerDetails']['receiverDetails'] === [
        'postalCode' => '', 'cityName' => 'Paris', 'countryCode' => 'FR',
    ]);
});

test('a postcode DHL cannot place is priced at the capital instead', function () {
    Http::fake(['express.api.dhl.com/mydhlapi/test/rates' => Http::sequence()
        ->push(['detail' => '420506: Invalid Postcode Format.'], 400)
        ->push(['products' => [['productCode' => 'P', 'totalPrice' => [['currencyType' => 'BILLC', 'priceCurrency' => 'MAD', 'price' => 1786.71]]]]]),
    ]);

    $this->actingAs(liveRateBuyer())
        ->getJson(route('checkout.delivery_quote', ['country' => 'GB', 'postal_code' => 'NOT A CODE']))
        ->assertJson(['fee' => 1786.71, 'live' => true]);

    Http::assertSentCount(2);
    Http::assertSent(fn (HttpRequest $request) => $request['customerDetails']['receiverDetails']['cityName'] === 'London');
});

test('checkout bills the price the page showed, without asking DHL again', function () {
    Notification::fake();
    fakeDhlRates();
    $buyer = liveRateBuyer();

    $this->actingAs($buyer)
        ->getJson(route('checkout.delivery_quote', ['country' => 'FR', 'postal_code' => '75001']))
        ->assertJson(['fee' => 1215.9]);

    $this->actingAs($buyer)->post(route('checkout.store'), [
        'first_name' => 'Claire',
        'last_name' => 'Martin',
        'shipping_phone' => '+33600000000',
        'shipping_address' => '10 rue de Rivoli, Paris',
        'postal_code' => '75001',
        'city_name' => 'Paris',
        'country' => 'FR',
        'payment_method' => 'card',
    ])->assertSessionHasNoErrors();

    $order = Order::withoutGlobalScope(Order::SETTLED_SCOPE)->sole();

    expect((float) $order->delivery_fee)->toBe(1215.9)
        ->and($order->delivery_service_name)->toBe('DHL Express');
    Http::assertSentCount(1);
});

test('when DHL cannot answer, the rate card prices the basket on the page and on the bill', function () {
    Notification::fake();
    fakeDhlRates(status: 404);
    $buyer = liveRateBuyer();

    $card = $this->actingAs($buyer)
        ->getJson(route('checkout.delivery_quote', ['country' => 'FR', 'postal_code' => '75001']))
        ->assertOk()
        ->assertJson(['live' => false])
        ->json('fee');

    expect($card)->toBeGreaterThan(0)->not->toBe(1215.9);

    $this->actingAs($buyer)->post(route('checkout.store'), [
        'first_name' => 'Claire',
        'last_name' => 'Martin',
        'shipping_phone' => '+33600000000',
        'shipping_address' => '10 rue de Rivoli, Paris',
        'postal_code' => '75001',
        'city_name' => 'Paris',
        'country' => 'FR',
        'payment_method' => 'card',
    ])->assertSessionHasNoErrors();

    expect((float) Order::withoutGlobalScope(Order::SETTLED_SCOPE)->sole()->delivery_fee)->toBe((float) $card);
});

test('Morocco is still priced per city, without asking DHL', function () {
    fakeDhlRates();

    $this->actingAs(liveRateBuyer())
        ->getJson(route('checkout.delivery_quote', ['country' => 'MA', 'city_id' => $this->casa->id]))
        ->assertOk()
        ->assertJson(['fee' => 20, 'live' => false]);

    Http::assertNothingSent();
});

test('the quote is refused for a country nothing ships to', function () {
    fakeDhlRates();

    $this->actingAs(liveRateBuyer())
        ->getJson(route('checkout.delivery_quote', ['country' => 'ZZ']))
        ->assertUnprocessable();

    Http::assertNothingSent();
});
