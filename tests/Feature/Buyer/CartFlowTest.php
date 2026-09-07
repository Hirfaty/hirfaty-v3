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

    // `items`, not `cart`: HandleInertiaRequests shares a global `cart` prop on
    // every response, so asserting on that name would pass even if this page
    // sent no basket of its own.
    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('buyer/cart')
        ->has('items', 0)
    );
});

test('the cart page prices each line for the viewer, with its image', function () {
    $buyer = createBuyerUser();
    $store = createStoreForCart();
    $product = createCartProduct($store, 'Bowl');
    $product->update(['sale_price' => 15.00]);

    $product->media()->create([
        'type' => 'image',
        'path' => 'products/bowl.jpg',
        'url' => 'https://example.test/bowl.jpg',
        'disk' => 'public',
        'order' => 0,
    ]);

    $cart = Cart::create(['user_id' => $buyer->id]);
    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'quantity' => 2,
    ]);

    $this->actingAs($buyer)
        ->get(route('cart.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('items', 1)
            // The sale price, not base_price — and the line total with it.
            ->where('items.0.unit_price', 15)
            ->where('items.0.line_total', 30)
            // base_price, so the page can strike it through.
            ->where('items.0.original_unit_price', 25)
            ->where('items.0.image', 'https://example.test/bowl.jpg')
            ->where('items.0.available_stock', 10)
            ->etc()
        );
});

/**
 * The reason the page stopped computing prices itself: it read
 * `sale_price ?? base_price` off the product, so a verified trade account was
 * quoted retail in the basket and then billed wholesale at the till.
 */
test('a verified trade buyer is quoted their wholesale price in the cart', function () {
    $buyer = User::factory()->verifiedProfessional()->create();
    $store = createStoreForCart();
    $product = createCartProduct($store, 'Jar');
    $product->update([
        'professional_price' => 12.00,
        'min_order_qty_professional' => 5,
    ]);

    $cart = Cart::create(['user_id' => $buyer->id]);
    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'quantity' => 5,
    ]);

    $this->actingAs($buyer)
        ->get(route('cart.show'))
        ->assertInertia(fn (Assert $page) => $page
            // 12 (wholesale), not the 25 retail list price.
            ->where('items.0.unit_price', 12)
            ->where('items.0.line_total', 60)
            // The stepper floors here so it can't dial below what
            // CartService::assertQuantity would then refuse.
            ->where('items.0.min_quantity', 5)
            ->etc()
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
