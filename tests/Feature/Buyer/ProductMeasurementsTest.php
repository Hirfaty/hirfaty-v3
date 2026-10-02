<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $seller = User::factory()->create(['role' => 'seller']);

    $this->store = Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Atelier Mesures',
        'slug' => 'atelier-mesures',
        'status' => 'active',
    ]);
});

function measuredProduct(Store $store, array $attributes = []): Product
{
    return Product::create(array_merge([
        'store_id' => $store->id,
        'name' => 'Tapis',
        'slug' => 'tapis-'.uniqid(),
        'base_price' => 300,
        'stock' => 3,
        'status' => 'published',
    ], $attributes));
}

test('the product page receives the packed weight and dimensions', function () {
    $product = measuredProduct($this->store, [
        'weight' => 2.5, 'weight_unit' => 'kg',
        'length' => 120, 'width' => 80, 'height' => 15, 'dimension_unit' => 'cm',
    ]);

    $this->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('product.measurements.weight', 2.5)
            ->where('product.measurements.weight_unit', 'kg')
            ->where('product.measurements.length', 120)
            ->where('product.measurements.width', 80)
            ->where('product.measurements.height', 15)
            ->where('product.measurements.dimension_unit', 'cm'));
});

test('blank or zero figures arrive as null, so the page shows nothing for them', function () {
    $product = measuredProduct($this->store, ['weight' => 0]);

    $this->get(route('products.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('product.measurements.weight', null)
            ->where('product.measurements.length', null)
            ->where('product.measurements.width', null)
            ->where('product.measurements.height', null));
});

test('a variant carries its own figures, null where it inherits', function () {
    $product = measuredProduct($this->store, ['weight' => 2, 'has_variants' => true, 'stock' => 0]);

    ProductVariant::create([
        'product_id' => $product->id,
        'stock' => 2,
        'is_active' => true,
        'weight' => 3.25,
        'length' => 200,
    ]);

    $this->get(route('products.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('product.variants.0.measurements.weight', 3.25)
            ->where('product.variants.0.measurements.length', 200)
            ->where('product.variants.0.measurements.width', null));
});
