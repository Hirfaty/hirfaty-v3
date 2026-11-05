<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'super_admin']);

    $this->category = Category::create([
        'slug' => 'poterie',
        'name' => ['fr' => 'Poterie', 'ar' => 'فخار', 'en' => 'Pottery'],
    ]);

    $this->city = City::create(['name' => 'Fès']);
    $this->store = Store::create([
        'user_id' => User::factory()->create(['role' => 'seller'])->id,
        'store_name' => 'Atelier Fès',
        'slug' => 'atelier-fes',
        'category_id' => $this->category->id,
        'city_id' => $this->city->id,
    ]);
});

function sortableProduct(string $name, array $attributes = []): Product
{
    return Product::create([
        'store_id' => test()->store->id,
        'category_id' => test()->category->id,
        'name' => $name,
        'slug' => Str::slug($name).'-'.uniqid(),
        'base_price' => 100,
        'stock' => 1,
        'status' => 'published',
        ...$attributes,
    ]);
}

function sellTimes(Product $product, int $times): void
{
    $order = Order::create([
        'buyer_id' => User::factory()->create(['role' => 'buyer'])->id,
        'store_id' => test()->store->id,
        'city_id' => test()->city->id,
        'status' => 'delivered',
        'total_amount' => 100,
        'platform_fee' => 0,
        'delivery_fee' => 0,
        'seller_earnings' => 100,
        'shipping_address' => 'Test Address',
    ]);

    foreach (range(1, $times) as $_) {
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
            'subtotal' => 100,
        ]);
    }
}

/** @return list<string> The names in the order the table lists them. */
function namesSortedBy(?string $sort): array
{
    $names = [];

    test()->actingAs(test()->admin)
        ->get(route('admin.products.index', array_filter(['sort' => $sort])))
        ->assertOk()
        ->assertInertia(function ($page) use (&$names) {
            $names = collect($page->toArray()['props']['products']['data'])->pluck('name')->all();
        });

    return $names;
}

test('products sort by date, newest first unless asked otherwise', function () {
    $this->travelTo('2026-01-01');
    $edited = sortableProduct('Ancien');

    $this->travelTo('2026-02-01');
    sortableProduct('Récent');

    $this->travelTo('2026-03-01');
    $edited->update(['stock' => 5]);

    expect(namesSortedBy(null))->toBe(['Récent', 'Ancien'])
        ->and(namesSortedBy('newest'))->toBe(['Récent', 'Ancien'])
        ->and(namesSortedBy('oldest'))->toBe(['Ancien', 'Récent'])
        ->and(namesSortedBy('updated'))->toBe(['Ancien', 'Récent']);
});

test('an unknown sort lists newest first', function () {
    $this->travelTo('2026-01-01');
    sortableProduct('Ancien');

    $this->travelTo('2026-02-01');
    sortableProduct('Récent');

    expect(namesSortedBy('nope'))->toBe(['Récent', 'Ancien']);
});

test('the name sort ignores case and accents', function () {
    foreach (['Zellige', 'éventail', 'Abricot', 'bol'] as $name) {
        sortableProduct($name);
    }

    expect(namesSortedBy('name_asc'))->toBe(['Abricot', 'bol', 'éventail', 'Zellige'])
        ->and(namesSortedBy('name_desc'))->toBe(['Zellige', 'éventail', 'bol', 'Abricot']);
});

test('the price sort uses the sale price and keeps unpriced products last', function () {
    sortableProduct('Cher', ['base_price' => 300]);
    sortableProduct('Soldé', ['base_price' => 500, 'sale_price' => 100]);
    sortableProduct('Moyen', ['base_price' => 200]);
    sortableProduct('À chiffrer', ['base_price' => null]);

    expect(namesSortedBy('price_asc'))->toBe(['Soldé', 'Moyen', 'Cher', 'À chiffrer'])
        ->and(namesSortedBy('price_desc'))->toBe(['Cher', 'Moyen', 'Soldé', 'À chiffrer']);
});

test('the stock sort follows active variants when a product sells through them', function () {
    sortableProduct('Simple', ['stock' => 3]);
    sortableProduct('Épuisé', ['stock' => 0]);

    $variants = sortableProduct('Décliné', ['has_variants' => true, 'stock' => 0]);
    ProductVariant::create(['product_id' => $variants->id, 'stock' => 2]);
    ProductVariant::create(['product_id' => $variants->id, 'stock' => 3]);
    ProductVariant::create(['product_id' => $variants->id, 'stock' => 50, 'is_active' => false]);

    // No active variant left: the storefront falls back on the product's own column.
    $fallback = sortableProduct('Sans variante active', ['has_variants' => true, 'stock' => 2]);
    ProductVariant::create(['product_id' => $fallback->id, 'stock' => 90, 'is_active' => false]);

    expect(namesSortedBy('stock_asc'))->toBe(['Épuisé', 'Sans variante active', 'Simple', 'Décliné'])
        ->and(namesSortedBy('stock_desc'))->toBe(['Décliné', 'Simple', 'Sans variante active', 'Épuisé']);

    // The figure the column shows is the one the rows are ordered by.
    $this->actingAs($this->admin)
        ->get(route('admin.products.index', ['sort' => 'stock_desc']))
        ->assertInertia(fn ($page) => $page->where('products.data.0.listed_stock', 5));
});

test('the sales sort lists the most ordered products first', function () {
    sellTimes(sortableProduct('Deuxième'), 1);
    sortableProduct('Invendu');
    sellTimes(sortableProduct('Premier'), 2);

    expect(namesSortedBy('sales'))->toBe(['Premier', 'Deuxième', 'Invendu']);

    $this->actingAs($this->admin)
        ->get(route('admin.products.index', ['sort' => 'sales']))
        ->assertInertia(fn ($page) => $page->where('products.data.0.order_items_count', 2));
});

test('rows that tie keep a stable order', function () {
    $this->travelTo('2026-01-01');

    foreach (['Un', 'Deux', 'Trois'] as $name) {
        sortableProduct($name);
    }

    expect(namesSortedBy(null))->toBe(['Trois', 'Deux', 'Un'])
        ->and(namesSortedBy('sales'))->toBe(['Trois', 'Deux', 'Un']);
});

test('the sort survives pagination and combines with the filters', function () {
    foreach (range(1, 21) as $number) {
        sortableProduct("Vase {$number}");
    }
    sortableProduct('À chiffrer', ['base_price' => null]);

    $this->actingAs($this->admin)
        ->get(route('admin.products.index', ['sort' => 'oldest']))
        ->assertInertia(fn ($page) => $page
            ->where('filters.sort', 'oldest')
            ->where('products.next_page_url', fn ($url) => str_contains($url, 'sort=oldest')));

    $this->actingAs($this->admin)
        ->get(route('admin.products.index', ['sort' => 'name_asc', 'price' => 'with']))
        ->assertInertia(fn ($page) => $page
            ->where('products.total', 21)
            ->where('products.data.0.name', 'Vase 1')
            // The tabs count the catalogue, whatever order it is shown in.
            ->where('statusCounts.published', 21));
});

test('the filters reach the page as an object even when none is set', function () {
    // As [] the page would read `filters.sort` as the array's own sort() method.
    $page = $this->actingAs($this->admin)
        ->get(route('admin.products.index'))
        ->viewData('page');

    expect(json_encode($page['props']['filters']))->toBe('{}');
});

test('the download follows the sort on screen', function () {
    sortableProduct('Zellige', ['base_price' => 100]);
    sortableProduct('Abricot', ['base_price' => 200]);

    $download = fn (string $sort) => $this->actingAs($this->admin)
        ->get(route('admin.products.export', ['format' => 'csv', 'sort' => $sort]))
        ->assertOk()
        ->streamedContent();

    $byName = $download('name_asc');
    $byPrice = $download('price_asc');

    expect(strpos($byName, 'Abricot'))->toBeLessThan(strpos($byName, 'Zellige'))
        ->and(strpos($byPrice, 'Zellige'))->toBeLessThan(strpos($byPrice, 'Abricot'));
});

test('the download refuses an unknown sort', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.products.export', ['format' => 'csv', 'sort' => 'nope']))
        ->assertSessionHasErrors('sort');
});
