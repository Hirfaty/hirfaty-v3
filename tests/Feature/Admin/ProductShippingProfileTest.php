<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\ProductShippingService;

/**
 * Weight, packed dimensions and customs facts the admin files with each
 * product, ahead of the DHL integration that will quote from them.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'super_admin']);

    $this->category = Category::create([
        'slug' => 'tapis',
        'name' => ['fr' => 'Tapis', 'ar' => 'زربية', 'en' => 'Rugs'],
        'hs_code' => '570110',
    ]);

    $seller = User::factory()->create(['role' => 'seller']);
    $this->store = Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Atelier Fès',
        'slug' => 'atelier-fes',
        'category_id' => $this->category->id,
        'city_id' => City::create(['name' => 'Fès'])->id,
    ]);

    $this->base = [
        'store_id' => $this->store->id,
        'category_id' => $this->category->id,
        'name' => 'Tapis Beni Ouarain',
        'base_price' => 2000,
    ];
});

test('admin files a shipping profile in the units they choose', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), [
            ...$this->base,
            'stock' => 3,
            'weight' => 2500,
            'weight_unit' => 'g',
            'length' => 1.2,
            'width' => 0.4,
            'height' => 0.3,
            'dimension_unit' => 'm',
            'hs_code' => '5701.10.90',
            'customs_description' => 'Hand-knotted wool rug',
            'is_fragile' => 1,
            'handling_days' => 3,
        ])
        ->assertSessionHasNoErrors();

    $product = Product::firstWhere('name', 'Tapis Beni Ouarain');
    $shipping = app(ProductShippingService::class);

    expect($product->origin_country)->toBe('MA')
        ->and($product->hs_code)->toBe('57011090')
        ->and($product->is_fragile)->toBeTrue()
        ->and($product->handling_days)->toBe(3)
        ->and($shipping->weightKg($product))->toBe(2.5)
        ->and($shipping->dimensionsCm($product))->toBe(['length' => 120.0, 'width' => 40.0, 'height' => 30.0])
        // 120 × 40 × 30 / 5000 = 28.8 kg volumetric, well over the 2.5 kg actual.
        ->and($shipping->chargeableWeightKg($product))->toBe(28.8)
        ->and($shipping->isComplete($product))->toBeTrue();
});

test('origin country cannot be changed from the form', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), [...$this->base, 'stock' => 1, 'origin_country' => 'FR'])
        ->assertSessionHasNoErrors();

    expect(Product::firstWhere('name', 'Tapis Beni Ouarain')->origin_country)->toBe('MA');
});

test('a product without its own HS code inherits the category one', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), [...$this->base, 'stock' => 1])
        ->assertSessionHasNoErrors();

    $product = Product::firstWhere('name', 'Tapis Beni Ouarain');

    expect($product->hs_code)->toBeNull()
        ->and(app(ProductShippingService::class)->hsCode($product))->toBe('570110')
        // No weight yet: saved fine, but flagged as not quotable.
        ->and(app(ProductShippingService::class)->isComplete($product))->toBeFalse();
});

test('variants override the product weight and dimensions axis by axis', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), [
            ...$this->base,
            'weight' => 3,
            'weight_unit' => 'kg',
            'length' => 200,
            'width' => 40,
            'height' => 40,
            'dimension_unit' => 'cm',
            'variants' => [
                ['stock' => 1, 'options' => [['type' => 'dimension', 'value' => '2 m']]],
                ['stock' => 1, 'weight' => 4.5, 'length' => 300, 'options' => [['type' => 'dimension', 'value' => '3 m']]],
            ],
        ])
        ->assertSessionHasNoErrors();

    $product = Product::with('variants')->firstWhere('name', 'Tapis Beni Ouarain');
    [$small, $large] = $product->variants->all();
    $shipping = app(ProductShippingService::class);

    expect($shipping->weightKg($product, $small))->toBe(3.0)
        ->and($shipping->weightKg($product, $large))->toBe(4.5)
        ->and($shipping->dimensionsCm($product, $large))->toBe(['length' => 300.0, 'width' => 40.0, 'height' => 40.0])
        ->and($shipping->isComplete($product))->toBeTrue();
});

test('shipping figures are validated', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), [
            ...$this->base,
            'stock' => 1,
            'weight' => -1,
            'weight_unit' => 'lb',
            'hs_code' => '12',
            'handling_days' => 100,
        ])
        ->assertSessionHasErrors(['weight', 'weight_unit', 'hs_code', 'handling_days']);
});

test('admin sets a category HS code', function () {
    $this->actingAs($this->admin)
        ->patch(route('admin.categories.update', $this->category), [
            'name' => ['fr' => 'Tapis', 'ar' => 'زربية', 'en' => 'Rugs'],
            'hs_code' => '5702.42',
        ])
        ->assertSessionHasNoErrors();

    expect($this->category->fresh()->hs_code)->toBe('570242');
});
