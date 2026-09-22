<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Store;
use App\Models\User;
use App\Services\BuyerAccountService;
use App\Services\ReviewService;
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
        'delivery_fee' => 20,
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

// ── One shot ─────────────────────────────────────────────────────────

/**
 * The rule the form is built around: once sent, a rating is final. The UI
 * closes the stars, but that is a display decision — this is what makes it
 * true.
 */
test('a second rating of the same product is ignored', function () {
    $product = reviewProduct($this->store);
    $order = orderFor($this->buyer, $this->store, $product);

    $this->actingAs($this->buyer)
        ->post(route('buyer.orders.reviews.store', $order->reference), [
            'products' => [$product->id => ['rating' => 2, 'comment' => 'Bof.']],
        ]);

    $this->actingAs($this->buyer)
        ->post(route('buyer.orders.reviews.store', $order->reference), [
            'products' => [$product->id => ['rating' => 5, 'comment' => 'Finalement superbe.']],
        ]);

    $review = ProductReview::where('product_id', $product->id)->sole();

    expect($review->rating)->toBe(2);
    expect($review->comment)->toBe('Bof.');
});

/**
 * A repeat customer is still one opinion. Buying the same piece again must not
 * reopen the form for it — that would be the cheapest way to weight a
 * product's average once per purchase.
 */
test('a later purchase of an already-rated product does not reopen it', function () {
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
    expect(ProductReview::where('product_id', $product->id)->first()->rating)->toBe(2);
});

/**
 * Partly rating a delivery leaves the rest open — the pieces close one at a
 * time, not the order as a whole.
 */
test('rating one piece leaves the others open', function () {
    $rated = reviewProduct($this->store, ['name' => 'Noté']);
    $untouched = reviewProduct($this->store, ['name' => 'Pas encore']);

    $order = orderFor($this->buyer, $this->store, $rated);
    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $untouched->id,
        'quantity' => 1,
        'unit_price' => 120,
        'subtotal' => 120,
    ]);

    $this->actingAs($this->buyer)
        ->post(route('buyer.orders.reviews.store', $order->reference), [
            'products' => [$rated->id => ['rating' => 4]],
        ]);

    expect(app(ReviewService::class)->openProductIds($this->buyer, $order->fresh()))
        ->toBe([$untouched->id]);

    $this->actingAs($this->buyer)
        ->post(route('buyer.orders.reviews.store', $order->reference), [
            'products' => [$untouched->id => ['rating' => 2]],
        ]);

    $this->assertDatabaseHas('product_reviews', [
        'product_id' => $untouched->id,
        'rating' => 2,
    ]);
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

// ── The shop rating, now gone ────────────────────────────────────────

/**
 * Buyers rate the pieces, not the artisan. The table still exists (see
 * StoreReview), so the thing worth pinning down is that a payload shaped like
 * the old form cannot write to it.
 */
test('a shop rating in the payload is ignored', function () {
    $product = reviewProduct($this->store);
    $order = orderFor($this->buyer, $this->store, $product);

    $this->actingAs($this->buyer)
        ->post(route('buyer.orders.reviews.store', $order->reference), [
            'products' => [$product->id => ['rating' => 5]],
            'store' => ['rating' => 1, 'comment' => 'Mal emballé.'],
        ])
        ->assertRedirect();

    $this->assertDatabaseCount('store_reviews', 0);
    $this->assertDatabaseCount('product_reviews', 1);
    expect((float) $this->store->fresh()->average_rating)->toBe(0.0);
});

// ── The arrival prompt ───────────────────────────────────────────────

/**
 * What decides whether the dialog opens. Counting *orders* rather than pieces
 * is deliberate — the prompt sends the buyer to the order list, so the number
 * it quotes has to be a number of orders.
 */
test('a delivered order with an unrated piece is counted as pending', function () {
    $product = reviewProduct($this->store);
    $order = orderFor($this->buyer, $this->store, $product);
    orderFor($this->buyer, $this->store, reviewProduct($this->store, ['name' => 'En route']), 'shipped');

    $reviews = app(ReviewService::class);

    expect($reviews->pendingOrderCount($this->buyer))->toBe(1);

    $this->actingAs($this->buyer)
        ->post(route('buyer.orders.reviews.store', $order->reference), [
            'products' => [$product->id => ['rating' => 5]],
        ]);

    expect($reviews->pendingOrderCount($this->buyer))->toBe(0);
});

test('another buyer\'s deliveries are never counted as pending', function () {
    $product = reviewProduct($this->store);
    orderFor($this->buyer, $this->store, $product);

    $stranger = User::factory()->create(['role' => 'buyer']);

    expect(app(ReviewService::class)->pendingOrderCount($stranger))->toBe(0);
});

test('the prompt count is shared with the page', function () {
    $product = reviewProduct($this->store);
    orderFor($this->buyer, $this->store, $product);

    $this->actingAs($this->buyer)
        ->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('pendingReviews', 1));
});

// ── What the orders page is told ─────────────────────────────────────

test('the orders page marks only delivered orders as rateable', function () {
    $product = reviewProduct($this->store);
    orderFor($this->buyer, $this->store, $product, 'delivered');
    orderFor($this->buyer, $this->store, $product, 'shipped');

    // Two separate checkouts, so two cards — grouping only folds together
    // orders that came from the same one.
    $this->actingAs($this->buyer)
        ->get(route('buyer.account.orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('buyer/orders/index')
            ->has('purchases', 2)
        );

    $rateable = collect(app(BuyerAccountService::class)->purchases($this->buyer))
        ->where('can_review', true);

    expect($rateable)->toHaveCount(1);
});
