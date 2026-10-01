<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusEvent;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Store;
use App\Models\User;
use App\Services\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * An order's history (OrderStatusEvent), the buyer's tracking page built on
 * it, and the purchase-wide "Rate my items" page.
 */
beforeEach(function () {
    Notification::fake();

    $this->buyer = User::factory()->create(['role' => 'buyer', 'email_verified_at' => now()]);
});

function trackingStore(string $name): Store
{
    return Store::create([
        'user_id' => User::factory()->create(['role' => 'seller'])->id,
        'store_name' => $name,
        'slug' => Str::slug($name.'-'.Str::random(6)),
        'status' => 'active',
    ]);
}

function trackingOrder(User $buyer, Store $store, ?string $group, string $status = 'pending', ?Product $product = null): Order
{
    $product ??= Product::create([
        'store_id' => $store->id,
        'name' => 'Pièce '.Str::random(4),
        'slug' => Str::slug('piece-'.Str::random(8)),
        'base_price' => 100,
        'stock' => 5,
        'status' => 'published',
    ]);

    $order = Order::create([
        'buyer_id' => $buyer->id,
        'store_id' => $store->id,
        'group_reference' => $group,
        'status' => $status,
        'total_amount' => 100,
        'platform_fee' => 20,
        'delivery_fee' => 0,
        'seller_earnings' => 80,
        'shipping_address' => '1 rue des artisans',
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 100,
        'subtotal' => 100,
    ]);

    return $order;
}

// ─── History ────────────────────────────────────────────────────────

test('a new order starts its history at the status it was created with', function () {
    $order = trackingOrder($this->buyer, trackingStore('Alpha'), 'HRFG-HIST000001');

    expect($order->statusEvents()->pluck('status')->all())->toBe(['pending']);
});

test('each status change is recorded, with the desk note when there is one', function () {
    $order = trackingOrder($this->buyer, trackingStore('Alpha'), 'HRFG-HIST000002');
    $service = app(OrderStatusService::class);

    $service->transition($order, 'confirmed');
    $service->transition($order, 'shipped', [], 'Parti de Safi');

    $events = $order->statusEvents()->get();

    expect($events->pluck('status')->all())->toBe(['pending', 'confirmed', 'shipped']);
    expect($events->last()->note)->toBe('Parti de Safi');
});

test('re-submitting the same status records nothing, unless it carries a note', function () {
    $order = trackingOrder($this->buyer, trackingStore('Alpha'), 'HRFG-HIST000003', 'shipped');
    $service = app(OrderStatusService::class);

    $service->transition($order, 'shipped');
    expect($order->statusEvents()->count())->toBe(1);

    $service->transition($order, 'shipped', [], 'Centre de tri de Casablanca');
    $last = $order->statusEvents()->get()->last();

    expect($last->status)->toBeNull();
    expect($last->note)->toBe('Centre de tri de Casablanca');
});

test('a cancellation records its reason', function () {
    $order = trackingOrder($this->buyer, trackingStore('Alpha'), 'HRFG-HIST000004');

    app(OrderStatusService::class)->transition($order, 'cancelled', ['cancellation_reason' => 'Rupture de stock']);

    expect($order->statusEvents()->get()->last()->note)->toBe('Rupture de stock');
});

test('the admin can post a status change with a note, and a note on its own', function () {
    $first = trackingOrder($this->buyer, trackingStore('Alpha'), 'HRFG-ADMN000001');
    $second = trackingOrder($this->buyer, trackingStore('Beta'), 'HRFG-ADMN000001');
    $admin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($admin)
        ->patch(route('admin.orders.status', $first), ['status' => 'shipped', 'note' => 'Parti de Safi'])
        ->assertSessionHasNoErrors();

    $this->actingAs($admin)
        ->post(route('admin.orders.notes.store', $first), ['note' => 'Arrivé à Casablanca'])
        ->assertSessionHasNoErrors();

    foreach ([$first, $second] as $order) {
        expect($order->statusEvents()->get()->map(fn ($e) => [$e->status, $e->note])->all())->toBe([
            ['pending', null],
            ['shipped', 'Parti de Safi'],
            [null, 'Arrivé à Casablanca'],
        ]);
    }

    // A note-only update does not move the order.
    expect($first->fresh()->status)->toBe('shipped');
});

test('a note-only update is refused when empty, and only the desk can post one', function () {
    $order = trackingOrder($this->buyer, trackingStore('Alpha'), 'HRFG-ADMN000002');
    $admin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($admin)
        ->post(route('admin.orders.notes.store', $order), ['note' => ''])
        ->assertSessionHasErrors('note');

    $this->actingAs($this->buyer)
        ->post(route('admin.orders.notes.store', $order), ['note' => 'Hack'])
        ->assertRoleDenied();

    expect(OrderStatusEvent::whereNull('status')->count())->toBe(0);
});

// ─── Tracking page ──────────────────────────────────────────────────

test('the tracking page folds a purchase-wide step into one line', function () {
    $first = trackingOrder($this->buyer, trackingStore('Alpha'), 'HRFG-TRCK000001');
    $second = trackingOrder($this->buyer, trackingStore('Beta'), 'HRFG-TRCK000001');

    app(OrderStatusService::class)->transitionMany(collect([$first, $second]), 'confirmed');

    $this->actingAs($this->buyer)
        ->get(route('buyer.purchases.tracking', 'HRFG-TRCK000001'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('buyer/orders/tracking')
            ->where('purchase.reference', 'HRFG-TRCK000001')
            ->has('events', 2)
            // Newest first.
            ->where('events.0.status', 'confirmed')
            ->where('events.0.stores', null)
            ->where('events.1.status', 'pending')
            ->has('reached.pending')
            ->has('reached.confirmed')
        );
});

test('a step only one workshop took says which', function () {
    $first = trackingOrder($this->buyer, trackingStore('Alpha'), 'HRFG-TRCK000002');
    trackingOrder($this->buyer, trackingStore('Beta'), 'HRFG-TRCK000002');

    app(OrderStatusService::class)->transition($first, 'shipped');

    $this->actingAs($this->buyer)
        ->get(route('buyer.purchases.tracking', 'HRFG-TRCK000002'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('events.0.status', 'shipped')
            ->where('events.0.stores', ['Alpha'])
        );
});

test('another buyer cannot see the tracking page', function () {
    trackingOrder($this->buyer, trackingStore('Alpha'), 'HRFG-TRCK000003');
    $stranger = User::factory()->create(['role' => 'buyer', 'email_verified_at' => now()]);

    $this->actingAs($stranger)
        ->get(route('buyer.purchases.tracking', 'HRFG-TRCK000003'))
        ->assertNotFound();
});

// ─── Rate my items ──────────────────────────────────────────────────

test('the card counts delivered pieces still waiting for a rating', function () {
    trackingOrder($this->buyer, trackingStore('Alpha'), 'HRFG-RATE000001', 'delivered');
    trackingOrder($this->buyer, trackingStore('Beta'), 'HRFG-RATE000001', 'shipped');

    $this->actingAs($this->buyer)
        ->get(route('buyer.account.orders.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('purchases.0.review_open_count', 1)
            ->where('purchases.0.can_review', true)
        );
});

test('the rate page lists the purchase and rates pieces across its workshops in one submit', function () {
    $alpha = trackingOrder($this->buyer, trackingStore('Alpha'), 'HRFG-RATE000002', 'delivered');
    $beta = trackingOrder($this->buyer, trackingStore('Beta'), 'HRFG-RATE000002', 'delivered');

    $this->actingAs($this->buyer)
        ->get(route('buyer.purchases.review', 'HRFG-RATE000002'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('buyer/orders/review')
            ->has('purchase.orders', 2)
        );

    $alphaProduct = $alpha->items()->value('product_id');
    $betaProduct = $beta->items()->value('product_id');

    $this->actingAs($this->buyer)
        ->post(route('buyer.purchases.review.store', 'HRFG-RATE000002'), [
            'products' => [
                $alphaProduct => ['rating' => 5, 'comment' => 'Superbe finition'],
                $betaProduct => ['rating' => 3, 'comment' => 'Un peu plus petit que prévu'],
            ],
        ])
        ->assertSessionHasNoErrors();

    // Each rating is filed against the order that delivered it.
    expect(ProductReview::where('product_id', $alphaProduct)->first())
        ->order_id->toBe($alpha->id)
        ->rating->toBe(5)
        ->comment->toBe('Superbe finition');
    expect(ProductReview::where('product_id', $betaProduct)->first())
        ->order_id->toBe($beta->id)
        ->comment->toBe('Un peu plus petit que prévu');
});

test('a piece from a workshop that has not delivered cannot be rated from the page', function () {
    trackingOrder($this->buyer, trackingStore('Alpha'), 'HRFG-RATE000003', 'delivered');
    $shipped = trackingOrder($this->buyer, trackingStore('Beta'), 'HRFG-RATE000003', 'shipped');

    $this->actingAs($this->buyer)
        ->post(route('buyer.purchases.review.store', 'HRFG-RATE000003'), [
            'products' => [$shipped->items()->value('product_id') => ['rating' => 1]],
        ]);

    expect(ProductReview::count())->toBe(0);
});

test('nothing can be rated before anything in the purchase is delivered', function () {
    $order = trackingOrder($this->buyer, trackingStore('Alpha'), 'HRFG-RATE000004', 'shipped');

    $this->actingAs($this->buyer)
        ->post(route('buyer.purchases.review.store', 'HRFG-RATE000004'), [
            'products' => [$order->items()->value('product_id') => ['rating' => 4]],
        ])
        ->assertForbidden();
});

test('another buyer cannot rate or open the rate page', function () {
    $order = trackingOrder($this->buyer, trackingStore('Alpha'), 'HRFG-RATE000005', 'delivered');
    $stranger = User::factory()->create(['role' => 'buyer', 'email_verified_at' => now()]);

    $this->actingAs($stranger)
        ->get(route('buyer.purchases.review', 'HRFG-RATE000005'))
        ->assertNotFound();

    $this->actingAs($stranger)
        ->post(route('buyer.purchases.review.store', 'HRFG-RATE000005'), [
            'products' => [$order->items()->value('product_id') => ['rating' => 1]],
        ])
        ->assertNotFound();

    expect(ProductReview::count())->toBe(0);
});
