<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;

/**
 * Variant references (SKUs) are unique across the whole table. These pin the
 * save paths that used to reach the index as a 500 rather than a form error.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'super_admin']);

    $seller = User::factory()->create(['role' => 'seller']);
    $this->category = Category::create([
        'slug' => 'poterie',
        'name' => ['fr' => 'Poterie', 'ar' => 'فخار', 'en' => 'Pottery'],
    ]);
    $this->store = Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Atelier Fès',
        'slug' => 'atelier-fes',
        'category_id' => $this->category->id,
        'city_id' => City::create(['name' => 'Fès'])->id,
    ]);
});

function skuVariant(string $colour, ?string $sku, ?int $id = null): array
{
    return array_filter([
        'id' => $id,
        'sku' => $sku,
        'stock' => 3,
        'options' => [['type' => 'color', 'value' => $colour]],
    ], fn ($v) => $v !== null);
}

function skuPayload(array $variants, array $overrides = []): array
{
    return array_merge([
        'store_id' => test()->store->id,
        'category_id' => test()->category->id,
        'name' => 'Plat en terre',
        'base_price' => 300,
        'variants' => $variants,
    ], $overrides);
}

function createWithVariants(array $variants): Product
{
    test()->actingAs(test()->admin)
        ->post(route('admin.products.store'), skuPayload($variants))
        ->assertSessionHasNoErrors();

    return Product::with('variants')->latest('id')->first();
}

test('resending an already-saved variant as new is a form error, not a crash', function () {
    // The Sentry case: a stale edit form re-sent a variant created by the
    // previous save without its id.
    $product = createWithVariants([skuVariant('Rouge', 'TRM-DECB4')]);

    $this->actingAs($this->admin)
        ->post(route('admin.products.update', $product), skuPayload([
            skuVariant('Rouge', 'TRM-DECB4'),
        ]))
        ->assertSessionHasNoErrors();

    // Own variants are freed first, so the reference simply moves to the
    // re-created row instead of colliding with the old one.
    expect($product->variants()->pluck('sku')->all())->toBe(['TRM-DECB4']);
});

test('a SKU used by another product is rejected with a message', function () {
    createWithVariants([skuVariant('Rouge', 'TRM-DECB4')]);

    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), skuPayload(
            [skuVariant('Bleu', 'TRM-DECB4')],
            ['name' => 'Autre plat'],
        ))
        ->assertSessionHasErrors(['variants.0.sku' => 'Cette référence est déjà utilisée par un autre produit.']);

    expect(Product::where('name', 'Autre plat')->exists())->toBeFalse();
});

test('two variants of one form cannot share a SKU', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), skuPayload([
            skuVariant('Rouge', 'DUP-1'),
            skuVariant('Bleu', 'DUP-1'),
        ]))
        ->assertSessionHasErrors('variants.1.sku');
});

test('removing a variant and re-adding its SKU in one save works', function () {
    $product = createWithVariants([
        skuVariant('Rouge', 'RED-1'),
        skuVariant('Bleu', 'BLU-1'),
    ]);
    $blue = $product->variants->firstWhere('sku', 'BLU-1');

    $this->actingAs($this->admin)
        ->post(route('admin.products.update', $product), skuPayload([
            skuVariant('Bleu', 'BLU-1', $blue->id),
            skuVariant('Vert', 'RED-1'),
        ]))
        ->assertSessionHasNoErrors();

    expect($product->variants()->orderBy('position')->pluck('sku')->all())
        ->toBe(['BLU-1', 'RED-1']);
});

test('two kept variants can swap SKUs', function () {
    $product = createWithVariants([
        skuVariant('Rouge', 'A-1'),
        skuVariant('Bleu', 'B-1'),
    ]);
    $red = $product->variants->firstWhere('sku', 'A-1');
    $blue = $product->variants->firstWhere('sku', 'B-1');

    $this->actingAs($this->admin)
        ->post(route('admin.products.update', $product), skuPayload([
            skuVariant('Rouge', 'B-1', $red->id),
            skuVariant('Bleu', 'A-1', $blue->id),
        ]))
        ->assertSessionHasNoErrors();

    expect($red->fresh()->sku)->toBe('B-1')
        ->and($blue->fresh()->sku)->toBe('A-1');
});

test('an unchanged SKU survives a re-save', function () {
    $product = createWithVariants([skuVariant('Rouge', 'KEEP-1')]);
    $red = $product->variants->first();

    $this->actingAs($this->admin)
        ->post(route('admin.products.update', $product), skuPayload([
            skuVariant('Rouge', 'KEEP-1', $red->id),
        ]))
        ->assertSessionHasNoErrors();

    expect($red->fresh()->sku)->toBe('KEEP-1');
});
