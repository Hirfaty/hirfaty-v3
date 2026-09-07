<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * A product carrying a trade minimum, visible to whichever audience the test
 * needs. `min_order_qty_professional` only binds the professional price list —
 * a consumer looking at the same row may buy a single piece.
 */
function createBulkProduct(int $minimum = 20, string $visibility = 'both', int $stock = 100): Product
{
    $seller = User::factory()->create(['role' => 'seller']);

    $store = Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Atlas Ceramics',
        'slug' => Str::slug('atlas-ceramics-'.Str::random(6)),
        'status' => 'active',
    ]);

    return Product::create([
        'store_id' => $store->id,
        'name' => 'Tajine',
        'slug' => Str::slug('tajine-'.Str::random(6)),
        'base_price' => 120.00,
        'professional_price' => 70.00,
        'stock' => $stock,
        'min_order_qty_professional' => $minimum,
        'visibility' => $visibility,
        'status' => 'published',
    ]);
}

test('a verified trade buyer cannot add less than the bulk minimum', function () {
    $buyer = User::factory()->verifiedProfessional()->create();
    $product = createBulkProduct(minimum: 20);

    $this->actingAs($buyer)
        ->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 5])
        ->assertSessionHasErrors('quantity');

    expect(CartItem::count())->toBe(0);
});

test('a verified trade buyer can add at the bulk minimum', function () {
    $buyer = User::factory()->verifiedProfessional()->create();
    $product = createBulkProduct(minimum: 20);

    $this->actingAs($buyer)
        ->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 20])
        ->assertSessionHasNoErrors();

    expect((int) CartItem::first()->quantity)->toBe(20);
});

/**
 * The minimum is on the basket total for that line, not on each click — a
 * trade buyer adding one at a time should still end up over the line once
 * they have added enough of them.
 */
test('repeated adds accumulate towards the bulk minimum', function () {
    $buyer = User::factory()->verifiedProfessional()->create();
    $product = createBulkProduct(minimum: 3);

    foreach ([1, 1] as $quantity) {
        $this->actingAs($buyer)
            ->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => $quantity]);
    }

    expect(CartItem::count())->toBe(0);

    $this->actingAs($buyer)
        ->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 3])
        ->assertSessionHasNoErrors();

    expect((int) CartItem::first()->quantity)->toBe(3);
});

test('a normal buyer is not held to the trade minimum', function () {
    $buyer = User::factory()->create(['role' => 'buyer']);
    $product = createBulkProduct(minimum: 20);

    $this->actingAs($buyer)
        ->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 1])
        ->assertSessionHasNoErrors();

    expect((int) CartItem::first()->quantity)->toBe(1);
});

/**
 * An unverified trade account pays retail, so it buys on retail terms too —
 * the minimum and the wholesale price are two halves of the same bargain and
 * must not come apart.
 */
test('an unverified trade account is not held to the trade minimum', function () {
    $buyer = User::factory()->create(['role' => 'professional_buyer']);
    $product = createBulkProduct(minimum: 20);

    $this->actingAs($buyer)
        ->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 1])
        ->assertSessionHasNoErrors();

    expect((int) CartItem::first()->quantity)->toBe(1);
});

test('a trade buyer cannot edit a line back down below the minimum', function () {
    $buyer = User::factory()->verifiedProfessional()->create();
    $product = createBulkProduct(minimum: 20);

    $cart = Cart::create(['user_id' => $buyer->id]);
    $item = CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'quantity' => 20,
    ]);

    $this->actingAs($buyer)
        ->patch(route('cart.update', $item), ['quantity' => 1])
        ->assertSessionHasErrors('quantity');

    expect((int) $item->fresh()->quantity)->toBe(20);
});

test('a guest cannot add to the cart', function () {
    $product = createBulkProduct();

    $this->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 1])
        ->assertRedirect(route('login'));

    expect(CartItem::count())->toBe(0);
});

test('a consumer cannot open a trade-only product by its slug', function () {
    $buyer = User::factory()->create(['role' => 'buyer']);
    $product = createBulkProduct(visibility: 'professional');

    $this->actingAs($buyer)
        ->get(route('products.show', $product->slug))
        ->assertNotFound();
});

test('a verified trade buyer can open a trade-only product', function () {
    $buyer = User::factory()->verifiedProfessional()->create();
    $product = createBulkProduct(visibility: 'professional');

    $this->actingAs($buyer)
        ->get(route('products.show', $product->slug))
        ->assertOk();
});
