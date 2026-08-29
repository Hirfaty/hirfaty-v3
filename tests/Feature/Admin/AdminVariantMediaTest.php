<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Media;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->admin = User::factory()->create(['role' => 'super_admin']);

    $this->category = Category::create([
        'slug' => 'bijoux',
        'name' => ['fr' => 'Bijoux', 'ar' => 'مجوهرات', 'en' => 'Jewelry'],
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

/** One variant with a single colour option, ready to attach a photo to. */
function variantPayload(array $overrides = []): array
{
    return array_merge([
        'stock' => 5,
        'options' => [
            [
                'type' => 'color',
                'name' => 'Bleu Majorelle',
                'color_hex' => '#2743CE',
            ],
        ],
    ], $overrides);
}

test('a photo uploaded for a variant is stored and attached to that variant', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), [
            'store_id' => $this->store->id,
            'category_id' => $this->category->id,
            'name' => 'Collier Bleu',
            'base_price' => 300,
            'status' => 'published',
            'variants' => [
                variantPayload([
                    'media' => [
                        UploadedFile::fake()->create('bleu.jpg', 40, 'image/jpeg'),
                    ],
                ]),
            ],
        ])
        ->assertRedirect();

    $variant = Product::firstWhere('name', 'Collier Bleu')->variants->first();

    expect($variant->media)->toHaveCount(1);
    Storage::disk('public')->assertExists($variant->media->first()->path);
});

test('an oversized variant photo is rejected', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), [
            'store_id' => $this->store->id,
            'category_id' => $this->category->id,
            'name' => 'Collier Trop Lourd',
            'base_price' => 300,
            'status' => 'published',
            'variants' => [
                variantPayload([
                    'media' => [
                        // Over config('media.image.max_kb') (5 MB).
                        UploadedFile::fake()->create('huge.jpg', 6000, 'image/jpeg'),
                    ],
                ]),
            ],
        ])
        ->assertSessionHasErrors('variants.0.media.0');

    expect(Product::firstWhere('name', 'Collier Trop Lourd'))->toBeNull();
});

test('a variant video is rejected — only images are accepted for variant photos', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), [
            'store_id' => $this->store->id,
            'category_id' => $this->category->id,
            'name' => 'Collier Vidéo',
            'base_price' => 300,
            'status' => 'published',
            'variants' => [
                variantPayload([
                    'media' => [
                        UploadedFile::fake()->create('demo.mp4', 40, 'video/mp4'),
                    ],
                ]),
            ],
        ])
        ->assertSessionHasErrors('variants.0.media.0');
});

test('re-saving a variant without touching its photo keeps the photo', function () {
    $this->actingAs($this->admin)->post(route('admin.products.store'), [
        'store_id' => $this->store->id,
        'category_id' => $this->category->id,
        'name' => 'Collier Persistant',
        'base_price' => 300,
        'status' => 'published',
        'variants' => [
            variantPayload([
                'media' => [
                    UploadedFile::fake()->create('bleu.jpg', 40, 'image/jpeg'),
                ],
            ]),
        ],
    ]);

    $product = Product::firstWhere('name', 'Collier Persistant');
    $variant = $product->variants->first();
    $mediaPath = $variant->media->first()->path;

    // Re-save with the same single variant at the same position (index 0),
    // no new media and nothing marked for deletion — simulates the admin
    // just tweaking stock on the edit screen.
    $this->actingAs($this->admin)
        ->post(route('admin.products.update', $product), [
            'name' => 'Collier Persistant',
            'category_id' => $this->category->id,
            'base_price' => 300,
            'status' => 'published',
            'variants' => [
                variantPayload(['stock' => 9]),
            ],
        ])
        ->assertRedirect();

    $variant->refresh();

    expect($variant->stock)->toBe(9)
        ->and($variant->media)->toHaveCount(1)
        ->and($variant->media->first()->path)->toBe($mediaPath);
    Storage::disk('public')->assertExists($mediaPath);
});

test('deleting a variant photo removes the file from storage', function () {
    $this->actingAs($this->admin)->post(route('admin.products.store'), [
        'store_id' => $this->store->id,
        'category_id' => $this->category->id,
        'name' => 'Collier Supprimé',
        'base_price' => 300,
        'status' => 'published',
        'variants' => [
            variantPayload([
                'media' => [
                    UploadedFile::fake()->create('bleu.jpg', 40, 'image/jpeg'),
                ],
            ]),
        ],
    ]);

    $product = Product::firstWhere('name', 'Collier Supprimé');
    $variant = $product->variants->first();
    $media = $variant->media->first();

    $this->actingAs($this->admin)
        ->post(route('admin.products.update', $product), [
            'name' => 'Collier Supprimé',
            'category_id' => $this->category->id,
            'base_price' => 300,
            'status' => 'published',
            'variants' => [
                variantPayload(['deleted_media' => [$media->id]]),
            ],
        ])
        ->assertRedirect();

    $variant->refresh();

    expect($variant->media)->toHaveCount(0);
    Storage::disk('public')->assertMissing($media->path);
    expect(Media::find($media->id))->toBeNull();
});

test('removing a variant slot cleans up its photos too', function () {
    $this->actingAs($this->admin)->post(route('admin.products.store'), [
        'store_id' => $this->store->id,
        'category_id' => $this->category->id,
        'name' => 'Deux Déclinaisons',
        'base_price' => 300,
        'status' => 'published',
        'variants' => [
            variantPayload([
                'options' => [['type' => 'color', 'name' => 'Bleu', 'color_hex' => '#2743CE']],
                'media' => [UploadedFile::fake()->create('bleu.jpg', 40, 'image/jpeg')],
            ]),
            variantPayload([
                'options' => [['type' => 'color', 'name' => 'Rouge', 'color_hex' => '#DC2626']],
                'media' => [UploadedFile::fake()->create('rouge.jpg', 40, 'image/jpeg')],
            ]),
        ],
    ]);

    $product = Product::firstWhere('name', 'Deux Déclinaisons');
    $secondVariant = $product->variants()->orderBy('position')->get()[1];
    $secondMediaPath = $secondVariant->media->first()->path;

    // Only resubmit the first variant — the second slot is gone.
    $this->actingAs($this->admin)
        ->post(route('admin.products.update', $product), [
            'name' => 'Deux Déclinaisons',
            'category_id' => $this->category->id,
            'base_price' => 300,
            'status' => 'published',
            'variants' => [
                variantPayload([
                    'options' => [['type' => 'color', 'name' => 'Bleu', 'color_hex' => '#2743CE']],
                ]),
            ],
        ])
        ->assertRedirect();

    $product->refresh();

    expect($product->variants)->toHaveCount(1);
    Storage::disk('public')->assertMissing($secondMediaPath);
});
