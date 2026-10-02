<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Blocking a seller (Admin\UserController::ban) takes their shop off the
 * public site: Product::storefront() and Store::selling() both refuse a shop
 * whose owner is banned.
 */

beforeEach(function () {
    $this->seller = User::factory()->create(['role' => 'seller', 'email_verified_at' => now()]);

    $this->store = Store::create([
        'user_id' => $this->seller->id,
        'store_name' => 'Atelier Bloqué',
        'slug' => 'atelier-bloque',
        'status' => 'active',
    ]);

    $this->product = Product::create([
        'store_id' => $this->store->id,
        'name' => 'Tapis berbère',
        'slug' => 'tapis-berbere',
        'base_price' => 300,
        'stock' => 5,
        'status' => 'published',
    ]);

    $this->buyer = User::factory()->create(['role' => 'buyer', 'email_verified_at' => now()]);
});

function blockSeller(User $seller): void
{
    $seller->forceFill(['is_banned' => true])->save();
}

test('a blocked seller\'s products leave the catalogue', function () {
    expect(Product::storefront()->whereKey($this->product->id)->exists())->toBeTrue();

    blockSeller($this->seller);

    expect(Product::storefront()->whereKey($this->product->id)->exists())->toBeFalse();

    $this->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products', 0)
            ->where('pagination.total', 0));
});

test('a blocked seller\'s product page and shop page are gone', function () {
    blockSeller($this->seller);

    $this->get(route('products.show', $this->product->slug))->assertNotFound();
    $this->get(route('stores.show', $this->store->slug))->assertNotFound();
});

test('a blocked seller\'s product cannot be added to the cart', function () {
    blockSeller($this->seller);

    $this->actingAs($this->buyer)
        ->post(route('cart.add'), ['product_id' => $this->product->id, 'quantity' => 1])
        ->assertSessionHasErrors('product_id');

    expect(CartItem::count())->toBe(0);
});

test('checkout refuses a line whose seller was blocked after it was added', function () {
    $cart = Cart::create(['user_id' => $this->buyer->id]);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $this->product->id, 'quantity' => 1]);

    blockSeller($this->seller);

    expect(fn () => app(CheckoutService::class)->process($cart->fresh('items.product'), []))
        ->toThrow(ValidationException::class, 'Tapis berbère');

    expect($this->product->fresh()->stock)->toBe(5);
});

test('unblocking the seller puts everything back', function () {
    blockSeller($this->seller);
    $this->seller->forceFill(['is_banned' => false])->save();

    $this->get(route('products.show', $this->product->slug))->assertOk();
    $this->get(route('stores.show', $this->store->slug))->assertOk();
});
