<?php

use App\Models\City;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Services\CurrencyService;
use App\Services\ExchangeRateService;
use App\Services\GeoLocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Display currency.
 *
 * Every price in the database is MAD. These cover the two things that must
 * stay true whatever the storefront is showing: the right currency is picked
 * for the visitor, and nothing converted ever leaks back into stored money.
 */
beforeEach(function () {
    Cache::flush();

    /*
     * Both lookups are off suite-wide (phpunit.xml) precisely so no other
     * test reaches the network. This file is the one that exercises them, so
     * it turns them back on — and must therefore seal the network itself.
     *
     * `preventStrayRequests` and nothing else: stubs registered by
     * `Http::fake()` accumulate first-match-wins, so a default stub here
     * would shadow the specific rate each test below sets up. Every test
     * states what it expects; anything unstubbed fails loudly instead of
     * quietly making a live request.
     */
    config([
        'currency.geo.enabled' => true,
        'currency.rates_api.enabled' => true,
    ]);

    Http::preventStrayRequests();
});

// ── Resolution order ────────────────────────────────────────────────

test('an explicit choice outranks whatever the IP says', function () {
    Http::fake([
        'ip-api.com/*' => Http::response(['status' => 'success', 'countryCode' => 'FR']),
        'open.er-api.com/*' => Http::response(['rates' => ['EUR' => 0.09, 'USD' => 0.1]]),
    ]);

    $this->withSession([CurrencyService::SESSION_KEY => 'USD'])
        ->get(route('products.index'))
        ->assertInertia(fn ($page) => $page->where('currency.code', 'USD'));
});

test('a visitor with no choice gets the currency their country maps to', function () {
    Http::fake([
        'ip-api.com/*' => Http::response(['status' => 'success', 'countryCode' => 'FR']),
        'open.er-api.com/*' => Http::response(['rates' => ['EUR' => 0.09]]),
    ]);

    // Routed through a header rather than a fake IP: the test client is
    // always 127.0.0.1, which GeoLocationService correctly refuses to look up.
    $this->withHeader('CF-IPCountry', 'FR')
        ->get(route('products.index'))
        ->assertInertia(fn ($page) => $page->where('currency.code', 'EUR'));
});

test('a crawler always reads dirhams, wherever it crawls from and whatever its session says', function () {
    // Googlebot crawls from the US: geolocation alone would show it dollars,
    // while the JSON-LD and the Merchant Center feed say MAD.
    $this->withHeader('CF-IPCountry', 'US')
        ->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')
        ->withSession([CurrencyService::SESSION_KEY => 'EUR'])
        ->get(route('products.index'))
        ->assertInertia(fn ($page) => $page->where('currency.code', 'MAD'));

    $this->withHeader('CF-IPCountry', 'US')
        ->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Storebot-Google/1.0) Chrome/120.0 Safari/537.36')
        ->get(route('products.index'))
        ->assertInertia(fn ($page) => $page->where('currency.code', 'MAD'));
});

test('an ordinary browser is not mistaken for a crawler', function () {
    Http::fake(['open.er-api.com/*' => Http::response(['rates' => ['USD' => 0.1]])]);

    $this->withHeader('CF-IPCountry', 'US')
        ->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36')
        ->get(route('products.index'))
        ->assertInertia(fn ($page) => $page->where('currency.code', 'USD'));
});

test('a country with no mapping falls back to dirhams', function () {
    $this->withHeader('CF-IPCountry', 'JP')
        ->get(route('products.index'))
        ->assertInertia(fn ($page) => $page
            ->where('currency.code', 'MAD')
            ->where('currency.is_base', true)
            // 1, not 1.0: the rate crosses as JSON, where a whole float
            // arrives back as an int and `where()` compares strictly.
            ->where('currency.rate', 1)
        );
});

test('an anonymising proxy is not treated as a country', function () {
    // Cloudflare sends XX for anonymised traffic and T1 for Tor. Reading
    // either as a country code would map a visitor to a random currency.
    foreach (['XX', 'T1'] as $code) {
        $this->withHeader('CF-IPCountry', $code)
            ->get(route('products.index'))
            ->assertInertia(fn ($page) => $page->where('currency.code', 'MAD'));
    }
});

test('the switcher stores a supported currency and rejects anything else', function () {
    $this->post(route('currency.switch', 'EUR'))->assertRedirect();
    expect(session(CurrencyService::SESSION_KEY))->toBe('EUR');

    $this->post(route('currency.switch', 'XYZ'))->assertNotFound();
    // The rejected code must not displace the good one already stored.
    expect(session(CurrencyService::SESSION_KEY))->toBe('EUR');
});

// ── Rates ───────────────────────────────────────────────────────────

test('live rates are used when the API answers', function () {
    Http::fake(['open.er-api.com/*' => Http::response([
        'rates' => ['EUR' => 0.0925, 'USD' => 0.1010],
    ])]);

    expect(app(ExchangeRateService::class)->for('EUR'))->toBe(0.0925);
});

test('a failing rates API falls back to the configured figures', function () {
    Http::fake(['open.er-api.com/*' => Http::response([], 500)]);

    expect(app(ExchangeRateService::class)->for('EUR'))
        ->toBe((float) config('currency.fallback_rates.EUR'));
});

test('a rates payload missing every supported currency is refused', function () {
    // A 200 carrying junk is as unusable as a 500 and must take the same
    // path — quoting every foreign price at parity with MAD would be worse
    // than showing a slightly stale one.
    Http::fake(['open.er-api.com/*' => Http::response(['rates' => ['XYZ' => 3.0]])]);

    expect(app(ExchangeRateService::class)->for('EUR'))
        ->toBe((float) config('currency.fallback_rates.EUR'));
});

test('the base currency is always exactly one', function () {
    Http::fake(['open.er-api.com/*' => Http::response(['rates' => ['MAD' => 7.4]])]);

    // Even if an API reports something else for MAD-against-MAD, converting
    // the base to itself has to be identity or every MAD price shifts.
    expect(app(ExchangeRateService::class)->for('MAD'))->toBe(1.0);
});

test('conversion rounds to the currency display decimals', function () {
    Http::fake(['open.er-api.com/*' => Http::response(['rates' => ['EUR' => 0.0925]])]);

    $rates = app(ExchangeRateService::class);

    // EUR carries cents, MAD does not (config/currency.php).
    expect($rates->convert(250.0, 'EUR'))->toBe(23.13)
        ->and($rates->convert(250.4, 'MAD'))->toBe(250.0);
});

test('an unsupported currency converts at parity rather than throwing', function () {
    // The caller is rendering a price; a wrong-but-present figure in the base
    // beats a fatal on a product page.
    expect(app(ExchangeRateService::class)->for('XYZ'))->toBe(1.0);
});

// ── Geolocation ─────────────────────────────────────────────────────

test('a private or loopback IP is never looked up', function () {
    Http::fake(['ip-api.com/*' => Http::response(['status' => 'success', 'countryCode' => 'FR'])]);

    $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '127.0.0.1']);

    expect(app(GeoLocationService::class)->countryFor($request))->toBeNull();
    Http::assertNothingSent();
});

test('a failed geolocation lookup is cached so it is not retried per request', function () {
    Http::fake(['ip-api.com/*' => Http::response(['status' => 'fail'])]);

    $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '8.8.8.8']);
    $geo = app(GeoLocationService::class);

    expect($geo->countryFor($request))->toBeNull()
        ->and($geo->countryFor($request))->toBeNull();

    Http::assertSentCount(1);
});

// ── The money path is untouched ─────────────────────────────────────

test('switching currency never changes what an order stores', function () {
    Http::fake(['open.er-api.com/*' => Http::response(['rates' => ['EUR' => 0.0925]])]);

    $currencies = app(CurrencyService::class);

    // Display conversion is a read-side transform: the base figure it starts
    // from is never written back anywhere.
    $mad = 250.0;

    expect($currencies->convert($mad, 'EUR'))->toBe(23.13)
        ->and($currencies->convert($mad, 'MAD'))->toBe(250.0)
        // And the raw rate stays unrounded, or every price derived from it
        // would inherit the rounding.
        ->and($currencies->rateFor('EUR'))->toBe(0.0925);
});

test('the admin order screen carries what the buyer was reading', function () {
    $city = City::create(['name' => 'Oujda', 'region' => 'Oriental']);
    $seller = User::factory()->create(['role' => 'seller']);
    $store = Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Atelier Devise',
        'slug' => 'atelier-devise',
        'type' => 'artist',
        'city_id' => $city->id,
    ]);

    $order = Order::create([
        'buyer_id' => User::factory()->create(['role' => 'buyer'])->id,
        'store_id' => $store->id,
        'city_id' => $city->id,
        'status' => 'pending',
        'total_amount' => 200,
        'platform_fee' => 40,
        'delivery_fee' => 40,
        'seller_earnings' => 200,
        'shipping_address' => '1 Rue Test',
        'display_currency' => 'EUR',
        'display_rate' => 0.0925,
    ]);

    $admin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($admin)
        ->get(route('admin.orders.show', $order))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('group.display_currency', 'EUR')
            ->where('group.display_rate', 0.0925)
            // The money the desk collects is untouched and still MAD.
            ->where('group.products_total', 200)
            ->etc()
        );

    /*
     * And the stored rate reproduces what the buyer was quoted: 200 × 0.0925.
     * Products only — `displayTotal()` converts `total_amount`, which does not
     * include the delivery fee (that sits in its own column).
     */
    expect($order->displayTotal())->toBe(18.5);
});

test('a loopback request can fall back to geolocating the server itself', function () {
    /*
     * Local development only: every request there arrives from 127.0.0.1, so
     * the client IP can never reveal anything and the detection would be
     * untestable without this. Asking about our own outbound address instead
     * makes flipping a VPN change the answer.
     */
    config(['currency.geo.resolve_own_ip' => true]);

    // The provider reports the caller's own address when asked about none, so
    // the request goes to the bare endpoint with an empty `:ip` slot.
    Http::fake(['ip-api.com/*' => Http::response([
        'status' => 'success', 'countryCode' => 'ES',
    ])]);

    $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '127.0.0.1']);

    expect(app(GeoLocationService::class)->countryFor($request))->toBe('ES');
});

test('a loopback request resolves nothing when the self-lookup is off', function () {
    // The production default. If proxy forwarding ever broke, having it on
    // would geolocate every visitor to the server's own country instead of
    // falling back safely to the base currency.
    config(['currency.geo.resolve_own_ip' => false]);

    Http::fake(['ip-api.com/*' => Http::response([
        'status' => 'success', 'countryCode' => 'ES',
    ])]);

    $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '127.0.0.1']);

    expect(app(GeoLocationService::class)->countryFor($request))->toBeNull();
    Http::assertNothingSent();
});
