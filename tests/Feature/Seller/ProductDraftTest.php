<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createSellerWithDraftProduct(): array
{
    $seller = User::factory()->create(['role' => 'seller']);
    $category = Category::create(['name' => 'Bijoux', 'slug' => 'bijoux']);
    $city = City::create(['name' => 'Marrakech']);
    $store = Store::create([
        'user_id' => $seller->id,
        'category_id' => $category->id,
        'city_id' => $city->id,
        'store_name' => 'Bijoux Marrakech',
        'slug' => 'bijoux-marrakech',
    ]);

    $product = $store->products()->create([
        'name' => 'Bracelet berbère',
        'slug' => 'bracelet-berbere',
        'base_price' => 90,
        'stock' => 5,
        'category_id' => $category->id,
        'status' => 'draft',
    ]);

    return [$seller, $product];
}

test('seller can submit a draft product for review', function () {
    [$seller, $product] = createSellerWithDraftProduct();

    $response = $this->actingAs($seller)
        ->patch(route('seller.products.submit', $product));

    $response->assertRedirect(route('seller.products.index'));
    expect($product->fresh()->status)->toBe('pending_review');
});

test('seller cannot submit a non-draft product for review', function () {
    [$seller, $product] = createSellerWithDraftProduct();
    $product->update(['status' => 'pending_review']);

    $response = $this->actingAs($seller)
        ->patch(route('seller.products.submit', $product));

    $response->assertSessionHasErrors('status');
    expect($product->fresh()->status)->toBe('pending_review');
});

test('a seller cannot submit another seller\'s draft product', function () {
    [, $product] = createSellerWithDraftProduct();
    $otherSeller = User::factory()->create(['role' => 'seller']);
    Store::create([
        'user_id' => $otherSeller->id,
        'category_id' => $product->category_id,
        'city_id' => $product->store->city_id,
        'store_name' => 'Autre Boutique',
        'slug' => 'autre-boutique',
    ]);

    $this->actingAs($otherSeller)
        ->patch(route('seller.products.submit', $product))
        ->assertForbidden();

    expect($product->fresh()->status)->toBe('draft');
});

test('editing a draft product keeps it a draft', function () {
    [$seller, $product] = createSellerWithDraftProduct();

    $response = $this->actingAs($seller)->put(route('seller.products.update', $product), [
        'name' => 'Bracelet berbère édité',
        'base_price' => 95,
        'stock' => 4,
        'category_id' => $product->category_id,
    ]);

    $response->assertRedirect(route('seller.products.index'));
    expect($product->fresh()->status)->toBe('draft');
});
