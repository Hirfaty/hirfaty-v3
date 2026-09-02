<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'super_admin']);
});

function makeOrderForTest(User $buyer, Store $store, City $city): Order
{
    return Order::create([
        'buyer_id' => $buyer->id,
        'store_id' => $store->id,
        'city_id' => $city->id,
        'status' => 'pending',
        'total_amount' => 100,
        'platform_fee' => 5,
        'amana_fee' => 15,
        'seller_earnings' => 80,
        'shipping_address' => 'Test Address',
    ]);
}

test('admin deletes a buyer with no order history', function () {
    $buyer = User::factory()->create(['role' => 'buyer']);

    $this->actingAs($this->admin)
        ->delete(route('admin.users.destroy', $buyer))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success');

    expect(User::find($buyer->id))->toBeNull();
});

test('admin deletes a seller with no order history', function () {
    $city = City::create(['name' => 'Casablanca']);
    $category = Category::create(['name' => 'Poterie', 'slug' => 'poterie']);

    $seller = User::factory()->create(['role' => 'seller']);
    Store::create([
        'user_id' => $seller->id,
        'category_id' => $category->id,
        'city_id' => $city->id,
        'store_name' => 'Empty Store',
        'slug' => 'empty-store',
    ]);

    $this->actingAs($this->admin)
        ->delete(route('admin.users.destroy', $seller))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success');

    expect(User::find($seller->id))->toBeNull();
});

test('admin cannot delete a buyer with order history, and nothing is destroyed', function () {
    $city = City::create(['name' => 'Casablanca']);
    $category = Category::create(['name' => 'Poterie', 'slug' => 'poterie']);

    $seller = User::factory()->create(['role' => 'seller']);
    $store = Store::create([
        'user_id' => $seller->id,
        'category_id' => $category->id,
        'city_id' => $city->id,
        'store_name' => 'Test Store',
        'slug' => 'test-store',
    ]);

    $buyer = User::factory()->create(['role' => 'buyer']);
    $order = makeOrderForTest($buyer, $store, $city);

    $this->actingAs($this->admin)
        ->delete(route('admin.users.destroy', $buyer))
        ->assertRedirect()
        ->assertSessionHas('error');

    // The whole point of the guard: buyer, order, and the seller's store all
    // survive untouched.
    expect(User::find($buyer->id))->not->toBeNull()
        ->and(Order::find($order->id))->not->toBeNull()
        ->and(User::find($seller->id))->not->toBeNull();
});

test('admin cannot delete a seller with order history, and nothing is destroyed', function () {
    $city = City::create(['name' => 'Casablanca']);
    $category = Category::create(['name' => 'Poterie', 'slug' => 'poterie']);

    $seller = User::factory()->create(['role' => 'seller']);
    $store = Store::create([
        'user_id' => $seller->id,
        'category_id' => $category->id,
        'city_id' => $city->id,
        'store_name' => 'Test Store',
        'slug' => 'test-store',
    ]);

    $buyer = User::factory()->create(['role' => 'buyer']);
    $order = makeOrderForTest($buyer, $store, $city);

    $this->actingAs($this->admin)
        ->delete(route('admin.users.destroy', $seller))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(User::find($seller->id))->not->toBeNull()
        ->and(Store::find($store->id))->not->toBeNull()
        ->and(Order::find($order->id))->not->toBeNull();
});

test('admin cannot delete another super admin', function () {
    $otherAdmin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($this->admin)
        ->delete(route('admin.users.destroy', $otherAdmin))
        ->assertForbidden();

    expect(User::find($otherAdmin->id))->not->toBeNull();
});

test('a non-admin cannot delete a user', function () {
    $buyer = User::factory()->create(['role' => 'buyer']);
    $otherBuyer = User::factory()->create(['role' => 'buyer']);

    $this->actingAs($buyer)
        ->delete(route('admin.users.destroy', $otherBuyer))
        ->assertForbidden();

    expect(User::find($otherBuyer->id))->not->toBeNull();
});
