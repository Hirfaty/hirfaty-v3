<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function createBuyerUser(): User
{
    return User::factory()->create([
        'role' => 'buyer',
        'email_verified_at' => now(),
    ]);
}

function createStoreForCart(string $name = 'Gamma'): Store
{
    $seller = User::factory()->create([
        'role' => 'seller',
        'email_verified_at' => now(),
    ]);

    return Store::create([
        'user_id' => $seller->id,
        'store_name' => "{$name} Store",
        'slug' => Str::slug("{$name}-store-".Str::random(6)),
        'status' => 'active',
    ]);
}

function createCartProduct(Store $store, string $name = 'Basket', int $stock = 10): Product
{
    return Product::create([
        'store_id' => $store->id,
        'name' => $name,
        'slug' => Str::slug("{$name}-".Str::random(6)),
        'base_price' => 25.00,
        'sale_price' => null,
        'stock' => $stock,
        'status' => 'published',
    ]);
}

test('buyer can view cart page', function () {
    $buyer = createBuyerUser();

    $response = $this->actingAs($buyer)->get(route('cart.show'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('buyer/cart')
        ->has('cart')
    );
});

test('buyer can add product to cart', function () {
    $buyer = createBuyerUser();
    $store = createStoreForCart();
    $product = createCartProduct($store, 'Lamp');

    $response = $this->actingAs($buyer)->post(route('cart.add'), [
        'product_id' => $product->id,
        'quantity' => 2,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Added to cart.');

    $cart = Cart::where('user_id', $buyer->id)->first();
    expect($cart)->not->toBeNull();
    expect($cart->items()->count())->toBe(1);
    expect((int) $cart->items()->first()->quantity)->toBe(2);
});

test('adding same product increments cart item quantity', function () {
    $buyer = createBuyerUser();
    $store = createStoreForCart();
    $product = createCartProduct($store, 'Plate');
    $cart = Cart::create(['user_id' => $buyer->id]);

    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'quantity' => 1,
    ]);

    $this->actingAs($buyer)->post(route('cart.add'), [
        'product_id' => $product->id,
        'quantity' => 3,
    ])->assertRedirect();

    expect((int) $cart->fresh()->items()->first()->quantity)->toBe(4);
});

test('buyer can update cart item quantity', function () {
    $buyer = createBuyerUser();
    $store = createStoreForCart();
    $product = createCartProduct($store, 'Mirror');
    $cart = Cart::create(['user_id' => $buyer->id]);
    $cartItem = CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'quantity' => 1,
    ]);

    $response = $this->actingAs($buyer)->patch(route('cart.update', $cartItem), [
        'quantity' => 5,
    ]);

    $response->assertRedirect();
    expect((int) $cartItem->fresh()->quantity)->toBe(5);
});

test('buyer can remove item from cart', function () {
    $buyer = createBuyerUser();
    $store = createStoreForCart();
    $product = createCartProduct($store, 'Tray');
    $cart = Cart::create(['user_id' => $buyer->id]);
    $cartItem = CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'quantity' => 2,
    ]);

    $response = $this->actingAs($buyer)->delete(route('cart.remove', $cartItem));

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Item removed.');
    expect(CartItem::query()->whereKey($cartItem->id)->exists())->toBeFalse();
});
