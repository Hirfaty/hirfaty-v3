<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Notifications\OrderCancelled;
use App\Notifications\OrderConfirmed;
use App\Notifications\OrderDelivered;
use App\Notifications\OrderShipped;
use App\Services\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * One purchase, one email — even when the admin desk moves every workshop's
 * order in it at once.
 *
 * `CheckoutService` splits a basket into one Order per store; the admin desk
 * then manages that whole group as a single job (see
 * Admin\OrderController's docblock: it collects from every workshop and
 * ships the purchase as one parcel). Looping the group through
 * `OrderStatusService::transition()` one order at a time used to email the
 * buyer once per store for what was, from the buyer's side, a single status
 * change — this file is about making sure that stays fixed.
 */
beforeEach(function () {
    $this->buyer = User::factory()->create(['role' => 'buyer', 'email_verified_at' => now()]);
});

function statusTestStore(string $name): Store
{
    return Store::create([
        'user_id' => User::factory()->create(['role' => 'seller'])->id,
        'store_name' => $name,
        'slug' => Str::slug($name.'-'.Str::random(6)),
        'status' => 'active',
    ]);
}

function statusTestOrder(User $buyer, Store $store, ?string $group, string $status = 'pending'): Order
{
    $product = Product::create([
        'store_id' => $store->id,
        'name' => 'Pièce test',
        'slug' => Str::slug('piece-test-'.Str::random(6)),
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

test('confirming a purchase spanning two workshops sends the buyer exactly one email', function () {
    Notification::fake();

    $alpha = statusTestStore('Atelier Alpha');
    $beta = statusTestStore('Atelier Beta');

    $first = statusTestOrder($this->buyer, $alpha, 'HRFG-CONFIRM01');
    $second = statusTestOrder($this->buyer, $beta, 'HRFG-CONFIRM01');

    $admin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($admin)
        ->patch(route('admin.orders.status', $first), ['status' => 'confirmed'])
        ->assertRedirect();

    expect($first->fresh()->status)->toBe('confirmed');
    expect($second->fresh()->status)->toBe('confirmed');

    Notification::assertSentToTimes($this->buyer, OrderConfirmed::class, 1);

    Notification::assertSentTo($this->buyer, OrderConfirmed::class, function (OrderConfirmed $notification) use ($first, $second) {
        return $notification->orders->pluck('id')->sort()->values()->all()
            === collect([$first->id, $second->id])->sort()->values()->all();
    });
});

test('shipping a purchase spanning two workshops sends the buyer exactly one email', function () {
    Notification::fake();

    $alpha = statusTestStore('Atelier Alpha');
    $beta = statusTestStore('Atelier Beta');

    $first = statusTestOrder($this->buyer, $alpha, 'HRFG-SHIP0001', 'confirmed');
    statusTestOrder($this->buyer, $beta, 'HRFG-SHIP0001', 'confirmed');

    $admin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($admin)
        ->patch(route('admin.orders.status', $first), [
            'status' => 'shipped',
            'tracking_number' => 'AMN-TEST1234',
        ])
        ->assertRedirect();

    Notification::assertSentToTimes($this->buyer, OrderShipped::class, 1);
});

test('delivering a purchase spanning two workshops sends the buyer exactly one email', function () {
    Notification::fake();

    $alpha = statusTestStore('Atelier Alpha');
    $beta = statusTestStore('Atelier Beta');

    $first = statusTestOrder($this->buyer, $alpha, 'HRFG-DELIVER1', 'shipped');
    statusTestOrder($this->buyer, $beta, 'HRFG-DELIVER1', 'shipped');

    $admin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($admin)
        ->patch(route('admin.orders.status', $first), ['status' => 'delivered'])
        ->assertRedirect();

    Notification::assertSentToTimes($this->buyer, OrderDelivered::class, 1);
});

test('cancelling a purchase spanning two workshops sends the buyer exactly one email', function () {
    Notification::fake();

    $alpha = statusTestStore('Atelier Alpha');
    $beta = statusTestStore('Atelier Beta');

    $first = statusTestOrder($this->buyer, $alpha, 'HRFG-CANCEL01');
    statusTestOrder($this->buyer, $beta, 'HRFG-CANCEL01');

    $admin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($admin)
        ->patch(route('admin.orders.status', $first), ['status' => 'cancelled'])
        ->assertRedirect();

    Notification::assertSentToTimes($this->buyer, OrderCancelled::class, 1);
});

test('handling one workshop separately only emails about that workshop', function () {
    Notification::fake();

    $alpha = statusTestStore('Atelier Alpha');
    $beta = statusTestStore('Atelier Beta');

    $first = statusTestOrder($this->buyer, $alpha, 'HRFG-SINGLE01');
    $second = statusTestOrder($this->buyer, $beta, 'HRFG-SINGLE01');

    $admin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($admin)
        ->patch(route('admin.orders.status', $first), [
            'status' => 'confirmed',
            'scope' => 'single',
        ])
        ->assertRedirect();

    expect($first->fresh()->status)->toBe('confirmed');
    // The other workshop's order is untouched by a "handle separately" move.
    expect($second->fresh()->status)->toBe('pending');

    Notification::assertSentToTimes($this->buyer, OrderConfirmed::class, 1);
    Notification::assertSentTo($this->buyer, OrderConfirmed::class, fn (OrderConfirmed $notification) => $notification->orders->pluck('id')->all() === [$first->id]
    );
});

test('re-submitting the same status for a group sends no email', function () {
    Notification::fake();

    $alpha = statusTestStore('Atelier Alpha');
    $beta = statusTestStore('Atelier Beta');

    $first = statusTestOrder($this->buyer, $alpha, 'HRFG-NOCHANGE', 'confirmed');
    statusTestOrder($this->buyer, $beta, 'HRFG-NOCHANGE', 'confirmed');

    $admin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($admin)
        ->patch(route('admin.orders.status', $first), ['status' => 'confirmed'])
        ->assertRedirect();

    Notification::assertNotSentTo($this->buyer, OrderConfirmed::class);
});

test('a single-order transition still emails the buyer once, unaffected by grouping', function () {
    Notification::fake();

    $store = statusTestStore('Atelier Solo');
    $order = statusTestOrder($this->buyer, $store, null);

    app(OrderStatusService::class)->transition($order, 'confirmed');

    expect($order->fresh()->status)->toBe('confirmed');
    Notification::assertSentToTimes($this->buyer, OrderConfirmed::class, 1);
});
