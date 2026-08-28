<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\ProductImageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function makeProduct(string $categorySlug, string $name = 'Produit test'): Product
{
    $seller = User::factory()->create(['role' => 'seller']);
    $category = Category::firstOrCreate(
        ['slug' => $categorySlug],
        ['name' => Str::headline($categorySlug)]
    );
    $city = City::firstOrCreate(['name' => 'Marrakech']);
    $store = Store::create([
        'user_id' => $seller->id,
        'category_id' => $category->id,
        'city_id' => $city->id,
        'store_name' => 'Boutique '.$seller->id,
        'slug' => 'boutique-'.$seller->id,
    ]);

    return $store->products()->create([
        'name' => $name,
        'slug' => Str::slug($name).'-'.$seller->id,
        'base_price' => 100,
        'stock' => 5,
        'category_id' => $category->id,
        'status' => 'published',
    ]);
}

test('it attaches a gallery of images to a product that has none', function () {
    Storage::fake('public');
    $product = makeProduct('pottery-ceramics');

    $this->seed(ProductImageSeeder::class);

    $media = $product->fresh()->media;

    expect($media)->toHaveCount(ProductImageSeeder::IMAGES_PER_PRODUCT);
    expect($media->pluck('order')->all())->toBe([0, 1, 2]);

    foreach ($media as $item) {
        expect($item->type)->toBe('image')
            ->and($item->disk)->toBe('public')
            ->and($item->mime_type)->toStartWith('image/')
            ->and($item->size)->toBeGreaterThan(0)
            ->and($item->path)->toStartWith("products/{$product->id}/");

        Storage::disk('public')->assertExists($item->path);
    }
});

test('it does not duplicate media when run twice', function () {
    Storage::fake('public');
    $product = makeProduct('leather-goods');

    $this->seed(ProductImageSeeder::class);
    $this->seed(ProductImageSeeder::class);

    expect($product->fresh()->media)->toHaveCount(ProductImageSeeder::IMAGES_PER_PRODUCT);
});

test('it leaves a product alone when its category has no source images', function () {
    Storage::fake('public');
    $product = makeProduct('spices-food');

    $this->seed(ProductImageSeeder::class);

    expect($product->fresh()->media)->toHaveCount(0);
});

test('products in the same category get different lead images', function () {
    Storage::fake('public');
    $first = makeProduct('wood-carpentry', 'Plateau');
    $second = makeProduct('wood-carpentry', 'Miroir');

    $this->seed(ProductImageSeeder::class);

    $leadSizes = collect([$first, $second])
        ->map(fn (Product $p) => $p->fresh()->media->firstWhere('order', 0)->size);

    expect($leadSizes->unique())->toHaveCount(2);
});

test('every configured category has source images on disk', function () {
    foreach (ProductImageSeeder::CATEGORY_IMAGES as $slug => $prefix) {
        $matches = glob(database_path("seeders/images/products/{$prefix}-*.{jpg,jpeg,png,webp}"), GLOB_BRACE);

        expect($matches)->not->toBeEmpty("No source images found for category \"{$slug}\" (prefix \"{$prefix}\").");
    }
});
