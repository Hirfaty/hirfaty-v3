<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\App;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Public pages carry their language in the URL (/fr, /ar, /en); everything
 * else follows the visitor's saved choice, then their browser.
 */
beforeEach(function () {
    $category = Category::create([
        'slug' => 'poterie',
        'name' => ['fr' => 'Poterie', 'ar' => 'فخار', 'en' => 'Pottery'],
    ]);

    $store = Store::create([
        'user_id' => User::factory()->create(['role' => 'seller'])->id,
        'store_name' => 'Atelier Safi',
        'slug' => 'atelier-safi',
        'category_id' => $category->id,
        'city_id' => City::create(['name' => 'Safi'])->id,
        'status' => 'active',
    ]);

    $this->product = Product::create([
        'store_id' => $store->id,
        'category_id' => $category->id,
        'name' => 'Vase bleu',
        'slug' => 'vase-bleu',
        'base_price' => 300,
        'stock' => 3,
        'status' => 'published',
        'visibility' => 'both',
    ]);
});

/*
 * ── The bare domain and old URLs ─────────────────────────────────────────
 */

test('the bare domain sends a visitor to their browser\'s language', function (string $header, string $expected) {
    $this->withHeader('Accept-Language', $header)
        ->get('/')
        ->assertRedirect("/{$expected}")
        ->assertStatus(302)
        ->assertHeader('Vary', 'Accept-Language, Cookie');
})->with([
    'Moroccan Arabic' => ['ar-MA,ar;q=0.9,fr;q=0.8', 'ar'],
    'British English' => ['en-GB,en;q=0.9', 'en'],
    'French' => ['fr-FR,fr;q=0.9', 'fr'],
    'a language we do not have' => ['de-DE,de;q=0.9', 'fr'],
]);

test('a language picked with the switcher outranks the browser', function () {
    $this->withHeader('Accept-Language', 'en-GB')
        ->post(route('locale.switch', 'ar'));

    $this->get('/')->assertRedirect('/ar');
});

/*
 * The bug this split exists for: one English page used to pin the bare
 * domain to /en for good, whatever the browser said.
 */
test('merely reading a page in another language does not override the browser', function () {
    $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')
        ->get('/en/products/vase-bleu')
        ->assertOk();

    $this->get('/')->assertRedirect('/fr');
    $this->get('/about')->assertRedirect('/fr/about');
});

test('a seller\'s language setting counts as a choice', function () {
    $seller = User::factory()->create(['role' => 'seller']);

    $this->withHeader('Accept-Language', 'fr-FR')
        ->actingAs($seller)
        ->patch(route('seller.settings.locale.update'), ['locale' => 'en']);

    $this->get('/')->assertRedirect('/en');
});

test('an old unprefixed URL forwards to the same page, query and all', function () {
    $this->withHeader('Accept-Language', 'ar')
        ->get('/products/vase-bleu?ref=whatsapp')
        ->assertRedirect('/ar/products/vase-bleu?ref=whatsapp');

    $this->withHeader('Accept-Language', 'fr')
        ->get('/about')
        ->assertRedirect('/fr/about');
});

test('an unknown language is a 404, not a redirect', function () {
    $this->get('/de/products')->assertNotFound();
});

/*
 * ── Pages in a language ──────────────────────────────────────────────────
 */

test('a public page renders in the language of its URL', function () {
    $this->get('/ar/products/vase-bleu')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('buyer/products/show')
            ->where('locale', 'ar')
            ->where('direction', 'rtl')
            ->where('product.slug', 'vase-bleu'));
});

test('the language of the last public page carries over to private pages', function () {
    $this->get('/en/about')->assertOk();

    // /login has no language in its URL — it follows the session.
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
});

test('links generated during a request stay in its language', function () {
    $this->get('/ar/products')
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'ar'));

    expect(route('products.show', 'vase-bleu'))->toEndWith('/ar/products/vase-bleu');
});

test('switching the active locale switches generated links with it', function () {
    // What a queued notification does while rendering for its recipient.
    App::setLocale('en');
    expect(route('privacy'))->toEndWith('/en/privacy');

    App::setLocale('ar');
    expect(route('privacy'))->toEndWith('/ar/privacy');
});

/*
 * ── The language switcher ────────────────────────────────────────────────
 */

test('the switcher moves a public page to the same page in the new language', function () {
    $this->from('/fr/products/vase-bleu?ref=x')
        ->post(route('locale.switch', 'en'))
        ->assertRedirect('/en/products/vase-bleu?ref=x')
        ->assertSessionHas('locale', 'en')
        ->assertSessionHas('locale_choice', 'en');
});

test('the switcher leaves a private page where it is', function () {
    $this->from('/login')
        ->post(route('locale.switch', 'ar'))
        ->assertRedirect('/login')
        ->assertSessionHas('locale', 'ar');
});

test('the switcher never bounces to another site', function () {
    $this->from('https://evil.example/fr/products')
        ->post(route('locale.switch', 'ar'))
        ->assertRedirect(route('home', ['locale' => 'ar']));
});

test('the switcher refuses a language we do not have', function () {
    $this->post(route('locale.switch', 'de'))->assertNotFound();

    expect(session('locale'))->not->toBe('de');
});

/*
 * ── Search engines ───────────────────────────────────────────────────────
 */

test('an indexable page links its translations with hreflang', function () {
    config(['hirfaty.indexable' => true]);

    $html = $this->get('/ar/products/vase-bleu')->getContent();

    expect($html)
        ->toContain('<link rel="canonical" href="'.url('/ar/products/vase-bleu').'">')
        ->toContain('<link rel="alternate" hreflang="fr" href="'.url('/fr/products/vase-bleu').'">')
        ->toContain('<link rel="alternate" hreflang="ar" href="'.url('/ar/products/vase-bleu').'">')
        ->toContain('<link rel="alternate" hreflang="en" href="'.url('/en/products/vase-bleu').'">')
        // The language-neutral URL, which picks the visitor's language.
        ->toContain('<link rel="alternate" hreflang="x-default" href="'.url('/products/vase-bleu').'">')
        ->toContain('<meta property="og:locale" content="ar_MA">');
});

test('the sitemap lists every page in every language', function () {
    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

    foreach (['fr', 'ar', 'en'] as $locale) {
        expect($xml)->toContain('<loc>'.url("/{$locale}/products/vase-bleu").'</loc>');
    }

    expect($xml)
        ->toContain('hreflang="x-default" href="'.url('/products/vase-bleu').'"')
        ->and(simplexml_load_string($xml))->not->toBeFalse();
});
