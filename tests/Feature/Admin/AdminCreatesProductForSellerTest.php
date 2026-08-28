<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeSellerStore(): Store
{
    $seller = User::factory()->create(['role' => 'seller']);
    $category = Category::create(['name' => 'Poterie', 'slug' => 'poterie']);
    $city = City::create(['name' => 'Fès']);

    return Store::create([
        'user_id' => $seller->id,
        'category_id' => $category->id,
        'city_id' => $city->id,
        'store_name' => 'Atelier de Fès',
        'slug' => 'atelier-de-fes',
    ]);
}

test('an admin can open the create-product page for a specific seller store', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);
    $store = makeSellerStore();

    $this->actingAs($admin)
        ->get(route('admin.products.create', ['store_id' => $store->id]))
        ->assertOk();
});

test('an admin can publish a product on behalf of a seller', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);
    $store = makeSellerStore();

    $response = $this->actingAs($admin)->post(route('admin.products.store'), [
        'store_id' => $store->id,
        'name' => 'Tajine émaillé',
        'description' => '<p>Fait main à Fès.</p>',
        'base_price' => 240,
        'stock' => 3,
        'category_id' => $store->category_id,
        'status' => 'published',
    ]);

    $response->assertRedirect(route('admin.users.show', $store->user_id));

    $product = $store->products()->firstOrFail();
    expect($product->name)->toBe('Tajine émaillé');
    expect($product->description)->toBe('<p>Fait main à Fès.</p>');
    expect($product->status)->toBe('published');
    expect($product->reviewed_by)->toBe($admin->id);
    expect($product->reviewed_at)->not->toBeNull();
});

test('an admin can save a product as a draft instead of publishing', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);
    $store = makeSellerStore();

    $this->actingAs($admin)->post(route('admin.products.store'), [
        'store_id' => $store->id,
        'name' => 'Plat en terre cuite',
        'base_price' => 120,
        'stock' => 2,
        'category_id' => $store->category_id,
        'status' => 'draft',
    ])->assertRedirect(route('admin.users.show', $store->user_id));

    $product = $store->products()->firstOrFail();
    expect($product->status)->toBe('draft');
    // A draft has not been reviewed, so no reviewer should be stamped.
    expect($product->reviewed_by)->toBeNull();
    expect($product->reviewed_at)->toBeNull();
});

test('the product status must be either draft or published', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);
    $store = makeSellerStore();

    $this->actingAs($admin)->post(route('admin.products.store'), [
        'store_id' => $store->id,
        'name' => 'Statut invalide',
        'base_price' => 50,
        'stock' => 1,
        'category_id' => $store->category_id,
        'status' => 'archived',
    ])->assertSessionHasErrors('status');

    expect($store->products()->count())->toBe(0);
});

test('the create-product route is not swallowed by the product show route', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);

    // Without correct route ordering, "/admin/products/create" would resolve as
    // show() with {product} = "create" and 404 on model binding.
    $this->actingAs($admin)
        ->get(route('admin.products.create'))
        ->assertOk();
});

test('a non-admin cannot create products on behalf of a seller', function () {
    $store = makeSellerStore();
    $buyer = User::factory()->create(['role' => 'buyer']);

    $this->actingAs($buyer)
        ->get(route('admin.products.create', ['store_id' => $store->id]))
        ->assertForbidden();

    $this->actingAs($buyer)
        ->post(route('admin.products.store'), [
            'store_id' => $store->id,
            'name' => 'Produit interdit',
            'base_price' => 10,
            'stock' => 1,
            'category_id' => $store->category_id,
            'status' => 'published',
        ])
        ->assertForbidden();

    expect($store->products()->count())->toBe(0);
});
