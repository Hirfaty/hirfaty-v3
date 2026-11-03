<?php

use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\City;
use App\Models\DeliveryRate;
use App\Models\DeliveryService;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\CountryService;
use Database\Seeders\DhlDeliveryRateSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Countries at checkout: named in the visitor's language (CountryService),
 * and the new-address form opening on the visitor's own country when a
 * carrier ships there.
 */

beforeEach(function () {
    // Off in phpunit.xml; detection here reads only the CDN header, never the network.
    config(['currency.geo.enabled' => true, 'currency.geo.resolve_own_ip' => false]);

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

/** A verified buyer with one piece in their cart. */
function countryBuyer(): User
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
        'name' => 'Vase',
        'slug' => 'vase-'.Str::random(8),
        'base_price' => 300,
        'stock' => 20,
        'status' => 'published',
    ]);

    $buyer = User::factory()->create(['role' => 'buyer', 'email_verified_at' => now()]);
    $cart = Cart::create(['user_id' => $buyer->id]);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

    return $buyer;
}

/** Every country checkout offers, as the page merges the cards: code ⇒ name. */
function offeredCountries(array $rates): array
{
    return collect($rates)
        ->flatMap(fn (array $card) => $card['countries'])
        ->mapWithKeys(fn (array $country) => [$country['code'] => $country['name']])
        ->all();
}

// ── Names ───────────────────────────────────────────────────────────────

test('checkout names every country in the visitor’s language', function (string $locale, array $expected) {
    $this->actingAs(countryBuyer())
        ->withSession(['locale' => $locale])
        ->get(route('checkout.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('buyer/checkout')
            ->where('deliveryRates', function ($rates) use ($expected) {
                $names = offeredCountries($rates->toArray());

                foreach ($expected as $code => $name) {
                    expect($names[$code] ?? null)->toBe($name);
                }

                return true;
            }));
})->with([
    'French reads the rate card' => ['fr', ['MA' => 'Maroc', 'DE' => 'Allemagne', 'US' => 'États-Unis', 'HK' => 'Hong Kong']],
    'Arabic' => ['ar', ['MA' => 'المغرب', 'DE' => 'ألمانيا', 'FR' => 'فرنسا', 'HK' => 'هونغ كونغ']],
    'English' => ['en', ['MA' => 'Morocco', 'DE' => 'Germany', 'US' => 'United States', 'HK' => 'Hong Kong']],
]);

test('the country list is sorted in the visitor’s language', function () {
    $this->actingAs(countryBuyer())
        ->withSession(['locale' => 'en'])
        ->get(route('checkout.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('deliveryRates.dhl.countries', function ($countries) {
                $names = collect($countries)->pluck('name');

                // Alphabetical in English: "Germany" under G, not under A as "Allemagne".
                expect($names->first())->toBe('Afghanistan')
                    ->and($names->search('Germany'))->toBeGreaterThan($names->search('France'))
                    ->and($names->search('Germany'))->toBeLessThan($names->search('Greece'));

                return true;
            }));
});

test('the French name stays the one on the rate card, whatever the visitor reads', function () {
    $this->actingAs(countryBuyer())
        ->withSession(['locale' => 'ar'])
        ->get(route('checkout.show'))
        ->assertOk();

    expect(DeliveryRate::where('country_code', 'DE')->value('country_name'))->toBe('Allemagne');
});

test('a saved address abroad carries its country name in the visitor’s language', function () {
    $buyer = countryBuyer();
    Address::create([
        'user_id' => $buyer->id,
        'address_line' => '12 rue de la Paix',
        'city_id' => null,
        'country' => 'FR',
        'is_default' => true,
    ]);

    $this->actingAs($buyer)
        ->withSession(['locale' => 'en'])
        ->get(route('checkout.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('addresses.0.country', 'FR')
            ->where('addresses.0.country_name', 'France'));
});

test('a code ICU cannot name falls back to the stored name, then the code', function () {
    $countries = app(CountryService::class);

    expect($countries->name('QQ', 'en', 'Pays inconnu'))->toBe('Pays inconnu')
        ->and($countries->name('ZZ', 'en', 'Pays inconnu'))->toBe('Pays inconnu')
        ->and($countries->name('QQ', 'ar'))->toBe('QQ')
        ->and($countries->name('es', 'ar'))->toBe('إسبانيا');
});

// ── Preselected country ─────────────────────────────────────────────────

test('a new address opens on the visitor’s own country when checkout ships there', function () {
    $this->actingAs(countryBuyer())
        ->withHeader('CF-IPCountry', 'DE')
        ->get(route('checkout.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('initialCountry', 'DE')
            ->where('defaultCountry', 'MA'));
});

test('a visitor from a country checkout does not serve opens on Morocco', function () {
    DeliveryRate::where('country_code', 'DE')->delete();

    $this->actingAs(countryBuyer())
        ->withHeader('CF-IPCountry', 'DE')
        ->get(route('checkout.show'))
        ->assertInertia(fn (Assert $page) => $page->where('initialCountry', 'MA'));
});

test('checkout opens on Morocco when the region cannot be detected', function (?string $header) {
    $request = $this->actingAs(countryBuyer());

    if ($header !== null) {
        $request = $request->withHeader('CF-IPCountry', $header);
    }

    $request->get(route('checkout.show'))
        ->assertInertia(fn (Assert $page) => $page->where('initialCountry', 'MA'));
})->with([
    'no header, local IP' => [null],
    'anonymising proxy' => ['XX'],
    'Tor' => ['T1'],
]);

test('detection switched off opens on Morocco', function () {
    config(['currency.geo.enabled' => false]);

    $this->actingAs(countryBuyer())
        ->withHeader('CF-IPCountry', 'DE')
        ->get(route('checkout.show'))
        ->assertInertia(fn (Assert $page) => $page->where('initialCountry', 'MA'));
});

test('a saved address keeps its own country whatever the region says', function () {
    $buyer = countryBuyer();
    Address::create([
        'user_id' => $buyer->id,
        'address_line' => '5 rue Atlas',
        'city_id' => $this->casa->id,
        'country' => 'MA',
        'is_default' => true,
    ]);

    $this->actingAs($buyer)
        ->withHeader('CF-IPCountry', 'DE')
        ->get(route('checkout.show'))
        ->assertInertia(fn (Assert $page) => $page
            // The page preselects the saved address; initialCountry only seeds the blank form.
            ->where('addresses.0.country', 'MA')
            ->where('addresses.0.is_default', true));
});
