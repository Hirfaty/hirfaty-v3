<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\City;
use App\Models\Earning;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Notifications\NewOrderReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function createBuyer(): User
{
    return User::factory()->create([
        'role' => 'buyer',
        'email_verified_at' => now(),
    ]);
}

function createStoreWithSeller(string $name): Store
{
    $seller = User::factory()->create([
        'role' => 'seller',
        'email_verified_at' => now(),
        'first_name' => $name,
    ]);

    return Store::create([
        'user_id' => $seller->id,
        'store_name' => "{$name} Store",
        'slug' => Str::slug("{$name}-store-".Str::random(6)),
        'status' => 'active',
    ]);
}

function createProductForStore(Store $store, string $name, float $basePrice, int $stock = 20): Product
{
    return Product::create([
        'store_id' => $store->id,
        'name' => $name,
        'slug' => Str::slug("{$name}-".Str::random(6)),
        'base_price' => $basePrice,
        'sale_price' => null,
        'stock' => $stock,
        'status' => 'published',
    ]);
}

test('buyer is redirected to products when checkout cart is empty', function () {
    $buyer = createBuyer();
    Cart::create(['user_id' => $buyer->id]);

    $response = $this->actingAs($buyer)->get(route('checkout.show'));

    $response->assertRedirect(route('products.index'));
});

test('buyer can view checkout with cart and cities payload', function () {
    $buyer = createBuyer();
    $cityA = City::create(['name' => 'Casablanca', 'region' => 'Casablanca-Settat']);
    $cityB = City::create(['name' => 'Rabat', 'region' => 'Rabat-Sale-Kenitra']);

    $store = createStoreWithSeller('Alpha');
    $product = createProductForStore($store, 'Handmade Vase', 120.50);

    $cart = Cart::create(['user_id' => $buyer->id]);
    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'quantity' => 2,
    ]);

    $response = $this->actingAs($buyer)->get(route('checkout.show'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('buyer/checkout')
        ->has('items', 1)
        ->where('cities.0.id', $cityA->id)
        ->where('cities.1.id', $cityB->id)
    );
});

test('checkout store validates required fields', function () {
    $buyer = createBuyer();

    $response = $this->actingAs($buyer)->from(route('checkout.show'))->post(route('checkout.store'), []);

    $response->assertRedirect(route('checkout.show'));
    $response->assertSessionHasErrors(['shipping_address', 'city_id', 'shipping_phone']);
});

test('checkout store rejects empty cart', function () {
    $buyer = createBuyer();
    $city = City::create(['name' => 'Marrakesh', 'region' => 'Marrakesh-Safi']);
    Cart::create(['user_id' => $buyer->id]);

    $response = $this->actingAs($buyer)->from(route('checkout.show'))->post(route('checkout.store'), [
        'shipping_address' => '123 Test Street',
        'city_id' => $city->id,
        'shipping_phone' => '0600000000',
        'notes' => 'Please call before delivery',
    ]);

    $response->assertRedirect(route('checkout.show'));
    $response->assertSessionHasErrors(['cart']);
});

test('checkout store creates grouped orders and clears cart', function () {
    Notification::fake();

    $buyer = createBuyer();
    $city = City::create(['name' => 'Tangier', 'region' => 'Tangier-Tetouan-Al Hoceima']);

    $storeA = createStoreWithSeller('Alpha');
    $storeB = createStoreWithSeller('Beta');

    $productA = createProductForStore($storeA, 'Clay Pot', 50.00, 15);
    $productB = createProductForStore($storeB, 'Woven Basket', 30.00, 9);

    $cart = Cart::create(['user_id' => $buyer->id]);
    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $productA->id,
        'quantity' => 2,
    ]);
    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $productB->id,
        'quantity' => 3,
    ]);

    $response = $this->actingAs($buyer)->post(route('checkout.store'), [
        'shipping_address' => '45 Example Ave',
        'city_id' => $city->id,
        'shipping_phone' => '0611223344',
        'notes' => 'Fragile items',
    ]);

    $response->assertRedirect(route('buyer.account.orders.index'));
    $response->assertSessionHas('success', '2 commande(s) passée(s) avec succès !');

    expect(Order::count())->toBe(2);
    expect(OrderItem::count())->toBe(2);
    expect(Earning::count())->toBe(2);
    expect($cart->fresh()->items()->count())->toBe(0);

    expect((int) $productA->fresh()->stock)->toBe(13);
    expect((int) $productB->fresh()->stock)->toBe(6);

    Notification::assertSentTo($storeA->user, NewOrderReceived::class);
    Notification::assertSentTo($storeB->user, NewOrderReceived::class);
});

test('the phone the buyer gives is stored on every order the basket produces', function () {
    Notification::fake();

    $buyer = createBuyer();
    $city = City::create(['name' => 'Oujda', 'region' => 'Oriental']);
    $storeA = createStoreWithSeller('Alpha');
    $storeB = createStoreWithSeller('Beta');

    $cart = Cart::create(['user_id' => $buyer->id]);
    foreach ([$storeA, $storeB] as $i => $store) {
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => createProductForStore($store, "Piece {$i}", 40.00, 5)->id,
            'quantity' => 1,
        ]);
    }

    $this->actingAs($buyer)->post(route('checkout.store'), [
        'shipping_address' => '7 Rue des Potiers',
        'city_id' => $city->id,
        'shipping_phone' => '0655443322',
    ])->assertSessionHasNoErrors();

    // Both halves of a split basket ship to the same person, so each order
    // needs the number — a courier holds one parcel, not the whole basket.
    expect(Order::count())->toBe(2)
        ->and(Order::pluck('shipping_phone')->all())
        ->toBe(['0655443322', '0655443322']);
});

test('a buyer can check out against a saved address', function () {
    Notification::fake();

    $buyer = createBuyer();
    $city = City::create(['name' => 'Essaouira', 'region' => 'Marrakesh-Safi']);
    $store = createStoreWithSeller('Alpha');

    $address = $buyer->addresses()->create([
        'label' => 'Maison',
        'recipient_name' => 'Yasmine',
        'recipient_phone' => '0611111111',
        'address_line' => '12 Derb El Hara',
        'city_id' => $city->id,
        'is_default' => true,
    ]);

    $cart = Cart::create(['user_id' => $buyer->id]);
    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => createProductForStore($store, 'Lantern', 90.00, 3)->id,
        'quantity' => 1,
    ]);

    $this->actingAs($buyer)->post(route('checkout.store'), [
        'address_id' => $address->id,
        'shipping_phone' => '0611111111',
    ])->assertSessionHasNoErrors();

    // Snapshotted flat, so editing or deleting the saved address later cannot
    // rewrite where this parcel actually went.
    $order = Order::first();
    expect($order->shipping_address)->toBe('Yasmine, 12 Derb El Hara, Essaouira')
        ->and((int) $order->city_id)->toBe($city->id);
});

test('a buyer cannot check out against another buyer\'s saved address', function () {
    $buyer = createBuyer();
    $city = City::create(['name' => 'Tetouan', 'region' => 'Tangier-Tetouan-Al Hoceima']);
    $store = createStoreWithSeller('Alpha');

    /*
     * Belongs to a different account. Route-model binding would resolve the id
     * happily, so the ownership scope on the `exists` rule is what stops a
     * parcel being sent to a stranger's door — and their address being copied
     * onto this order.
     */
    $stranger = createBuyer();
    $theirs = $stranger->addresses()->create([
        'address_line' => '9 Rue Privee',
        'city_id' => $city->id,
        'is_default' => true,
    ]);

    $cart = Cart::create(['user_id' => $buyer->id]);
    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => createProductForStore($store, 'Bowl', 40.00, 3)->id,
        'quantity' => 1,
    ]);

    $this->actingAs($buyer)->from(route('checkout.show'))->post(route('checkout.store'), [
        'address_id' => $theirs->id,
        'shipping_phone' => '0600000000',
    ])->assertSessionHasErrors('address_id');

    expect(Order::count())->toBe(0);
});

test('checkout store applies 20% commission and leaves seller pending payout at 180 for a 200 product with 20 shipping', function () {
    Notification::fake();

    $buyer = createBuyer();
    $city = City::create(['name' => 'Fes', 'region' => 'Fes-Meknes']);
    $store = createStoreWithSeller('Omega');
    $product = createProductForStore($store, 'Handcrafted Tray', 200.00, 10);

    $cart = Cart::create(['user_id' => $buyer->id]);
    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'quantity' => 1,
    ]);

    $response = $this->actingAs($buyer)->post(route('checkout.store'), [
        'shipping_address' => '99 Artisan Lane',
        'city_id' => $city->id,
        'shipping_phone' => '0600000000',
        'notes' => 'Please handle with care',
    ]);

    $response->assertRedirect(route('buyer.account.orders.index'));
    $response->assertSessionHas('success', '1 commande(s) passée(s) avec succès !');

    expect(Order::count())->toBe(1);
    expect(Earning::count())->toBe(1);

    $order = Order::first();
    $earning = Earning::first();

    expect((float) $order->platform_fee)->toBe(40.0);
    expect((float) $order->amana_fee)->toBe(20.0);
    expect((float) $order->seller_earnings)->toBe(180.0);

    expect((float) $earning->platform_fee)->toBe(40.0);
    expect((float) $earning->amana_fee)->toBe(20.0);
    expect((float) $earning->net_amount)->toBe(180.0);
});
