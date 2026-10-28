<?php

use App\Models\CartItem;
use App\Models\Category;
use App\Models\City;
use App\Models\DeliveryRate;
use App\Models\DeliveryService;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantOption;
use App\Models\Store;
use App\Models\User;
use App\Services\DeliveryFeeService;
use Database\Seeders\DhlDeliveryRateSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * /feeds/google/{language}.xml — the product feed Google Merchant Center
 * fetches. What matters: only what a guest can buy, one item per variant, at
 * the consumer price in MAD, with the fields handmade goods need.
 */
beforeEach(function () {
    Cache::flush();

    $this->category = Category::create([
        'slug' => 'poterie',
        'name' => ['fr' => 'Poterie', 'ar' => 'فخار', 'en' => 'Pottery'],
    ]);

    $this->store = Store::create([
        'user_id' => User::factory()->create(['role' => 'seller'])->id,
        'store_name' => 'Atelier Safi',
        'slug' => 'atelier-safi',
        'category_id' => $this->category->id,
        'city_id' => City::create(['name' => 'Safi'])->id,
        'status' => 'active',
    ]);
});

function feedProduct(Store $store, array $attributes = [], bool $withImage = true): Product
{
    $name = $attributes['name'] ?? 'Vase bleu';

    $product = Product::create(array_merge([
        'store_id' => $store->id,
        'category_id' => $store->category_id,
        'name' => $name,
        'slug' => Str::slug($name).'-'.Str::random(6),
        'description' => '<p>Un vase tourné à la main.</p><p>Émaillé en bleu.</p>',
        'base_price' => 300,
        'stock' => 3,
        'status' => 'published',
        'visibility' => 'both',
    ], $attributes));

    if ($withImage) {
        feedImage($product, "https://cdn.example/{$product->id}.jpg");
    }

    return $product;
}

function feedImage(Model $owner, string $url, int $order = 0): void
{
    Media::create([
        'mediable_type' => $owner::class,
        'mediable_id' => $owner->id,
        'type' => 'image',
        'path' => 'products/x.jpg',
        'url' => $url,
        'order' => $order,
    ]);
}

/** @param  array<string, string>  $options  type => value */
function feedVariant(Product $product, array $attributes, array $options): ProductVariant
{
    $variant = ProductVariant::create(array_merge([
        'product_id' => $product->id,
        'stock' => 2,
        'is_active' => true,
    ], $attributes));

    foreach ($options as $type => $value) {
        ProductVariantOption::create([
            'product_variant_id' => $variant->id,
            'type' => $type,
            'name' => ProductVariantOption::axisFor($type) ?? 'Autre',
            'value' => $value,
        ]);
    }

    return $variant;
}

/**
 * Every <item>, keyed by its `g:id`, each field without the `g:` prefix.
 * Repeated fields (additional_image_link) come back as a list; `shipping`
 * always does, keyed by country, each entry its sub-fields.
 *
 * @return array<string, array<string, mixed>>
 */
function feedItems(string $xml): array
{
    $feed = simplexml_load_string($xml);
    $items = [];

    foreach ($feed->channel->item as $item) {
        $fields = [];

        foreach ($item->children('http://base.google.com/ns/1.0') as $name => $value) {
            if ($name === 'shipping') {
                $entry = [];

                foreach ($value->children('http://base.google.com/ns/1.0') as $sub => $subValue) {
                    $entry[$sub] = (string) $subValue;
                }

                $fields['shipping'][$entry['country']] = $entry;

                continue;
            }

            $fields[$name] = isset($fields[$name])
                ? [...(array) $fields[$name], (string) $value]
                : (string) $value;
        }

        $items[$fields['id']] = $fields;
    }

    return $items;
}

test('the feed lists only what a guest can buy', function () {
    $listed = feedProduct($this->store, ['name' => 'Tajine']);
    feedProduct($this->store, ['name' => 'Lot hotel', 'visibility' => 'professional']);
    feedProduct($this->store, ['name' => 'Brouillon', 'status' => 'pending_review']);
    feedProduct($this->store, ['name' => 'Sans prix', 'base_price' => null]);
    feedProduct($this->store, ['name' => 'Sans photo'], withImage: false);

    $response = $this->get('/feeds/google/fr.xml')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/xml')
        ->and(array_keys(feedItems($response->getContent())))->toBe([$listed->id]);
});

test('a simple product carries what Merchant Center needs for a handmade piece', function () {
    $product = feedProduct($this->store, ['weight' => 850, 'weight_unit' => 'g']);
    feedImage($product, 'https://cdn.example/second.jpg', 1);

    $item = feedItems($this->get('/feeds/google/fr.xml')->getContent())[(string) $product->id];

    expect($item)
        ->toMatchArray([
            'title' => 'Vase bleu',
            'description' => 'Un vase tourné à la main. Émaillé en bleu.',
            'link' => route('products.show', ['locale' => 'fr', 'slug' => $product->slug]),
            'image_link' => "https://cdn.example/{$product->id}.jpg",
            'additional_image_link' => 'https://cdn.example/second.jpg',
            'availability' => 'in_stock',
            'price' => '300.00 MAD',
            'condition' => 'new',
            'brand' => 'Atelier Safi',
            'identifier_exists' => 'no',
            'product_type' => 'Poterie',
            'shipping_weight' => '0.850 kg',
        ])
        ->not->toHaveKeys(['sale_price', 'item_group_id']);
});

test('a sale shows as sale_price under the regular price, and an out-of-stock piece says so', function () {
    $onSale = feedProduct($this->store, ['name' => 'Plat', 'sale_price' => 240]);
    $bogusSale = feedProduct($this->store, ['name' => 'Bol', 'sale_price' => 350]);
    $soldOut = feedProduct($this->store, ['name' => 'Jarre', 'stock' => 0]);

    $items = feedItems($this->get('/feeds/google/fr.xml')->getContent());

    expect($items[(string) $onSale->id])->toMatchArray(['price' => '300.00 MAD', 'sale_price' => '240.00 MAD'])
        ->and($items[(string) $bogusSale->id])->not->toHaveKey('sale_price')
        ->and($items[(string) $soldOut->id]['availability'])->toBe('out_of_stock');
});

test('each active variant is its own item, grouped under its product', function () {
    $product = feedProduct($this->store, ['name' => 'Tapis', 'has_variants' => true, 'sale_price' => 250, 'stock' => 0]);

    $blue = feedVariant($product, [], ['color' => 'Bleu', 'dimension' => '2x3 m']);
    $red = feedVariant($product, ['price_override' => 420, 'stock' => 0], ['color' => 'Rouge', 'dimension' => '3x4 m']);
    feedImage($red, 'https://cdn.example/red.jpg');
    $hidden = feedVariant($product, ['is_active' => false], ['color' => 'Vert']);

    $items = feedItems($this->get('/feeds/google/fr.xml')->getContent());

    expect($items)->toHaveKeys(["{$product->id}-{$blue->id}", "{$product->id}-{$red->id}"])
        ->not->toHaveKeys([(string) $product->id, "{$product->id}-{$hidden->id}"]);

    // Inherits the product's price, so its sale applies.
    expect($items["{$product->id}-{$blue->id}"])->toMatchArray([
        'item_group_id' => (string) $product->id,
        'title' => 'Tapis — Bleu / 2x3 m',
        'color' => 'Bleu',
        'size' => '2x3 m',
        'price' => '300.00 MAD',
        'sale_price' => '250.00 MAD',
        'availability' => 'in_stock',
        'link' => route('products.show', ['locale' => 'fr', 'slug' => $product->slug, 'variant' => $blue->id]),
        'image_link' => "https://cdn.example/{$product->id}.jpg",
    ]);

    // Its own price, its own photo first, its own stock.
    expect($items["{$product->id}-{$red->id}"])
        ->toMatchArray([
            'price' => '420.00 MAD',
            'availability' => 'out_of_stock',
            'image_link' => 'https://cdn.example/red.jpg',
            'additional_image_link' => "https://cdn.example/{$product->id}.jpg",
        ])
        ->not->toHaveKey('sale_price');
});

test('each language has its own feed, linking to that language', function () {
    $product = feedProduct($this->store, ['description' => null]);

    $item = feedItems($this->get('/feeds/google/ar.xml')->assertOk()->getContent())[(string) $product->id];

    expect($item['link'])->toBe(route('products.show', ['locale' => 'ar', 'slug' => $product->slug]))
        ->and($item['product_type'])->toBe('فخار')
        // No description of its own: the translated fallback, in Arabic.
        ->and($item['description'])->toContain('Vase bleu')->not->toContain('pièce faite main');

    // Building the Arabic feed must not leave the app in Arabic.
    expect(app()->getLocale())->not->toBe('ar');

    $this->get('/feeds/google/de.xml')->assertNotFound();
});

/** Amana in two Moroccan cities, DHL Express abroad — the live cards. */
function feedCarriers(): DeliveryService
{
    $amana = DeliveryService::create(['slug' => 'amana', 'name' => 'Amana', 'sort_order' => 1]);

    foreach (['Casablanca' => 40, 'Dakhla' => 65] as $city => $fee) {
        DeliveryRate::create([
            'delivery_service_id' => $amana->id,
            'country_code' => 'MA',
            'country_name' => 'Maroc',
            'city_id' => City::create(['name' => $city])->id,
            'fee' => $fee,
        ]);
    }

    (new DhlDeliveryRateSeeder)->run();

    return DeliveryService::where('slug', DhlDeliveryRateSeeder::SLUG)->firstOrFail();
}

/** What checkout bills to ship one unit to `$country`, as the feed writes money. */
function checkoutQuote(DeliveryService $service, string $country, Product $product, ?ProductVariant $variant = null): string
{
    $line = (new CartItem(['quantity' => 1]))
        ->setRelation('product', $product)
        ->setRelation('variant', $variant);

    return number_format(app(DeliveryFeeService::class)->resolve($country, null, $service, [$line]), 2, '.', '').' MAD';
}

test('each item carries its delivery price per target country, at what checkout bills', function () {
    $dhl = feedCarriers();
    $dhl->update(['fuel_surcharge_percent' => 27.5]);
    config(['hirfaty.google_feed_shipping_countries' => ['ma', 'FR', 'US', 'XX']]);

    $light = feedProduct($this->store, ['name' => 'Bol', 'weight' => 850, 'weight_unit' => 'g']);
    $heavy = feedProduct($this->store, ['name' => 'Jarre', 'weight' => 12, 'weight_unit' => 'kg']);

    $items = feedItems($this->get('/feeds/google/fr.xml')->getContent());
    $shipping = $items[(string) $light->id]['shipping'];

    // Nothing ships to XX, so it is left out rather than quoted at the fallback.
    expect(array_keys($shipping))->toBe(['MA', 'FR', 'US'])
        // Morocco is priced per city: its dearest, so Google never under-quotes.
        ->and($shipping['MA'])->toBe(['country' => 'MA', 'service' => 'Amana', 'price' => '65.00 MAD'])
        ->and($shipping['FR'])->toBe(['country' => 'FR', 'service' => $dhl->name, 'price' => checkoutQuote($dhl, 'FR', $light)])
        ->and($shipping['US']['price'])->toBe(checkoutQuote($dhl, 'US', $light))
        ->and($items[(string) $heavy->id]['shipping']['FR']['price'])->toBe(checkoutQuote($dhl, 'FR', $heavy))
        ->and(checkoutQuote($dhl, 'FR', $heavy))->not->toBe(checkoutQuote($dhl, 'FR', $light));
});

test('a variant is priced on its own weight', function () {
    $dhl = feedCarriers();
    config(['hirfaty.google_feed_shipping_countries' => ['FR']]);

    $product = feedProduct($this->store, ['name' => 'Tapis', 'has_variants' => true, 'weight' => 2, 'weight_unit' => 'kg']);
    $small = feedVariant($product, [], ['dimension' => '1x1 m']);
    $large = feedVariant($product, ['weight' => 9], ['dimension' => '3x4 m']);

    $items = feedItems($this->get('/feeds/google/fr.xml')->getContent());

    expect($items["{$product->id}-{$small->id}"]['shipping']['FR']['price'])->toBe(checkoutQuote($dhl, 'FR', $product, $small))
        ->and($items["{$product->id}-{$large->id}"]['shipping']['FR']['price'])->toBe(checkoutQuote($dhl, 'FR', $product, $large))
        ->and(checkoutQuote($dhl, 'FR', $product, $large))->not->toBe(checkoutQuote($dhl, 'FR', $product, $small));
});

test('with no carrier on file the feed carries no shipping', function () {
    $product = feedProduct($this->store);

    expect(feedItems($this->get('/feeds/google/fr.xml')->getContent())[(string) $product->id])
        ->not->toHaveKey('shipping');
});

test('pricing delivery costs no query per item', function () {
    feedCarriers();

    $queries = function () {
        Cache::flush();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/feeds/google/fr.xml')->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    feedProduct($this->store, ['name' => 'Un']);
    $one = $queries();

    foreach (['Deux', 'Trois', 'Quatre'] as $name) {
        feedProduct($this->store, ['name' => $name, 'weight' => 3, 'weight_unit' => 'kg']);
    }

    expect($queries())->toBe($one);
});
