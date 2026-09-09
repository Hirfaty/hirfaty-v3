<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Store;
use App\Models\StoreReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Ratings for a delivered order.
 *
 * The rule that makes a rating worth anything is that you had to have received
 * the thing — so most of what follows is about the ways someone might try not
 * to, rather than about the happy path.
 */
beforeEach(function () {
    $this->category = Category::create([
        'slug' => 'poterie',
        'name' => ['fr' => 'Poterie', 'ar' => 'فخار', 'en' => 'Pottery'],
    ]);
    $this->city = City::create(['name' => 'Safi']);

    $this->store = Store::create([
        'user_id' => User::factory()->create(['role' => 'seller'])->id,
        'store_name' => 'Atelier Safi',
        'slug' => 'atelier-safi',
        'category_id' => $this->category->id,
        'city_id' => $this->city->id,
        'status' => 'active',
    ]);

    $this->buyer = User::factory()->create(['role' => 'buyer']);
});

function reviewProduct(Store $store, array $attributes = []): Product
{
    $name = $attributes['name'] ?? 'Vase';

    return Product::create(array_merge([
        'store_id' => $store->id,
        'name' => $name,
        'slug' => Str::slug($name).'-'.Str::random(6),
        'base_price' => 120,
        'stock' => 4,
        'status' => 'published',
        'visibility' => 'both',
    ], $attributes));
}

function orderFor(User $buyer, Store $store, Product $product, string $status = 'delivered'): Order
{
    $order = Order::create([
        'buyer_id' => $buyer->id,
        'store_id' => $store->id,
        'city_id' => $store->city_id,
        'status' => $status,
        'total_amount' => 120,
        'platform_fee' => 24,
        'amana_fee' => 20,
        'seller_earnings' => 76,
        'shipping_address' => '1 rue des potiers',
        'delivered_at' => $status === 'delivered' ? now() : null,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 120,
        'subtotal' => 120,
    ]);

    return $order;
}

test('a buyer can rate a product from a delivered order', function () {
    $product = reviewProduct($this->store);
    $order = orderFor($this->buyer, $this->store, $product);

    $this->actingAs($this->buyer)
        ->post(route('buyer.orders.reviews.store', $order->reference), [
            'products' => [
                $product->id => ['rating' => 5, 'comment' => 'Superbe pièce.'],
            ],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('product_reviews', [
        'user_id' => $this->buyer->id,
        'product_id' => $product->id,
        'order_id' => $order->id,
        'rating' => 5,
    ]);
});

/**
 * The whole point of the feature. `shipped` is the dangerous one — it is the
 * last state before delivery, so an off-by-one in the gate would let a buyer
 * rate something still in transit.
 */
test('a product cannot be rated before it has been delivered', function (string $status) {
    $product = reviewProduct($this->store);
    $order = orderFor($this->buyer, $this->store, $product, $status);

    $this->actingAs($this->buyer)
        ->post(route('buyer.orders.reviews.store', $order->reference), [
            'products' => [$product->id => ['rating' => 5]],
        ])
        ->assertForbidden();

    $this->assertDatabaseCount('product_reviews', 0);
})->with(['pending', 'confirmed', 'shipped', 'cancelled']);

test('a buyer cannot rate someone else\'s order', function () {
    $product = reviewProduct($this->store);
    $order = orderFor($this->buyer, $this->store, $product);

    $stranger = User::factory()->create(['role' => 'buyer']);

    $this->actingAs($stranger)
        ->post(route('buyer.orders.reviews.store', $order->reference), [
            'products' => [$product->id => ['rating' => 5]],
        ])
        ->assertForbidden();

    $this->assertDatabaseCount('product_reviews', 0);
});

/**
 * The payload names the products, so it is the obvious place to smuggle one
 * in. A piece from another order — even one this same buyer legitimately
 * received — must not be writable through *this* order.
 */
test('a product that was not in the order is ignored', function () {
    $bought = reviewProduct($this->store, ['name' => 'Acheté']);
    $notBought = reviewProduct($this->store, ['name' => 'Jamais acheté']);

    $order = orderFor($this->buyer, $this->store, $bought);

    $this->actingAs($this->buyer)
        ->post(route('buyer.orders.reviews.store', $order->reference), [
            'products' => [
                $bought->id => ['rating' => 5],
                $notBought->id => ['rating' => 1],
            ],
        ]);

    $this->assertDatabaseHas('product_reviews', ['product_id' => $bought->id]);
    $this->assertDatabaseMissing('product_reviews', ['product_id' => $notBought->id]);
});

test('a guest cannot rate anything', function () {
    $product = reviewProduct($this->store);
    $order = orderFor($this->buyer, $this->store, $product);

    $this->post(route('buyer.orders.reviews.store', $order->reference), [
        'products' => [$product->id => ['rating' => 5]],
    ])->assertRedirect(route('login'));

    $this->assertDatabaseCount('product_reviews', 0);
});

test('a rating outside 1 to 5 is rejected', function (int $rating) {
    $product = reviewProduct($this->store);
    $order = orderFor($this->buyer, $this->store, $product);

    $this->actingAs($this->buyer)
        ->post(route('buyer.orders.reviews.store', $order->reference), [
            'products' => [$product->id => ['rating' => $rating]],
        ])
        ->assertSessionHasErrors();

    $this->assertDatabaseCount('product_reviews', 0);
})->with([0, 6, -1]);

/**
 * A repeat customer is still one opinion. Without the unique index they could
 * weight a product's average once per purchase, which is the cheapest way to
 * game a rating.
 */
test('rating the same product twice edits the existing review', function () {
    $product = reviewProduct($this->store);
    $first = orderFor($this->buyer, $this->store, $product);
    $second = orderFor($this->buyer, $this->store, $product);

    $this->actingAs($this->buyer)
        ->post(route('buyer.orders.reviews.store', $first->reference), [
            'products' => [$product->id => ['rating' => 2]],
        ]);

    $this->actingAs($this->buyer)
        ->post(route('buyer.orders.reviews.store', $second->reference), [
            'products' => [$product->id => ['rating' => 5]],
        ]);

    expect(ProductReview::where('product_id', $product->id)->count())->toBe(1);
    expect(ProductReview::where('product_id', $product->id)->first()->rating)->toBe(5);
});

// ── The cached average ───────────────────────────────────────────────

test('a product\'s average rating is recomputed on every review', function () {
    $product = reviewProduct($this->store);

    foreach ([5, 4] as $i => $rating) {
        $buyer = User::factory()->create(['role' => 'buyer']);
        $order = orderFor($buyer, $this->store, $product);

        $this->actingAs($buyer)
            ->post(route('buyer.orders.reviews.store', $order->reference), [
                'products' => [$product->id => ['rating' => $rating]],
            ]);
    }

    expect((float) $product->fresh()->average_rating)->toBe(4.5);
});

/**
 * Editing changes a row rather than adding one, so an average folded in
 * incrementally would drift. This is the case that catches that.
 */
test('editing a review recomputes the average rather than compounding it', function () {
    $product = reviewProduct($this->store);
    $order = orderFor($this->buyer, $this->store, $product);

    $this->actingAs($this->buyer)
        ->post(route('buyer.orders.reviews.store', $order->reference), [
            'products' => [$product->id => ['rating' => 1]],
        ]);

    expect((float) $product->fresh()->average_rating)->toBe(1.0);

    $this->actingAs($this->buyer)
        ->post(route('buyer.orders.reviews.store', $order->reference), [
            'products' => [$product->id => ['rating' => 5]],
        ]);

    expect((float) $product->fresh()->average_rating)->toBe(5.0);
});

// ── The shop rating ──────────────────────────────────────────────────

test('a buyer can rate the shop for a delivered order', function () {
    $product = reviewProduct($this->store);
    $order = orderFor($this->buyer, $this->store, $product);

    $this->actingAs($this->buyer)
        ->post(route('buyer.orders.reviews.store', $order->reference), [
            'store' => ['rating' => 4, 'comment' => 'Bien emballé.'],
        ]);

    $this->assertDatabaseHas('store_reviews', [
        'user_id' => $this->buyer->id,
        'store_id' => $this->store->id,
        'order_id' => $order->id,
        'rating' => 4,
    ]);

    expect((float) $this->store->fresh()->average_rating)->toBe(4.0);
});

/**
 * Scoped per order rather than per shop, so a returning customer rates each
 * delivery — three orders from the same artisan is three service experiences.
 */
test('the same shop can be rated once per delivered order', function () {
    $product = reviewProduct($this->store);
    $first = orderFor($this->buyer, $this->store, $product);
    $second = orderFor($this->buyer, $this->store, $product);

    $this->actingAs($this->buyer)
        ->post(route('buyer.orders.reviews.store', $first->reference), [
            'store' => ['rating' => 5],
        ]);

    $this->actingAs($this->buyer)
        ->post(route('buyer.orders.reviews.store', $second->reference), [
            'store' => ['rating' => 3],
        ]);

    expect(StoreReview::where('store_id', $this->store->id)->count())->toBe(2);
    expect((float) $this->store->fresh()->average_rating)->toBe(4.0);
});

test('the shop rating is optional', function () {
    $product = reviewProduct($this->store);
    $order = orderFor($this->buyer, $this->store, $product);

    $this->actingAs($this->buyer)
        ->post(route('buyer.orders.reviews.store', $order->reference), [
            'products' => [$product->id => ['rating' => 5]],
        ]);

    $this->assertDatabaseCount('store_reviews', 0);
    $this->assertDatabaseCount('product_reviews', 1);
});

// ── What the orders page is told ─────────────────────────────────────

test('the orders page marks only delivered orders as rateable', function () {
    $product = reviewProduct($this->store);
    orderFor($this->buyer, $this->store, $product, 'delivered');
    orderFor($this->buyer, $this->store, $product, 'shipped');

    $this->actingAs($this->buyer)
        ->get(route('buyer.account.orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('buyer/orders/index')
            ->has('orders', 2)
        );
});
