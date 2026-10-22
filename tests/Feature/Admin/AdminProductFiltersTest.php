<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Services\MediaHealthService;
use App\Services\ProductPhotoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
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

test('the variants filter splits products sold through variants from simple ones', function () {
    filterProduct('Simple');
    filterProduct('Décliné', ['has_variants' => true]);

    expect(listedNames(['variants' => 'with']))->toBe(['Décliné'])
        ->and(listedNames(['variants' => 'without']))->toBe(['Simple']);
});

test('the export accepts the new filters', function () {
    filterProduct('À chiffrer', ['base_price' => null]);

    $this->actingAs($this->admin)
        ->get(route('admin.products.export', ['format' => 'csv', 'price' => 'without', 'stock' => 'in', 'images' => 'broken', 'variants' => 'without']))
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
        ->and($stillBroken->fresh()->broken_at->toDateString())->toBe('2026-10-01')
        ->and($healthy->fresh()->checked_at)->not->toBeNull();
});

test('media:check reads S3 images in parallel and treats a stalled body as broken', function () {
    config(['filesystems.disks.photos' => [
        'driver' => 's3',
        'key' => 'key',
        'secret' => 'secret',
        'region' => 'us-east-1',
        'bucket' => 'hirfaty',
        'endpoint' => 'http://s3.test',
        'use_path_style_endpoint' => true,
    ]]);

    // What production's SeaweedFS does with a lost file is a 200 whose body
    // never arrives — the client sees that as a failed connection.
    Http::fake(fn ($request) => match (true) {
        str_contains($request->url(), 'good') => Http::response('jpeg-bytes'),
        str_contains($request->url(), 'missing') => Http::response('', 404),
        default => Http::failedConnection(),
    });

    $product = filterProduct('Vase');
    $make = fn (string $name) => tap(filterImage($product))->update(['disk' => 'photos', 'path' => "products/{$name}.jpg"]);

    $good = $make('good');
    $missing = $make('missing');
    $stalled = $make('stalled');

    expect(app(MediaHealthService::class)->scan())->toBe(['checked' => 3, 'broken' => 2])
        ->and($good->fresh()->broken_at)->toBeNull()
        ->and($missing->fresh()->broken_at)->not->toBeNull()
        ->and($stalled->fresh()->broken_at)->not->toBeNull();
});

test('admins can start the image check from the products list', function () {
    Storage::fake('public');
    $image = filterImage(filterProduct('Vase'));

    $this->actingAs($this->admin)
        ->from(route('admin.products.index'))
        ->post(route('admin.products.check-images'))
        ->assertRedirect(route('admin.products.index'))
        ->assertSessionHas('success');

    // The queue is synchronous in tests, so the queued batches already ran.
    expect($image->fresh()->broken_at)->not->toBeNull();

    $this->actingAs($this->admin)
        ->get(route('admin.products.index'))
        ->assertInertia(fn ($page) => $page
            ->where('brokenImagesCount', 1)
            ->where('imagesCheckedAt', fn ($at) => $at !== null));
});

test('a product photo can be replaced in place from the products list', function () {
    Storage::fake('public');
    $product = filterProduct('Vase');
    $old = filterImage($product, now()->toDateTimeString());
    Storage::disk('public')->put($old->path, 'old-bytes');

    $this->actingAs($this->admin)
        ->post(route('admin.products.photos.store', $product), [
            'media_id' => $old->id,
            'file' => UploadedFile::fake()->create('new.jpg', 40, 'image/jpeg'),
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Photo remplacée.');

    $media = $old->fresh();

    expect($media->path)->not->toBe($old->path)
        ->and($media->path)->toStartWith("products/{$product->id}/")
        ->and($media->broken_at)->toBeNull()
        ->and($product->media()->count())->toBe(1);
    Storage::disk('public')->assertExists($media->path);
    Storage::disk('public')->assertMissing($old->path);
});

test('a variant photo is replaced inside the variant folder', function () {
    Storage::fake('public');
    $product = filterProduct('Tapis', ['has_variants' => true]);
    $variant = ProductVariant::create(['product_id' => $product->id, 'stock' => 1]);
    $old = filterImage($variant, now()->toDateTimeString());

    $this->actingAs($this->admin)
        ->post(route('admin.products.photos.store', $product), [
            'media_id' => $old->id,
            'file' => UploadedFile::fake()->create('new.jpg', 40, 'image/jpeg'),
        ])
        ->assertRedirect();

    expect($old->fresh()->path)->toStartWith("products/{$product->id}/variants/{$variant->id}/");
});

test('without a target the photo is added to the end of the gallery', function () {
    Storage::fake('public');
    $product = filterProduct('Vase');
    filterImage($product)->update(['order' => 3]);

    $this->actingAs($this->admin)
        ->post(route('admin.products.photos.store', $product), [
            'file' => UploadedFile::fake()->create('new.jpg', 40, 'image/jpeg'),
        ])
        ->assertSessionHas('success', 'Photo ajoutée.');

    expect($product->media()->count())->toBe(2)
        ->and((int) $product->media()->max('order'))->toBe(4);
});

test('a photo of another product cannot be replaced through this one', function () {
    Storage::fake('public');
    $other = filterImage(filterProduct('Autre'));

    $this->actingAs($this->admin)
        ->post(route('admin.products.photos.store', filterProduct('Vase')), [
            'media_id' => $other->id,
            'file' => UploadedFile::fake()->create('new.jpg', 40, 'image/jpeg'),
        ])
        ->assertNotFound();
});

test('a pasted image link is fetched and served back for cropping', function () {
    // 1x1 transparent PNG.
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
    Http::fake(['encrypted-tbn2.gstatic.com/*' => Http::response($png, 200, ['Content-Type' => 'image/png'])]);

    // No DNS in tests: pretend the host resolves to a public address.
    app()->instance(ProductPhotoService::class, new class extends ProductPhotoService
    {
        protected function resolve(string $host): array
        {
            return ['142.250.200.14'];
        }
    });

    $this->actingAs($this->admin)
        ->get(route('admin.products.remote-image', ['url' => 'https://encrypted-tbn2.gstatic.com/images?q=tbn:abc']))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

test('links to private addresses or to non-images are refused', function (string $url) {
    Http::fake(['*' => Http::response('<html>not an image</html>')]);

    $this->actingAs($this->admin)
        ->getJson(route('admin.products.remote-image', ['url' => $url]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('url');
})->with([
    'loopback' => 'http://127.0.0.1/photo.jpg',
    'private network' => 'http://10.0.0.5:8333/hirfaty/photo.jpg',
    'not http' => 'file:///etc/passwd',
    'not an image' => 'http://93.184.216.34/page.html',
]);

test('sellers cannot use the photo shortcuts', function () {
    $seller = User::factory()->create(['role' => 'seller']);

    $this->actingAs($seller)->post(route('admin.products.check-images'))->assertRoleDenied();
    $this->actingAs($seller)->get(route('admin.products.remote-image', ['url' => 'https://example.com/a.jpg']))->assertRoleDenied();
});
