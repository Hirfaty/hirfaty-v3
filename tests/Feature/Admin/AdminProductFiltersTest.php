<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Services\MediaHealthService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'super_admin']);

    $this->category = Category::create([
        'slug' => 'poterie',
        'name' => ['fr' => 'Poterie', 'ar' => 'فخار', 'en' => 'Pottery'],
    ]);

    $seller = User::factory()->create(['role' => 'seller']);
    $this->store = Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Atelier Fès',
        'slug' => 'atelier-fes',
        'category_id' => $this->category->id,
        'city_id' => City::create(['name' => 'Fès'])->id,
    ]);
});

function filterProduct(string $name, array $attributes = []): Product
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

function filterImage(Product|ProductVariant $owner, ?string $brokenAt = null): Media
{
    $media = $owner->media()->create([
        'type' => 'image',
        'path' => 'products/'.uniqid().'.jpg',
        'url' => '/storage/x.jpg',
        'disk' => 'public',
    ]);
    $media->forceFill(['broken_at' => $brokenAt])->save();

    return $media;
}

/** @return list<string> */
function listedNames(array $query): array
{
    $names = [];

    test()->actingAs(test()->admin)
        ->get(route('admin.products.index', $query))
        ->assertOk()
        ->assertInertia(function ($page) use (&$names) {
            $names = collect($page->toArray()['props']['products']['data'])->pluck('name')->sort()->values()->all();
        });

    return $names;
}

test('the broken images filter finds products whose own or variant photos are flagged', function () {
    $own = filterProduct('Vase cassé');
    filterImage($own, now()->toDateTimeString());
    filterImage($own);

    $viaVariant = filterProduct('Tapis à variante cassée', ['has_variants' => true]);
    filterImage(ProductVariant::create(['product_id' => $viaVariant->id, 'stock' => 2]), now()->toDateTimeString());

    filterImage(filterProduct('Vase sain'));

    expect(listedNames(['images' => 'broken']))->toBe(['Tapis à variante cassée', 'Vase cassé']);

    $this->actingAs($this->admin)
        ->get(route('admin.products.index'))
        ->assertInertia(fn ($page) => $page->where('brokenImagesCount', 2));
});

test('the no photo filter finds products without any image on them or their variants', function () {
    filterProduct('Sans rien');
    filterImage(filterProduct('Avec photo'));
    $variantOnly = filterProduct('Photo de variante', ['has_variants' => true]);
    filterImage(ProductVariant::create(['product_id' => $variantOnly->id, 'stock' => 2]));

    expect(listedNames(['images' => 'none']))->toBe(['Sans rien']);
});

test('the price filter splits priced products from those still to price', function () {
    filterProduct('Prix fixé');
    filterProduct('À chiffrer', ['base_price' => null]);

    expect(listedNames(['price' => 'with']))->toBe(['Prix fixé'])
        ->and(listedNames(['price' => 'without']))->toBe(['À chiffrer']);
});

test('the stock filter follows active variants when a product sells through them', function () {
    filterProduct('Simple en stock', ['stock' => 3]);
    filterProduct('Simple épuisé', ['stock' => 0]);

    // The product column is ignored once active variants carry the stock.
    $stocked = filterProduct('Variantes en stock', ['has_variants' => true, 'stock' => 0]);
    ProductVariant::create(['product_id' => $stocked->id, 'stock' => 0]);
    ProductVariant::create(['product_id' => $stocked->id, 'stock' => 4]);

    $soldOut = filterProduct('Variantes épuisées', ['has_variants' => true, 'stock' => 9]);
    ProductVariant::create(['product_id' => $soldOut->id, 'stock' => 0]);
    ProductVariant::create(['product_id' => $soldOut->id, 'stock' => 5, 'is_active' => false]);

    expect(listedNames(['stock' => 'in']))->toBe(['Simple en stock', 'Variantes en stock'])
        ->and(listedNames(['stock' => 'out']))->toBe(['Simple épuisé', 'Variantes épuisées']);
});

test('the export accepts the new filters', function () {
    filterProduct('À chiffrer', ['base_price' => null]);

    $this->actingAs($this->admin)
        ->get(route('admin.products.export', ['format' => 'csv', 'price' => 'without', 'stock' => 'in', 'images' => 'broken']))
        ->assertOk();

    $this->actingAs($this->admin)
        ->get(route('admin.products.export', ['format' => 'csv', 'stock' => 'nope']))
        ->assertSessionHasErrors('stock');
});

test('media:check flags unreadable images and clears ones that read again', function () {
    Storage::fake('public');
    $product = filterProduct('Vase');

    $healthy = filterImage($product);
    Storage::disk('public')->put($healthy->path, 'jpeg-bytes');

    $missing = filterImage($product);

    $empty = filterImage($product);
    Storage::disk('public')->put($empty->path, '');

    $recovered = filterImage($product, '2026-10-01 00:00:00');
    Storage::disk('public')->put($recovered->path, 'jpeg-bytes');

    $stillBroken = filterImage($product, '2026-10-01 00:00:00');

    $result = app(MediaHealthService::class)->scan();

    expect($result)->toBe(['checked' => 5, 'broken' => 3])
        ->and($healthy->fresh()->broken_at)->toBeNull()
        ->and($missing->fresh()->broken_at)->not->toBeNull()
        ->and($empty->fresh()->broken_at)->not->toBeNull()
        ->and($recovered->fresh()->broken_at)->toBeNull()
        // Keeps the date it was first found broken.
        ->and($stillBroken->fresh()->broken_at->toDateString())->toBe('2026-10-01');
});
