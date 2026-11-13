<?php

use App\Models\Cart;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\PriceModeService;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * "Particulier" or "pro" (PriceModeService): a choice anyone makes, which
 * decides what the storefront quotes, what is listed, what checkout bills,
 * and — in pro mode — the per-piece minimum quantity.
 */
beforeEach(function () {
    $seller = User::factory()->create(['role' => 'seller', 'email_verified_at' => now()]);

    $this->store = Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Atelier Mode',
        'slug' => 'atelier-mode-'.Str::random(6),
        'status' => 'active',
    ]);
});

function modeProduct(array $attributes = []): Product
{
    return Product::create(array_merge([
        'store_id' => test()->store->id,
        'name' => 'Lanterne',
        'slug' => 'lanterne-'.Str::random(8),
        'base_price' => 200,
        'professional_price' => 120,
        'min_order_qty_professional' => 10,
        'stock' => 50,
        'status' => 'published',
        'visibility' => 'both',
    ], $attributes));
}

test('a first-time visitor is asked, and browses at retail until they answer', function () {
    $product = modeProduct();

    $this->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('priceMode.mode', 'individual')
            ->where('priceMode.needs_choice', true)
            ->missing('product.professional_effective_price')
        );
});

test('choosing a mode stores it in a long-lived cookie', function () {
    $this->post(route('price-mode.switch', 'professional'))
        ->assertRedirect()
        ->assertCookie(PriceModeService::COOKIE, 'professional');
});

test('an unknown mode is refused', function () {
    // The route constraint turns it away before the controller is reached.
    $this->post('/price-mode/wholesale')
        ->assertClientError()
        ->assertCookieMissing(PriceModeService::COOKIE);
});

test('pro mode quotes the trade price and the minimum', function () {
    $product = modeProduct();

    shopInProMode();

    $this->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('priceMode.mode', 'professional')
            ->where('priceMode.needs_choice', false)
            ->where('product.professional_effective_price', 120)
            ->where('product.min_order_qty_professional', 10)
        );
});

test('pro mode lists trade-only pieces and hides consumer-only ones', function () {
    modeProduct(['name' => 'Trade only', 'visibility' => 'professional']);
    modeProduct(['name' => 'Consumer only', 'visibility' => 'individual']);
    modeProduct(['name' => 'Everyone']);

    $this->withCookie(PriceModeService::COOKIE, 'professional')
        ->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('audience', 'professional')
            ->has('products', 2)
            ->where('products', fn ($products) => collect($products)->pluck('name')->sort()->values()->all() === ['Everyone', 'Trade only'])
        );

    $this->withCookie(PriceModeService::COOKIE, 'individual')
        ->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('audience', 'individual')
            ->where('products', fn ($products) => collect($products)->pluck('name')->sort()->values()->all() === ['Consumer only', 'Everyone'])
        );
});

test('any buyer in pro mode buys at the trade price, from the minimum', function () {
    $buyer = proBuyer();
    $product = modeProduct();

    // Under the floor: refused, naming it.
    $this->actingAs($buyer)
        ->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 1])
        ->assertSessionHasErrors('quantity');

    // At the floor: in, at the trade price.
    $this->actingAs($buyer)
        ->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 10])
        ->assertSessionHasNoErrors();

    $this->actingAs($buyer)
        ->get(route('cart.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('cart.items.0.price', 120)
            ->where('cart.items.0.qty', 10)
            ->where('items.0.min_quantity', 10)
        );
});

test('personal prices start from one piece', function () {
    $buyer = User::factory()->create(['role' => 'buyer', 'email_verified_at' => now()]);
    $product = modeProduct();

    $this->actingAs($buyer)
        ->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 1])
        ->assertSessionHasNoErrors();

    $this->actingAs($buyer)
        ->get(route('cart.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('cart.items.0.price', 200)
            ->where('items.0.min_quantity', 1)
        );
});

test('a trade-only piece can only be bought in pro mode', function () {
    $buyer = User::factory()->create(['role' => 'buyer', 'email_verified_at' => now()]);
    $product = modeProduct(['visibility' => 'professional']);

    $this->actingAs($buyer)
        ->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 10])
        ->assertSessionHasErrors('product_id');

    expect(Cart::where('user_id', $buyer->id)->first()?->items()->count() ?? 0)->toBe(0);

    shopInProMode();

    $this->actingAs($buyer)
        ->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 10])
        ->assertSessionHasNoErrors();
});

test('buy now holds a pro-mode buyer to the minimum too', function () {
    $buyer = proBuyer();
    $product = modeProduct();

    $this->actingAs($buyer)
        ->post(route('checkout.buy_now.start'), ['product_id' => $product->id, 'quantity' => 2])
        ->assertSessionHasErrors('quantity');

    $this->actingAs($buyer)
        ->post(route('checkout.buy_now.start'), ['product_id' => $product->id, 'quantity' => 10])
        ->assertRedirect(route('checkout.buy_now'));
});

test('crawlers always read retail and are never asked', function () {
    $product = modeProduct();

    $this->withCookie(PriceModeService::COOKIE, 'professional')
        ->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')
        ->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('priceMode.mode', 'individual')
            ->where('priceMode.needs_choice', false)
            ->missing('product.professional_effective_price')
        );
});

test('sellers are not asked who they are buying for', function () {
    $seller = User::factory()->create(['role' => 'seller', 'email_verified_at' => now()]);
    $product = modeProduct();

    $this->actingAs($seller)
        ->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('priceMode.needs_choice', false));
});

test('search suggestions quote the price list being browsed', function () {
    modeProduct(['name' => 'Lanterne cuivre']);

    // withCredentials: a JSON test request sends no cookies otherwise, and
    // the browser's same-origin fetch does.
    $this->withCredentials()
        ->withCookie(PriceModeService::COOKIE, 'professional')
        ->getJson(route('search.suggestions', ['q' => 'lanterne']))
        ->assertOk()
        ->assertJsonPath('products.0.price', 120);

    $this->withCredentials()
        ->withCookie(PriceModeService::COOKIE, 'individual')
        ->getJson(route('search.suggestions', ['q' => 'lanterne']))
        ->assertOk()
        ->assertJsonPath('products.0.price', 200);
});

test('personal prices show what the piece costs a business', function () {
    $product = modeProduct();

    $this->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('proOffer.price', 120)
            ->where('proOffer.retail', 200)
            ->where('proOffer.min_qty', 10)
            // The page's own price is still the personal one.
            ->missing('product.professional_effective_price')
        );
});

test('no business offer in pro mode, for crawlers, or without a trade rate', function () {
    $product = modeProduct();
    $retailOnly = modeProduct(['professional_price' => null]);

    // No trade rate below retail: nothing to offer.
    $this->get(route('products.show', $retailOnly->slug))
        ->assertInertia(fn (Assert $page) => $page->where('proOffer', null));

    // Crawlers read the retail landing page only.
    $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')
        ->get(route('products.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page->where('proOffer', null));

    // Already on the pro list: the price block quotes it.
    $this->withHeader('User-Agent', 'Mozilla/5.0')
        ->withCookie(PriceModeService::COOKIE, 'professional')
        ->get(route('products.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page->where('proOffer', null));
});
