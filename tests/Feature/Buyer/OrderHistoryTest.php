<?php

use App\Models\City;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\BuyerAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * What the buyer sees of a basket that was split.
 *
 * Checkout writes one Order per store, because that is who gets paid and who
 * gets notified. The buyer made one purchase, and the history has to say so —
 * so these are about the seam between the two: one card, the shops still
 * distinguishable inside it, and nothing double-counted on the way.
 */
beforeEach(function () {
    $this->buyer = User::factory()->create(['role' => 'buyer', 'email_verified_at' => now()]);
    $this->city = City::create(['name' => 'Fès']);
});

function historyStore(string $name): Store
{
    return Store::create([
        'user_id' => User::factory()->create(['role' => 'seller'])->id,
        'store_name' => $name,
        'slug' => Str::slug($name.'-'.Str::random(6)),
        'status' => 'active',
    ]);
}

function historyProduct(Store $store, string $name, float $price): Product
{
    return Product::create([
        'store_id' => $store->id,
        'name' => $name,
        'slug' => Str::slug($name.'-'.Str::random(6)),
        'base_price' => $price,
        'stock' => 5,
        'status' => 'published',
    ]);
}

/**
 * One store's share of a checkout. `$group` is what ties it to the others —
 * passing null makes it a lone order, as every order placed before grouping
 * existed is.
 */
function historyOrder(
    User $buyer,
    Store $store,
    Product $product,
    ?string $group,
    string $status = 'pending',
    float $deliveryFee = 0,
): Order {
    $order = Order::create([
        'buyer_id' => $buyer->id,
        'store_id' => $store->id,
        'city_id' => $store->city_id,
        'group_reference' => $group,
        'status' => $status,
        'total_amount' => $product->base_price,
        'platform_fee' => round($product->base_price * 0.2, 2),
        'delivery_fee' => $deliveryFee,
        'seller_earnings' => $product->base_price * 0.8 + $deliveryFee,
        'shipping_address' => '12 derb El Horra',
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => $product->base_price,
        'subtotal' => $product->base_price,
    ]);

    return $order;
}

test('a basket split across two workshops reads as one purchase', function () {
    $alpha = historyStore('Atelier Alpha');
    $beta = historyStore('Atelier Beta');

    historyOrder($this->buyer, $alpha, historyProduct($alpha, 'Vase', 100), 'HRFG-ABCDEFGHIJ', deliveryFee: 30);
    historyOrder($this->buyer, $beta, historyProduct($beta, 'Panier', 60), 'HRFG-ABCDEFGHIJ');

    $purchases = app(BuyerAccountService::class)->purchases($this->buyer);

    expect($purchases)->toHaveCount(1);

    $purchase = $purchases[0];

    // The reference the buyer quotes is the one the whole purchase shares.
    expect($purchase['reference'])->toBe('HRFG-ABCDEFGHIJ');
    expect($purchase['orders'])->toHaveCount(2);

    // Both workshops' pieces are there, each still attributed to its shop.
    expect(collect($purchase['orders'])->pluck('store.store_name')->all())
        ->toEqualCanonicalizing(['Atelier Alpha', 'Atelier Beta']);

    expect(collect($purchase['orders'])->flatMap(fn ($order) => collect($order['items'])->pluck('product.name'))->all())
        ->toEqualCanonicalizing(['Vase', 'Panier']);
});

test('the purchase total carries the delivery charged once for the basket', function () {
    $alpha = historyStore('Atelier Alpha');
    $beta = historyStore('Atelier Beta');

    // Checkout parks the whole delivery fee on the first order it writes and
    // gives the rest 0 — so summing is what puts the basket back together.
    historyOrder($this->buyer, $alpha, historyProduct($alpha, 'Vase', 100), 'HRFG-DELIVERY1', deliveryFee: 30);
    historyOrder($this->buyer, $beta, historyProduct($beta, 'Panier', 60), 'HRFG-DELIVERY1');

    $purchase = app(BuyerAccountService::class)->purchases($this->buyer)[0];

    expect($purchase['items_total'])->toBe(160.0);
    expect($purchase['delivery_fee'])->toBe(30.0);
    expect($purchase['total_amount'])->toBe(190.0);
});

test('the least advanced workshop sets the status of the purchase', function () {
    $alpha = historyStore('Atelier Alpha');
    $beta = historyStore('Atelier Beta');

    // One parcel has arrived, the other has not left — the purchase is not
    // delivered, whatever the luckier half says.
    historyOrder($this->buyer, $alpha, historyProduct($alpha, 'Vase', 100), 'HRFG-STATUS001', 'delivered');
    historyOrder($this->buyer, $beta, historyProduct($beta, 'Panier', 60), 'HRFG-STATUS001', 'confirmed');

    $purchase = app(BuyerAccountService::class)->purchases($this->buyer)[0];

    expect($purchase['status'])->toBe('confirmed');

    // ...but the delivered half can still be rated, and says so itself.
    expect($purchase['can_review'])->toBeTrue();
    expect(collect($purchase['orders'])->where('can_review', true))->toHaveCount(1);
});

test('a cancelled workshop does not drag the rest of the purchase backwards', function () {
    $alpha = historyStore('Atelier Alpha');
    $beta = historyStore('Atelier Beta');

    historyOrder($this->buyer, $alpha, historyProduct($alpha, 'Vase', 100), 'HRFG-CANCEL001', 'cancelled');
    historyOrder($this->buyer, $beta, historyProduct($beta, 'Panier', 60), 'HRFG-CANCEL001', 'shipped');

    $purchase = app(BuyerAccountService::class)->purchases($this->buyer)[0];

    expect($purchase['status'])->toBe('shipped');
});

test('a purchase reads as cancelled only when every workshop in it was', function () {
    $alpha = historyStore('Atelier Alpha');
    $beta = historyStore('Atelier Beta');

    historyOrder($this->buyer, $alpha, historyProduct($alpha, 'Vase', 100), 'HRFG-CANCEL002', 'cancelled');
    historyOrder($this->buyer, $beta, historyProduct($beta, 'Panier', 60), 'HRFG-CANCEL002', 'cancelled');

    $purchase = app(BuyerAccountService::class)->purchases($this->buyer)[0];

    expect($purchase['status'])->toBe('cancelled');
});

test('orders placed before grouping existed still stand on their own', function () {
    $alpha = historyStore('Atelier Alpha');
    $beta = historyStore('Atelier Beta');

    // A null group reference must not collapse an entire history into one
    // card — each of these is its own purchase, quoting its own reference.
    $first = historyOrder($this->buyer, $alpha, historyProduct($alpha, 'Vase', 100), null);
    $second = historyOrder($this->buyer, $beta, historyProduct($beta, 'Panier', 60), null);

    $purchases = app(BuyerAccountService::class)->purchases($this->buyer);

    expect($purchases)->toHaveCount(2);
    expect(collect($purchases)->pluck('reference')->all())
        ->toEqualCanonicalizing([$first->reference, $second->reference]);
});

test('the orders page is handed purchases, not raw orders', function () {
    $alpha = historyStore('Atelier Alpha');
    $beta = historyStore('Atelier Beta');

    historyOrder($this->buyer, $alpha, historyProduct($alpha, 'Vase', 100), 'HRFG-PAGE00001');
    historyOrder($this->buyer, $beta, historyProduct($beta, 'Panier', 60), 'HRFG-PAGE00001');

    $this->actingAs($this->buyer)
        ->get(route('buyer.account.orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('buyer/orders/index')
            ->has('purchases', 1)
            ->has('purchases.0.orders', 2)
        );
});

test('the card counts pieces across every workshop and names who bought them', function () {
    $this->buyer->update(['first_name' => 'Amina', 'last_name' => 'Tazi']);

    $alpha = historyStore('Atelier Alpha');
    $beta = historyStore('Atelier Beta');

    historyOrder($this->buyer, $alpha, historyProduct($alpha, 'Vase', 100), 'HRFG-CARD000001');
    historyOrder($this->buyer, $beta, historyProduct($beta, 'Panier', 60), 'HRFG-CARD000001');

    $purchase = app(BuyerAccountService::class)->purchases($this->buyer)[0];

    expect($purchase['items_count'])->toBe(2);
    expect($purchase['buyer_name'])->toBe('Amina Tazi');
});

test('the delivery date waits for every live workshop to deliver', function () {
    $alpha = historyStore('Atelier Alpha');
    $beta = historyStore('Atelier Beta');

    $first = historyOrder($this->buyer, $alpha, historyProduct($alpha, 'Vase', 100), 'HRFG-DATE000001', 'delivered');
    $second = historyOrder($this->buyer, $beta, historyProduct($beta, 'Panier', 60), 'HRFG-DATE000001', 'shipped');
    $first->update(['delivered_at' => now()->subDay()]);

    $service = app(BuyerAccountService::class);

    // One parcel landed, the other is still on its way.
    expect($service->purchases($this->buyer)[0]['delivered_at'])->toBeNull();

    $second->update(['status' => 'delivered', 'delivered_at' => now()]);

    // The later of the two, once both are in.
    expect($service->purchases($this->buyer)[0]['delivered_at']->toDateString())
        ->toBe(now()->toDateString());
});

test('the confirmation page is only for the buyer who placed the purchase', function () {
    $alpha = historyStore('Atelier Alpha');
    historyOrder($this->buyer, $alpha, historyProduct($alpha, 'Vase', 100), 'HRFG-CONF000001');

    $this->actingAs($this->buyer)
        ->get(route('checkout.confirmation', 'HRFG-CONF000001'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('buyer/checkout-confirmation')
            ->where('purchase.reference', 'HRFG-CONF000001')
        );

    $stranger = User::factory()->create(['role' => 'buyer', 'email_verified_at' => now()]);

    $this->actingAs($stranger)
        ->get(route('checkout.confirmation', 'HRFG-CONF000001'))
        ->assertNotFound();
});

test('the invoice downloads as one PDF for the whole purchase', function () {
    $alpha = historyStore('Atelier Alpha');
    $beta = historyStore('Atelier Beta');

    historyOrder($this->buyer, $alpha, historyProduct($alpha, 'Vase', 100), 'HRFG-INVC000001', deliveryFee: 30);
    historyOrder($this->buyer, $beta, historyProduct($beta, 'Panier', 60), 'HRFG-INVC000001');

    $response = $this->actingAs($this->buyer)
        ->get(route('buyer.purchases.invoice', 'HRFG-INVC000001'))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect($response->headers->get('content-disposition'))
        ->toContain('attachment')
        ->toContain('HRFG-INVC000001');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

test('an order placed before grouping existed still has an invoice', function () {
    $alpha = historyStore('Atelier Alpha');
    $order = historyOrder($this->buyer, $alpha, historyProduct($alpha, 'Vase', 100), null);

    $this->actingAs($this->buyer)
        ->get(route('buyer.purchases.invoice', $order->reference))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('another buyer cannot download the invoice', function () {
    $alpha = historyStore('Atelier Alpha');
    historyOrder($this->buyer, $alpha, historyProduct($alpha, 'Vase', 100), 'HRFG-PRIV000001');

    $stranger = User::factory()->create(['role' => 'buyer', 'email_verified_at' => now()]);

    $this->actingAs($stranger)
        ->get(route('buyer.purchases.invoice', 'HRFG-PRIV000001'))
        ->assertNotFound();
});
