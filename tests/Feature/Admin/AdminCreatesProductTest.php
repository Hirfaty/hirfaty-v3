<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;

/**
 * The CEO builds the whole catalogue from the admin panel — sellers fill in
 * nothing — so these cover the admin-side create flow.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'super_admin']);

    $seller = User::factory()->create(['role' => 'seller']);
    $this->store = Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Atelier Fès',
        'slug' => 'atelier-fes',
        'category_id' => Category::create([
            'slug' => 'poterie',
            'name' => ['fr' => 'Poterie', 'ar' => 'فخار', 'en' => 'Pottery'],
        ])->id,
        'city_id' => City::create(['name' => 'Fès'])->id,
    ]);

    $this->category = Category::create([
        'slug' => 'tapis',
        'name' => ['fr' => 'Tapis', 'ar' => 'زربية', 'en' => 'Rugs'],
    ]);
});

test('admin creates a simple product with both price lists', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), [
            'store_id' => $this->store->id,
            'category_id' => $this->category->id,
            'name' => 'Tapis Beni Ouarain',
            'description' => 'Laine naturelle.',
            'base_price' => 2000,
            'sale_price' => 1800,
            'professional_price' => 1400,
            'professional_sale_price' => 1200,
            'min_order_qty_professional' => 5,
            'stock' => 12,
            'visibility' => 'both',
        ])
        ->assertRedirect();

    $product = Product::firstWhere('name', 'Tapis Beni Ouarain');

    expect($product)->not->toBeNull()
        ->and($product->has_variants)->toBeFalse()
        ->and($product->stock)->toBe(12)
        ->and($product->min_order_qty_professional)->toBe(5)
        // Sale prices win over the list prices for each audience.
        ->and($product->priceFor(Product::AUDIENCE_INDIVIDUAL))->toBe(1800.0)
        ->and($product->priceFor(Product::AUDIENCE_PROFESSIONAL))->toBe(1200.0);
});

test('admin creates a product with colour and size variants', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), [
            'store_id' => $this->store->id,
            'category_id' => $this->category->id,
            'name' => 'Babouche brodée',
            'base_price' => 300,
            'professional_price' => 210,
            'variants' => [
                [
                    'sku' => 'BAB-RED-40',
                    'stock' => 4,
                    'options' => [
                        ['type' => 'color', 'name' => 'Rouge', 'color_hex' => '#DC2626'],
                        ['type' => 'size', 'name' => '40'],
                    ],
                ],
                [
                    'sku' => 'BAB-BLU-42',
                    'stock' => 7,
                    'price_override' => 340,
                    'options' => [
                        ['type' => 'color', 'name' => 'Bleu', 'color_hex' => '#2743CE'],
                        ['type' => 'size', 'name' => '42'],
                    ],
                ],
            ],
        ])
        ->assertRedirect();

    $product = Product::with('variants.options')->firstWhere('name', 'Babouche brodée');

    expect($product->has_variants)->toBeTrue()
        ->and($product->variants)->toHaveCount(2);

    $red = $product->variants->firstWhere('sku', 'BAB-RED-40');
    $blue = $product->variants->firstWhere('sku', 'BAB-BLU-42');

    expect($red->options->pluck('value')->sort()->values()->all())->toBe(['40', 'Rouge'])
        // No override, so it inherits the product's price for each audience.
        ->and($red->priceFor('individual'))->toBe(300.0)
        ->and($red->priceFor('professional'))->toBe(210.0)
        // Overrides only the retail price; professional still inherits.
        ->and($blue->priceFor('individual'))->toBe(340.0)
        ->and($blue->priceFor('professional'))->toBe(210.0);
});

test('stock lives on the variants, not the parent product', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), [
            'store_id' => $this->store->id,
            'category_id' => $this->category->id,
            'name' => 'Sac cuir',
            'base_price' => 500,
            'variants' => [
                [
                    'stock' => 3,
                    'options' => [
                        ['type' => 'color', 'name' => 'Camel', 'color_hex' => '#8B5E3C'],
                    ],
                ],
            ],
        ])
        ->assertRedirect();

    $product = Product::firstWhere('name', 'Sac cuir');

    expect($product->stock)->toBe(0)
        ->and($product->variants->sum('stock'))->toBe(3);
});

test('a product without variants must declare stock', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), [
            'store_id' => $this->store->id,
            'category_id' => $this->category->id,
            'name' => 'Sans stock',
            'base_price' => 100,
        ])
        ->assertSessionHasErrors('stock');
});

test('a sale price above the base price is rejected', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), [
            'store_id' => $this->store->id,
            'category_id' => $this->category->id,
            'name' => 'Prix incoherent',
            'base_price' => 100,
            'sale_price' => 150,
            'stock' => 1,
        ])
        ->assertSessionHasErrors('sale_price');
});

test('professional pricing falls back to retail when not set', function () {
    $product = Product::create([
        'store_id' => $this->store->id,
        'category_id' => $this->category->id,
        'name' => 'Retail only',
        'slug' => 'retail-only',
        'base_price' => 250,
        'stock' => 5,
        'status' => 'published',
    ]);

    // No professional price configured — pros are billed the retail price
    // rather than getting a null total.
    expect($product->priceFor(Product::AUDIENCE_PROFESSIONAL))->toBe(250.0);
});

test('non admins cannot create products', function () {
    $seller = User::factory()->create(['role' => 'seller']);

    $this->actingAs($seller)
        ->post(route('admin.products.store'), [
            'store_id' => $this->store->id,
            'category_id' => $this->category->id,
            'name' => 'Nope',
            'base_price' => 10,
            'stock' => 1,
        ])
        ->assertForbidden();
});
