<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'super_admin']);

    $this->category = Category::create([
        'slug' => 'tapis',
        'name' => ['fr' => 'Tapis', 'ar' => 'زربية', 'en' => 'Rugs'],
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

test('a draft can be saved with only a name and seller', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), [
            'store_id' => $this->store->id,
            'name' => 'Tapis à compléter',
            'status' => 'draft',
        ])
        ->assertRedirect();

    $product = Product::firstWhere('name', 'Tapis à compléter');

    expect($product)->not->toBeNull()
        ->and($product->isDraft())->toBeTrue()
        // A draft has not been reviewed, so no reviewer is stamped.
        ->and($product->reviewed_by)->toBeNull()
        ->and($product->reviewed_at)->toBeNull();
});

/**
 * `base_price` is deliberately absent from this list: it is optional even on
 * publish, because the admin catalogues an artisan's work before the price is
 * agreed. An unpriced product is kept off the storefront instead of being
 * refused — see AdminUnpricedProductTest.
 */
test('publishing still enforces the full rule set', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), [
            'store_id' => $this->store->id,
            'name' => 'Incomplet',
            'status' => 'published',
        ])
        ->assertSessionHasErrors(['category_id', 'stock']);

    expect(Product::count())->toBe(0);
});

test('a draft can be completed and published later', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), [
            'store_id' => $this->store->id,
            'name' => 'Brouillon',
            'status' => 'draft',
        ]);

    $product = Product::firstWhere('name', 'Brouillon');

    $this->actingAs($this->admin)
        ->post(route('admin.products.update', $product), [
            'name' => 'Tapis fini',
            'category_id' => $this->category->id,
            'base_price' => 1500,
            'stock' => 4,
            'status' => 'published',
        ])
        ->assertRedirect();

    $product->refresh();

    expect($product->status)->toBe('published')
        ->and($product->name)->toBe('Tapis fini')
        ->and((float) $product->base_price)->toBe(1500.0)
        ->and($product->reviewed_by)->toBe($this->admin->id);
});

test('drafts never appear in the storefront', function () {
    Product::create([
        'store_id' => $this->store->id,
        'category_id' => $this->category->id,
        'name' => 'Caché',
        'slug' => 'cache',
        'base_price' => 100,
        'stock' => 1,
        'status' => 'draft',
    ]);

    $this->get(route('products.index'))
        ->assertSuccessful()
        ->assertDontSee('Caché');
});

test('variant options record the axis separately from the value', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), [
            'store_id' => $this->store->id,
            'category_id' => $this->category->id,
            'name' => 'Babouche',
            'base_price' => 300,
            'status' => 'published',
            'variants' => [
                [
                    'stock' => 5,
                    'options' => [
                        ['type' => 'color', 'value' => 'Bleu Majorelle'],
                        ['type' => 'dimension', 'value' => '42'],
                        ['type' => 'material', 'value' => 'Cuir'],
                        [
                            'type' => 'other',
                            'name' => 'Tressage',
                            'value' => 'Serré',
                        ],
                    ],
                ],
            ],
        ])
        ->assertRedirect();

    $options = Product::firstWhere('name', 'Babouche')
        ->variants->first()->options;

    /*
     * `name` is the axis and `value` the point on it. These used to both hold
     * the label, which made the storefront build one dead selector row per
     * value instead of one row per axis.
     */
    expect($options->firstWhere('type', 'color')->only(['name', 'value']))
        ->toBe(['name' => 'Couleur', 'value' => 'Bleu Majorelle'])
        ->and($options->firstWhere('type', 'dimension')->only(['name', 'value']))
        ->toBe(['name' => 'Dimension', 'value' => '42'])
        ->and($options->firstWhere('type', 'material')->only(['name', 'value']))
        ->toBe(['name' => 'Matière', 'value' => 'Cuir'])
        // Only `other` lets the seller name the axis themselves.
        ->and($options->firstWhere('type', 'other')->only(['name', 'value']))
        ->toBe(['name' => 'Tressage', 'value' => 'Serré']);
});

test('an option without a value is rejected', function () {
    // The value is what the shopper actually picks; an option carrying only an
    // axis would render an empty chip that resolves to no variant.
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), [
            'store_id' => $this->store->id,
            'category_id' => $this->category->id,
            'name' => 'Option vide',
            'base_price' => 10,
            'status' => 'published',
            'variants' => [
                [
                    'stock' => 1,
                    'options' => [
                        ['type' => 'color', 'name' => 'Couleur'],
                    ],
                ],
            ],
        ])
        ->assertSessionHasErrors('variants.0.options.0.value');
});

test('an unknown option type is rejected', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), [
            'store_id' => $this->store->id,
            'category_id' => $this->category->id,
            'name' => 'Type inconnu',
            'base_price' => 10,
            'status' => 'published',
            'variants' => [
                ['stock' => 1, 'options' => [['type' => 'weight', 'name' => 'x']]],
            ],
        ])
        ->assertSessionHasErrors('variants.0.options.0.type');
});

test('uploaded media is stored and ordered as submitted', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), [
            'store_id' => $this->store->id,
            'category_id' => $this->category->id,
            'name' => 'Avec photos',
            'base_price' => 200,
            'stock' => 2,
            'status' => 'published',
            'media' => [
                UploadedFile::fake()->create('first.jpg', 40, 'image/jpeg'),
                UploadedFile::fake()->create('second.jpg', 40, 'image/jpeg'),
            ],
        ])
        ->assertRedirect();

    $media = Product::firstWhere('name', 'Avec photos')->media()->orderBy('order')->get();

    expect($media)->toHaveCount(2)
        ->and($media[0]->order)->toBe(0)
        ->and($media[1]->order)->toBe(1);

    Storage::disk('public')->assertExists($media[0]->path);
});

test('an oversized product photo is rejected', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), [
            'store_id' => $this->store->id,
            'category_id' => $this->category->id,
            'name' => 'Photo trop lourde',
            'base_price' => 200,
            'stock' => 2,
            'status' => 'published',
            'media' => [
                // Over config('media.image.max_kb') (5 MB) — a video is
                // allowed to be this big, an image is not.
                UploadedFile::fake()->create('huge.jpg', 6000, 'image/jpeg'),
            ],
        ])
        ->assertSessionHasErrors('media.0');

    expect(Product::firstWhere('name', 'Photo trop lourde'))->toBeNull();
});

test('a video under the video limit but over the image limit is still accepted', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), [
            'store_id' => $this->store->id,
            'category_id' => $this->category->id,
            'name' => 'Avec vidéo',
            'base_price' => 200,
            'stock' => 2,
            'status' => 'published',
            'media' => [
                // Bigger than the 5 MB image cap but under the 50 MB video
                // cap — proves the two media types aren't held to the same
                // flat size limit.
                UploadedFile::fake()->create('demo.mp4', 20000, 'video/mp4'),
            ],
        ])
        ->assertRedirect();

    expect(Product::firstWhere('name', 'Avec vidéo'))->not->toBeNull();
});

test('an oversized product video is rejected', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), [
            'store_id' => $this->store->id,
            'category_id' => $this->category->id,
            'name' => 'Vidéo trop lourde',
            'base_price' => 200,
            'stock' => 2,
            'status' => 'published',
            'media' => [
                // Over config('media.video.max_kb') (50 MB).
                UploadedFile::fake()->create('huge.mp4', 60000, 'video/mp4'),
            ],
        ])
        ->assertSessionHasErrors('media.0');
});

test('editing can delete media and reorder what remains', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)->post(route('admin.products.store'), [
        'store_id' => $this->store->id,
        'category_id' => $this->category->id,
        'name' => 'Galerie',
        'base_price' => 200,
        'stock' => 2,
        'status' => 'published',
        'media' => [
            UploadedFile::fake()->create('a.jpg', 40, 'image/jpeg'),
            UploadedFile::fake()->create('b.jpg', 40, 'image/jpeg'),
            UploadedFile::fake()->create('c.jpg', 40, 'image/jpeg'),
        ],
    ]);

    $product = Product::firstWhere('name', 'Galerie');
    $ids = $product->media()->orderBy('order')->pluck('id')->all();

    // Drop the first image and swap the order of the two that remain.
    $this->actingAs($this->admin)
        ->post(route('admin.products.update', $product), [
            'name' => 'Galerie',
            'category_id' => $this->category->id,
            'base_price' => 200,
            'stock' => 2,
            'status' => 'published',
            'deleted_media' => [$ids[0]],
            'media_order' => [$ids[2], $ids[1]],
        ])
        ->assertRedirect();

    $remaining = $product->fresh()->media()->orderBy('order')->pluck('id')->all();

    expect($remaining)->toBe([$ids[2], $ids[1]]);
});

test('a product keeps its own sku when re-saved', function () {
    $this->actingAs($this->admin)->post(route('admin.products.store'), [
        'store_id' => $this->store->id,
        'category_id' => $this->category->id,
        'name' => 'Avec SKU',
        'sku' => 'TAP-001',
        'base_price' => 100,
        'stock' => 1,
        'status' => 'published',
    ]);

    $product = Product::firstWhere('sku', 'TAP-001');

    // Re-submitting the same SKU must not collide with the product itself.
    $this->actingAs($this->admin)
        ->post(route('admin.products.update', $product), [
            'name' => 'Avec SKU',
            'sku' => 'TAP-001',
            'category_id' => $this->category->id,
            'base_price' => 120,
            'stock' => 1,
            'status' => 'published',
        ])
        ->assertSessionHasNoErrors();
});
