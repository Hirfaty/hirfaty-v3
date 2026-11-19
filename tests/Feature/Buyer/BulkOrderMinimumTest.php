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

test('a buyer in pro mode cannot add less than the bulk minimum', function () {
    $buyer = proBuyer();
    $product = createBulkProduct(minimum: 20);

    $this->actingAs($buyer)
        ->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 5])
        ->assertSessionHasErrors('quantity');

    expect(CartItem::count())->toBe(0);
});

test('a buyer in pro mode can add at the bulk minimum', function () {
    $buyer = proBuyer();
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
    $buyer = proBuyer();
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

test('a buyer on personal prices is not held to the trade minimum', function () {
    $buyer = User::factory()->create(['role' => 'buyer']);
    $product = createBulkProduct(minimum: 20);

    $this->actingAs($buyer)
        ->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 1])
        ->assertSessionHasNoErrors();

    expect((int) CartItem::first()->quantity)->toBe(1);
});

test('a pro-mode line cannot be edited back down below the minimum', function () {
    $buyer = proBuyer();
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

test('a visitor in pro mode is held to the bulk minimum like a buyer', function () {
    // Visitors shop without signing in now — on the same rules.
    shopInProMode();
    $product = createBulkProduct(minimum: 20);

    $this->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 5])
        ->assertSessionHasErrors('quantity');

    expect(CartItem::count())->toBe(0);

    $this->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 20])
        ->assertSessionHasNoErrors();

    expect((int) CartItem::first()->quantity)->toBe(20);
});

test('a trade-only product does not open on personal prices', function () {
    $buyer = User::factory()->create(['role' => 'buyer']);
    $product = createBulkProduct(visibility: 'professional');

    $this->actingAs($buyer)
        ->get(route('products.show', $product->slug))
        ->assertNotFound();
});

test('a trade-only product opens in pro mode', function () {
    $buyer = proBuyer();
    $product = createBulkProduct(visibility: 'professional');

    $this->actingAs($buyer)
        ->get(route('products.show', $product->slug))
        ->assertOk();
});

/**
 * The basket was filled at personal prices, then the buyer switched to pro:
 * the line now sits under the pro floor. Checkout refuses it (naming the
 * piece) rather than billing wholesale for a single piece.
 */
test('switching to pro with a line under the minimum blocks checkout', function () {
    $buyer = User::factory()->create(['role' => 'buyer', 'email_verified_at' => now()]);
    $product = createBulkProduct(minimum: 20);

    $this->actingAs($buyer)
        ->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 1])
        ->assertSessionHasNoErrors();

    shopInProMode();

    // The basket flags it, so the buyer can raise it or switch back.
    $this->actingAs($buyer)
        ->get(route('cart.show'))
        ->assertInertia(fn ($page) => $page
            ->where('items.0.min_quantity', 20)
            ->where('items.0.quantity', 1)
        );

    // And the checkout page sends them back there instead of a form that
    // will only refuse.
    $this->actingAs($buyer)
        ->get(route('checkout.show'))
        ->assertRedirect(route('cart.show'))
        ->assertSessionHas('error');

    // Raising it to the floor clears the way.
    $item = CartItem::first();

    $this->actingAs($buyer)
        ->patch(route('cart.update', $item), ['quantity' => 20])
        ->assertSessionHasNoErrors();

    $this->actingAs($buyer)
        ->get(route('checkout.show'))
        ->assertOk();
});
