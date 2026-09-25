<?php

use App\Models\Blog;
use App\Models\Category;
use App\Models\City;
use App\Models\Media;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * What search engines and link previews read: server-rendered <head> tags,
 * robots.txt and sitemap.xml.
 *
 * The rule that matters most is the negative one — nothing but the real site's
 * public pages may ever be indexed — so much of this is about noindex.
 */
beforeEach(function () {
    $this->category = Category::create([
        'slug' => 'poterie',
        'name' => ['fr' => 'Poterie', 'ar' => 'فخار', 'en' => 'Pottery'],
    ]);
    $this->city = City::create(['name' => 'Safi']);

    $this->store = Store::create([
        'user_id' => User::factory()->create(['role' => 'seller'])->id,
        'store_name' => 'Atelier Safi',
        'slug' => 'atelier-safi',
        'category_id' => $this->category->id,
        'city_id' => $this->city->id,
        'status' => 'active',
    ]);
});

function seoProduct(Store $store, array $attributes = []): Product
{
    $name = $attributes['name'] ?? 'Vase bleu';

    return Product::create(array_merge([
        'store_id' => $store->id,
        'category_id' => $store->category_id,
        'name' => $name,
        'slug' => Str::slug($name).'-'.Str::random(6),
        'description' => 'Un vase tourné à la main à Safi, émaillé en bleu.',
        'base_price' => 300,
        'stock' => 3,
        'status' => 'published',
        'visibility' => 'both',
    ], $attributes));
}

/** @return array<int, array<string, mixed>> Every JSON-LD block in the page. */
function jsonLd(string $html): array
{
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

    return array_map(fn ($json) => json_decode($json, true, flags: JSON_THROW_ON_ERROR), $matches[1]);
}

function schemaOfType(string $html, string $type): ?array
{
    return collect(jsonLd($html))->firstWhere('@type', $type);
}

/*
 * ── <head> tags ───────────────────────────────────────────────────────────
 */

test('a product page ships its description, preview image and price in the first HTML', function () {
    $product = seoProduct($this->store);
    Media::create([
        'mediable_type' => Product::class,
        'mediable_id' => $product->id,
        'type' => 'image',
        'path' => 'products/1/a.jpg',
        'url' => 'https://cdn.example/a.jpg',
        'order' => 0,
    ]);

    $html = $this->get(route('products.show', $product->slug))->assertOk()->getContent();

    expect($html)
        ->toContain('<title>Vase bleu — Hirfaty</title>')
        ->toContain('<meta name="description" content="Un vase tourné à la main à Safi, émaillé en bleu.">')
        ->toContain('<meta property="og:type" content="product">')
        ->toContain('<meta property="og:image" content="https://cdn.example/a.jpg">')
        ->toContain('<meta property="product:price:amount" content="300.00">')
        ->toContain('<meta name="twitter:card" content="summary_large_image">');
});

test('a product page carries Product and BreadcrumbList structured data', function () {
    $product = seoProduct($this->store, ['sale_price' => 250]);

    $html = $this->get(route('products.show', $product->slug))->getContent();
    $schema = schemaOfType($html, 'Product');

    expect($schema['name'])->toBe('Vase bleu')
        ->and($schema['brand']['name'])->toBe('Atelier Safi')
        // The consumer sale price, since that is what a guest pays.
        ->and($schema['offers']['price'])->toBe('250.00')
        ->and($schema['offers']['priceCurrency'])->toBe('MAD')
        ->and($schema['offers']['availability'])->toBe('https://schema.org/InStock')
        ->and($schema)->not->toHaveKey('aggregateRating');

    $crumbs = schemaOfType($html, 'BreadcrumbList')['itemListElement'];

    expect(collect($crumbs)->pluck('name')->all())->toBe(['Accueil', 'Catalogue', 'Poterie', 'Vase bleu']);
});

test('reviews reach the structured data with the author masked', function () {
    $product = seoProduct($this->store);
    $buyer = User::factory()->create(['role' => 'buyer', 'first_name' => 'Achraf', 'last_name' => 'Essaadi']);
    $order = Order::create([
        'buyer_id' => $buyer->id,
        'store_id' => $this->store->id,
        'city_id' => $this->city->id,
        'status' => 'delivered',
        'total_amount' => 300,
        'platform_fee' => 60,
        'delivery_fee' => 20,
        'seller_earnings' => 240,
        'shipping_address' => '1 rue des potiers',
    ]);
    ProductReview::create([
        'user_id' => $buyer->id,
        'product_id' => $product->id,
        'order_id' => $order->id,
        'rating' => 4,
        'comment' => 'Très beau.',
    ]);

    $html = $this->get(route('products.show', $product->slug))->getContent();
    $schema = schemaOfType($html, 'Product');

    expect($schema['aggregateRating']['ratingValue'])->toEqual(4)
        ->and($schema['aggregateRating']['reviewCount'])->toBe(1)
        ->and($schema['review'][0]['author']['name'])->toBe('A*** E.')
        ->and($html)->not->toContain('Essaadi');
});

test('a product description cannot break out of the JSON-LD script tag', function () {
    $product = seoProduct($this->store, [
        'description' => 'Vase</script><script>alert(1)</script>',
    ]);

    $html = $this->get(route('products.show', $product->slug))->getContent();

    expect($html)->not->toContain('<script>alert(1)</script>');
});

test('the home page describes the site and names the organisation', function () {
    $html = $this->get(route('home'))->getContent();

    expect($html)
        ->toContain('<meta name="description" content="Achetez l’artisanat marocain')
        ->toContain('<meta property="og:image" content="'.asset('og-image.jpg').'">')
        ->and(schemaOfType($html, 'Organization')['name'])->toBe('Hirfaty')
        ->and(schemaOfType($html, 'WebSite'))->not->toBeNull();
});

test('a shop page is described by its own description', function () {
    $this->store->update(['description' => 'Trois générations de potiers à Safi.']);
    seoProduct($this->store);

    $this->get(route('stores.show', $this->store->slug))
        ->assertSee('<title>Atelier Safi — Hirfaty</title>', false)
        ->assertSee('<meta name="description" content="Trois générations de potiers à Safi.">', false);
});

test('a category listing gets its own title, description and canonical', function () {
    config(['hirfaty.indexable' => true]);
    seoProduct($this->store);

    $html = $this->get(route('products.index', ['category_id' => $this->category->id, 'page' => 3, 'utm_source' => 'x']))
        ->getContent();

    expect($html)
        ->toContain('<title>Poterie — Catalogue — Hirfaty</title>')
        ->toContain('Poterie faits main par des artisans marocains')
        // Filters that make a distinct page survive; paging and tracking do not.
        ->toContain('<link rel="canonical" href="'.route('products.index', ['category_id' => $this->category->id]).'">');
});

test('a published article carries BlogPosting data', function () {
    $blog = Blog::create([
        'title' => 'Le zellige de Fès',
        'slug' => 'le-zellige-de-fes',
        'excerpt' => 'Comment naît un carreau de zellige.',
        'content' => '<p>Texte.</p>',
        'status' => Blog::STATUS_PUBLISHED,
        'published_at' => now()->subDay(),
    ]);

    $html = $this->get(route('blog.show', $blog->slug))->getContent();
    $article = schemaOfType($html, 'BlogPosting');

    expect($html)->toContain('<meta property="og:type" content="article">')
        ->and($article['headline'])->toBe('Le zellige de Fès')
        ->and($article['description'])->toBe('Comment naît un carreau de zellige.');
});

/*
 * ── Indexing rules ────────────────────────────────────────────────────────
 */

test('nothing is indexable outside production', function () {
    config(['hirfaty.indexable' => false]);
    $product = seoProduct($this->store);

    $this->get(route('products.show', $product->slug))
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertDontSee('rel="canonical"', false);
});

test('public pages are indexable in production', function () {
    config(['hirfaty.indexable' => true]);
    $product = seoProduct($this->store);

    $this->get(route('products.show', $product->slug))
        ->assertHeaderMissing('X-Robots-Tag')
        ->assertSee('<meta name="robots" content="index, follow, max-image-preview:large">', false)
        ->assertSee('<link rel="canonical" href="'.route('products.show', $product->slug).'">', false);
});

test('the staging host is never indexable, even with indexing switched on', function () {
    config(['hirfaty.indexable' => true]);

    $this->get('http://'.config('hirfaty.staging_host').'/fr')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

test('private pages stay noindex in production', function () {
    config(['hirfaty.indexable' => true]);
    $buyer = User::factory()->create(['role' => 'buyer']);

    $this->actingAs($buyer)
        ->get(route('cart.show'))
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

test('search results are followed but never indexed', function () {
    config(['hirfaty.indexable' => true]);

    $this->get(route('products.index', ['search' => 'vase']))
        ->assertSee('<meta name="robots" content="noindex, follow">', false);
});

/*
 * ── robots.txt ────────────────────────────────────────────────────────────
 */

test('robots.txt shuts everything out outside production', function () {
    config(['hirfaty.indexable' => false]);

    $response = $this->get('/robots.txt')->assertOk();

    expect($response->getContent())->toBe("User-agent: *\nDisallow: /\n");
});

test('robots.txt in production blocks private areas and points to the sitemap', function () {
    config(['hirfaty.indexable' => true]);

    $body = $this->get('/robots.txt')->assertOk()->getContent();

    expect($body)
        ->toContain("Disallow: /admin\n")
        ->toContain("Disallow: /checkout\n")
        ->toContain('Sitemap: '.route('sitemap'))
        ->not->toContain("Disallow: /\n");
});

/*
 * ── sitemap.xml ───────────────────────────────────────────────────────────
 */

test('the sitemap lists only what a guest can open', function () {
    $listed = seoProduct($this->store, ['name' => 'Tajine']);
    $tradeOnly = seoProduct($this->store, ['name' => 'Lot hotel', 'visibility' => 'professional']);
    $pending = seoProduct($this->store, ['name' => 'Brouillon', 'status' => 'pending_review']);
    $unpriced = seoProduct($this->store, ['name' => 'Sans prix', 'base_price' => null]);

    $emptyStore = Store::create([
        'user_id' => User::factory()->create(['role' => 'seller'])->id,
        'store_name' => 'Atelier vide',
        'slug' => 'atelier-vide',
        'category_id' => $this->category->id,
        'city_id' => $this->city->id,
        'status' => 'active',
    ]);
    $emptyCategory = Category::create(['slug' => 'cuir', 'name' => ['fr' => 'Cuir', 'ar' => 'جلد', 'en' => 'Leather']]);

    $published = Blog::create(['title' => 'Publié', 'slug' => 'publie', 'content' => '<p>x</p>', 'status' => Blog::STATUS_PUBLISHED, 'published_at' => now()->subDay()]);
    $draft = Blog::create(['title' => 'Brouillon', 'slug' => 'brouillon', 'content' => '<p>x</p>', 'status' => Blog::STATUS_DRAFT]);

    $xml = $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->getContent();

    expect($xml)
        ->toContain('<loc>'.route('home').'</loc>')
        ->toContain('<loc>'.route('products.show', $listed->slug).'</loc>')
        ->toContain('<loc>'.route('stores.show', $this->store->slug).'</loc>')
        ->toContain('<loc>'.e(route('products.index', ['category_id' => $this->category->id])).'</loc>')
        ->toContain('<loc>'.route('blog.show', $published->slug).'</loc>')
        ->not->toContain(route('products.show', $tradeOnly->slug))
        ->not->toContain(route('products.show', $pending->slug))
        ->not->toContain(route('products.show', $unpriced->slug))
        ->not->toContain(route('stores.show', $emptyStore->slug))
        ->not->toContain('category_id='.$emptyCategory->id)
        ->not->toContain(route('blog.show', $draft->slug));

    // Well-formed XML, not just a string that looks like it.
    expect(simplexml_load_string($xml))->not->toBeFalse();
});
