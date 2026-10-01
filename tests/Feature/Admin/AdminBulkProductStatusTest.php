<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'super_admin']);

    $category = Category::create([
        'slug' => 'poterie',
        'name' => ['fr' => 'Poterie', 'ar' => 'فخار', 'en' => 'Pottery'],
    ]);

    $store = Store::create([
        'user_id' => User::factory()->create(['role' => 'seller'])->id,
        'store_name' => 'Atelier Fès',
        'slug' => 'atelier-fes',
        'category_id' => $category->id,
        'city_id' => City::create(['name' => 'Fès'])->id,
    ]);

    $this->makeProduct = fn (string $status) => Product::create([
        'store_id' => $store->id,
        'category_id' => $category->id,
        'name' => 'Vase '.Str::random(4),
        'slug' => 'vase-'.Str::random(8),
        'base_price' => 200,
        'stock' => 5,
        'status' => $status,
    ]);
});

test('admin can publish several archived products at once', function () {
    $a = ($this->makeProduct)('archived');
    $b = ($this->makeProduct)('archived');

    $this->actingAs($this->admin)
        ->patch(route('admin.products.bulk-status'), ['action' => 'publish', 'ids' => [$a->id, $b->id]])
        ->assertSessionHas('success', '2 products published.');

    expect($a->fresh()->status)->toBe('published')
        ->and($b->fresh()->status)->toBe('published');
});

test('admin can archive several published products at once', function () {
    $a = ($this->makeProduct)('published');
    $b = ($this->makeProduct)('published');

    $this->actingAs($this->admin)
        ->patch(route('admin.products.bulk-status'), ['action' => 'archive', 'ids' => [$a->id, $b->id]])
        ->assertSessionHas('success', '2 products archived.');

    expect($a->fresh()->status)->toBe('archived')
        ->and($b->fresh()->status)->toBe('archived');
});

test('a mixed selection is refused and nothing changes', function () {
    $archived = ($this->makeProduct)('archived');
    $published = ($this->makeProduct)('published');

    $this->actingAs($this->admin)
        ->patch(route('admin.products.bulk-status'), ['action' => 'publish', 'ids' => [$archived->id, $published->id]])
        ->assertSessionHas('error');

    expect($archived->fresh()->status)->toBe('archived')
        ->and($published->fresh()->status)->toBe('published');
});

test('pending products cannot be published through the bulk action', function () {
    $pending = ($this->makeProduct)('pending_review');

    $this->actingAs($this->admin)
        ->patch(route('admin.products.bulk-status'), ['action' => 'publish', 'ids' => [$pending->id]])
        ->assertSessionHas('error');

    expect($pending->fresh()->status)->toBe('pending_review');
});

test('an unknown action is rejected', function () {
    $product = ($this->makeProduct)('archived');

    $this->actingAs($this->admin)
        ->patch(route('admin.products.bulk-status'), ['action' => 'delete', 'ids' => [$product->id]])
        ->assertSessionHasErrors('action');
});

test('sellers cannot use the bulk status route', function () {
    $product = ($this->makeProduct)('archived');

    $this->actingAs(User::factory()->create(['role' => 'seller']))
        ->patch(route('admin.products.bulk-status'), ['action' => 'publish', 'ids' => [$product->id]])
        ->assertRoleDenied();

    expect($product->fresh()->status)->toBe('archived');
});
