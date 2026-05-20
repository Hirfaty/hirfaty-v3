<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\City;
use App\Models\Earning;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Notifications\NewOrderReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function createBuyer(): User
{
    return User::factory()->create([
        'role' => 'buyer',
        'email_verified_at' => now(),
    ]);
}

function createStoreWithSeller(string $name): Store
{
    $seller = User::factory()->create([
        'role' => 'seller',
        'email_verified_at' => now(),
        'first_name' => $name,
    ]);

    return Store::create([
        'user_id' => $seller->id,
        'store_name' => "{$name} Store",
        'slug' => Str::slug("{$name}-store-".Str::random(6)),
        'status' => 'active',
    ]);
}

function createProductForStore(Store $store, string $name, float $basePrice, int $stock = 20): Product
{
    return Product::create([
        'store_id' => $store->id,
        'name' => $name,
        'slug' => Str::slug("{$name}-".Str::random(6)),
        'base_price' => $basePrice,
        'sale_price' => null,
        'stock' => $stock,
        'status' => 'published',
    ]);
}

test('buyer is redirected to products when checkout cart is empty', function () {
    $buyer = createBuyer();
    Cart::create(['user_id' => $buyer->id]);

    $response = $this->actingAs($buyer)->get(route('checkout.show'));

    $response->assertRedirect(route('products.index'));
});

test('buyer can view checkout with cart and cities payload', function () {
    $buyer = createBuyer();
    $cityA = City::create(['name' => 'Casablanca', 'region' => 'Casablanca-Settat']);
    $cityB = City::create(['name' => 'Rabat', 'region' => 'Rabat-Sale-Kenitra']);

    $store = createStoreWithSeller('Alpha');
    $product = createProductForStore($store, 'Handmade Vase', 120.50);

    $cart = Cart::create(['user_id' => $buyer->id]);
    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'quantity' => 2,
    ]);

    $response = $this->actingAs($buyer)->get(route('checkout.show'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('buyer/checkout')
        ->has('cart.items', 1)
        ->where('cities.0.id', $cityA->id)
        ->where('cities.1.id', $cityB->id)
    );
});

test('checkout store validates required fields', function () {
    $buyer = createBuyer();

    $response = $this->actingAs($buyer)->from(route('checkout.show'))->post(route('checkout.store'), []);

    $response->assertRedirect(route('checkout.show'));
    $response->assertSessionHasErrors(['shipping_address', 'city_id']);
});

test('checkout store rejects empty cart', function () {
    $buyer = createBuyer();
    $city = City::create(['name' => 'Marrakesh', 'region' => 'Marrakesh-Safi']);
    Cart::create(['user_id' => $buyer->id]);

    $response = $this->actingAs($buyer)->from(route('checkout.show'))->post(route('checkout.store'), [
        'shipping_address' => '123 Test Street',
        'city_id' => $city->id,
        'notes' => 'Please call before delivery',
    ]);

    $response->assertRedirect(route('checkout.show'));
    $response->assertSessionHasErrors(['cart']);
});

test('checkout store creates grouped orders and clears cart', function () {
    Notification::fake();

    $buyer = createBuyer();
    $city = City::create(['name' => 'Tangier', 'region' => 'Tangier-Tetouan-Al Hoceima']);

    $storeA = createStoreWithSeller('Alpha');
    $storeB = createStoreWithSeller('Beta');

    $productA = createProductForStore($storeA, 'Clay Pot', 50.00, 15);
    $productB = createProductForStore($storeB, 'Woven Basket', 30.00, 9);

    $cart = Cart::create(['user_id' => $buyer->id]);
    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $productA->id,
        'quantity' => 2,
    ]);
    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $productB->id,
        'quantity' => 3,
    ]);

    $response = $this->actingAs($buyer)->post(route('checkout.store'), [
        'shipping_address' => '45 Example Ave',
        'city_id' => $city->id,
        'notes' => 'Fragile items',
    ]);

    $response->assertRedirect(route('products.index'));
    $response->assertSessionHas('success', '2 commande(s) passée(s) avec succès !');

    expect(Order::count())->toBe(2);
    expect(OrderItem::count())->toBe(2);
    expect(Earning::count())->toBe(2);
    expect($cart->fresh()->items()->count())->toBe(0);

    expect((int) $productA->fresh()->stock)->toBe(13);
    expect((int) $productB->fresh()->stock)->toBe(6);

    Notification::assertSentTo($storeA->user, NewOrderReceived::class);
    Notification::assertSentTo($storeB->user, NewOrderReceived::class);
});

test('checkout store applies 20% commission and leaves seller pending payout at 180 for a 200 product with 20 shipping', function () {
    Notification::fake();

    $buyer = createBuyer();
    $city = City::create(['name' => 'Fes', 'region' => 'Fes-Meknes']);
    $store = createStoreWithSeller('Omega');
    $product = createProductForStore($store, 'Handcrafted Tray', 200.00, 10);

    $cart = Cart::create(['user_id' => $buyer->id]);
    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'quantity' => 1,
    ]);

    $response = $this->actingAs($buyer)->post(route('checkout.store'), [
        'shipping_address' => '99 Artisan Lane',
        'city_id' => $city->id,
        'notes' => 'Please handle with care',
    ]);

    $response->assertRedirect(route('products.index'));
    $response->assertSessionHas('success', '1 commande(s) passée(s) avec succès !');

    expect(Order::count())->toBe(1);
    expect(Earning::count())->toBe(1);

    $order = Order::first();
    $earning = Earning::first();

    expect((float) $order->platform_fee)->toBe(40.0);
    expect((float) $order->amana_fee)->toBe(20.0);
    expect((float) $order->seller_earnings)->toBe(180.0);

    expect((float) $earning->platform_fee)->toBe(40.0);
    expect((float) $earning->amana_fee)->toBe(20.0);
    expect((float) $earning->net_amount)->toBe(180.0);
});
