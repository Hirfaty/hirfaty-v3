<?php

use App\Models\City;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('super admin can view an order', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);
    
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
    
    $order = Order::create([
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

    $response = $this->actingAs($admin)
        ->get(route('admin.orders.show', $order));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/orders/show')
        ->has('order', fn (Assert $page) => $page
            ->where('id', $order->id)
            ->where('total_amount', "100.00")
            ->has('buyer')
            ->has('store')
            ->has('items')
            ->has('city')
            ->etc()
        )
    );
});

test('sellers cannot view orders via admin route', function () {
    $seller = User::factory()->create(['role' => 'seller']);
    
    $city = City::create(['name' => 'Casablanca']);
    $category = Category::create(['name' => 'Poterie', 'slug' => 'poterie']);
    
    $otherSeller = User::factory()->create(['role' => 'seller']);
    $store = Store::create([
        'user_id' => $otherSeller->id,
        'category_id' => $category->id,
        'city_id' => $city->id,
        'store_name' => 'Test Store',
        'slug' => 'test-store',
    ]);
    
    $buyer = User::factory()->create(['role' => 'buyer']);
    
    $order = Order::create([
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

    $response = $this->actingAs($seller)
        ->get(route('admin.orders.show', $order));

    $response->assertForbidden();
});
