<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'super_admin']);

    $this->potterie = Category::create([
        'slug' => 'poterie',
        'name' => ['fr' => 'Poterie', 'ar' => 'فخار', 'en' => 'Pottery'],
    ]);
    $this->tapis = Category::create([
        'slug' => 'tapis',
        'name' => ['fr' => 'Tapis', 'ar' => 'زربية', 'en' => 'Rugs'],
    ]);

    $seller = User::factory()->create(['role' => 'seller']);
    $this->store = Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Atelier Fès',
        'slug' => 'atelier-fes',
        'category_id' => $this->potterie->id,
        'city_id' => City::create(['name' => 'Fès'])->id,
    ]);
});

function makeProduct(string $name, string $status, $storeId, $categoryId): Product
{
    return Product::create([
        'store_id' => $storeId,
        'category_id' => $categoryId,
        'name' => $name,
        'slug' => Str::slug($name).'-'.uniqid(),
        'base_price' => 100,
        'stock' => 1,
        'status' => $status,
    ]);
}

test('status counts reflect the whole catalogue regardless of the active tab', function () {
    makeProduct('Vase A', 'published', $this->store->id, $this->potterie->id);
    makeProduct('Vase B', 'pending_review', $this->store->id, $this->potterie->id);
    makeProduct('Vase C', 'pending_review', $this->store->id, $this->potterie->id);
    makeProduct('Vase D', 'draft', $this->store->id, $this->potterie->id);
    makeProduct('Vase E', 'rejected', $this->store->id, $this->potterie->id);

    $this->actingAs($this->admin)
        ->get(route('admin.products.index', ['status' => 'published']))
        ->assertInertia(
            fn ($page) => $page
                ->component('admin/products/index')
                ->where('totalProducts', 5)
                ->where('statusCounts.published', 1)
                ->where('statusCounts.pending_review', 2)
                ->where('statusCounts.draft', 1)
                ->where('statusCounts.rejected', 1)
                // The tab filter narrows the visible list...
                ->has('products.data', 1)
            // ...but never the counts above.
        );
});

test('the category filter actually narrows the product list', function () {
    makeProduct('Tapis Berbère', 'published', $this->store->id, $this->tapis->id);
    makeProduct('Vase en terre', 'published', $this->store->id, $this->potterie->id);

    $this->actingAs($this->admin)
        ->get(route('admin.products.index', ['category_id' => $this->tapis->id]))
        ->assertInertia(
            fn ($page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.name', 'Tapis Berbère')
        );
});

test('search matches the product name or the seller shop name', function () {
    makeProduct('Coussin brodé', 'published', $this->store->id, $this->potterie->id);

    $otherSeller = User::factory()->create(['role' => 'seller']);
    $otherStore = Store::create([
        'user_id' => $otherSeller->id,
        'store_name' => 'Zellige Marrakech',
        'slug' => 'zellige-marrakech',
        'category_id' => $this->potterie->id,
        'city_id' => City::create(['name' => 'Marrakech'])->id,
    ]);
    makeProduct('Plateau', 'published', $otherStore->id, $this->potterie->id);

    $this->actingAs($this->admin)
        ->get(route('admin.products.index', ['search' => 'Zellige']))
        ->assertInertia(
            fn ($page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.name', 'Plateau')
        );
});

test('each row carries its sku for the table and the review panel', function () {
    makeProduct('Tapis Berbère', 'published', $this->store->id, $this->tapis->id)
        ->update(['sku' => 'TAP-001']);

    $this->actingAs($this->admin)
        ->get(route('admin.products.index'))
        ->assertInertia(
            fn ($page) => $page->where('products.data.0.sku', 'TAP-001')
        );
});

test('search matches the product sku or one of its variant skus', function () {
    makeProduct('Tapis Berbère', 'published', $this->store->id, $this->tapis->id)
        ->update(['sku' => 'TAP-001']);
    $babouches = makeProduct('Babouches', 'published', $this->store->id, $this->tapis->id);
    ProductVariant::create(['product_id' => $babouches->id, 'sku' => 'BAB-RED-40', 'stock' => 1]);
    makeProduct('Vase en terre', 'published', $this->store->id, $this->potterie->id);

    $this->actingAs($this->admin)
        ->get(route('admin.products.index', ['search' => 'TAP-001']))
        ->assertInertia(
            fn ($page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.name', 'Tapis Berbère')
        );

    $this->actingAs($this->admin)
        ->get(route('admin.products.index', ['search' => 'BAB-RED']))
        ->assertInertia(
            fn ($page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.name', 'Babouches')
        );
});

test('category filter and status counts compose together', function () {
    makeProduct('Tapis pending', 'pending_review', $this->store->id, $this->tapis->id);
    makeProduct('Vase pending', 'pending_review', $this->store->id, $this->potterie->id);

    $this->actingAs($this->admin)
        ->get(route('admin.products.index', ['category_id' => $this->tapis->id]))
        ->assertInertia(
            fn ($page) => $page
                ->where('totalProducts', 1)
                ->where('statusCounts.pending_review', 1)
        );
});

test('non admins cannot list products', function () {
    foreach (['buyer', 'professional_buyer', 'seller'] as $role) {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)
            ->get(route('admin.products.index'))
            ->assertRoleDenied();
    }
});
