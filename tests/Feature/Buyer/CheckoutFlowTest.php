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
use App\Notifications\OrderPlaced;
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

/**
 * Everything checkout insists on, with the per-test bits left to the caller.
 *
 * The recipient's name and the payment method are required down both branches
 * — a buyer may have no name on file at all, now that signup asks only for a
 * password — while the postal code and country are wanted only when the
 * address is typed rather than picked, so a saved-address test overrides them
 * away with `null`.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function checkoutPayload(array $overrides = []): array
{
    return array_filter(
        array_merge([
            'first_name' => 'Yasmine',
            'last_name' => 'Alaoui',
            'shipping_phone' => '0600000000',
            'postal_code' => '40000',
            'country' => 'MA',
            'payment_method' => 'cod',
        ], $overrides),
        fn ($value) => $value !== null,
    );
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
    /*
     * `city_id` is deliberately absent from this list. Delivery is priced per
     * Moroccan city and per foreign country, and `cities` holds Moroccan
     * cities only — so a city is required once the country says Morocco, not
     * before. With no country submitted at all, that is the error to answer
     * first; the next test covers the city rule itself.
     */
    $response->assertSessionHasErrors([
        'first_name', 'last_name', 'shipping_phone',
        'shipping_address', 'postal_code', 'country',
        'payment_method',
    ]);
});

test('a Moroccan destination must name a city, and an unknown country is refused', function () {
    $buyer = createBuyer();

    // Morocco with no city: rejected, because domestic delivery is priced
    // city by city.
    $this->actingAs($buyer)
        ->from(route('checkout.show'))
        ->post(route('checkout.store'), checkoutPayload([
            'shipping_address' => '45 Example Ave',
            'country' => 'MA',
        ]))
        ->assertSessionHasErrors('city_id');

    /*
     * Amana's rate card is the shipping whitelist: a destination with no rate
     * on file is one we have no price for, so it is refused outright. See
     * DeliveryFeeService::shippableCountryCodes().
     */
    $this->actingAs($buyer)
        ->from(route('checkout.show'))
        ->post(route('checkout.store'), checkoutPayload([
            'shipping_address' => '12 Rue de Paris',
            'country' => 'ZZ',
        ]))
        ->assertSessionHasErrors('country');
});

test('checkout store rejects empty cart', function () {
    $buyer = createBuyer();
    $city = City::create(['name' => 'Marrakesh', 'region' => 'Marrakesh-Safi']);
    Cart::create(['user_id' => $buyer->id]);

    $response = $this->actingAs($buyer)->from(route('checkout.show'))->post(route('checkout.store'), checkoutPayload([
        'shipping_address' => '123 Test Street',
        'city_id' => $city->id,
        'notes' => 'Please call before delivery',
    ]));

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

    $response = $this->actingAs($buyer)->post(route('checkout.store'), checkoutPayload([
        'shipping_address' => '45 Example Ave',
        'city_id' => $city->id,
        'shipping_phone' => '0611223344',
        'notes' => 'Fragile items',
    ]));

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

test('a split basket sends the buyer one confirmation carrying every order and item', function () {
    Notification::fake();

    $buyer = createBuyer();
    $city = City::create(['name' => 'Fez', 'region' => 'Fes-Meknes']);

    $storeA = createStoreWithSeller('Alpha');
    $storeB = createStoreWithSeller('Beta');

    $potA1 = createProductForStore($storeA, 'Clay Pot', 50.00, 15);
    $potA2 = createProductForStore($storeA, 'Tea Set', 80.00, 15);
    $basketB = createProductForStore($storeB, 'Woven Basket', 30.00, 9);

    $cart = Cart::create(['user_id' => $buyer->id]);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $potA1->id, 'quantity' => 1]);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $potA2->id, 'quantity' => 1]);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $basketB->id, 'quantity' => 1]);

    $this->actingAs($buyer)->post(route('checkout.store'), checkoutPayload([
        'shipping_address' => '9 Rue des Potiers',
        'city_id' => $city->id,
    ]))->assertSessionHasNoErrors();

    // One OrderPlaced for the buyer, not one per store — otherwise a
    // multi-seller purchase reads, in the inbox, as only its last order.
    Notification::assertSentToTimes($buyer, OrderPlaced::class, 1);

    Notification::assertSentTo($buyer, OrderPlaced::class, function (OrderPlaced $notification) {
        expect($notification->orders)->toHaveCount(2);
        expect($notification->orders->sum(fn ($order) => $order->items->count()))->toBe(3);

        return true;
    });
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

    $this->actingAs($buyer)->post(route('checkout.store'), checkoutPayload([
        'shipping_address' => '7 Rue des Potiers',
        'city_id' => $city->id,
        'shipping_phone' => '0655443322',
    ]))->assertSessionHasNoErrors();

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

    $this->actingAs($buyer)->post(route('checkout.store'), checkoutPayload([
        'address_id' => $address->id,
        'shipping_phone' => '0611111111',
        // Not asked for on this branch — the saved row already carries them.
        'postal_code' => null,
        'country' => null,
    ]))->assertSessionHasNoErrors();

    /*
     * Snapshotted flat, so editing or deleting the saved address later cannot
     * rewrite where this parcel actually went.
     *
     * The name in front is the one typed on *this* order, not the one stored
     * on the address — a buyer may send a parcel to someone else without
     * wanting to rewrite their own saved address.
     */
    $order = Order::first();
    expect($order->shipping_address)->toBe('Yasmine Alaoui, Yasmine, 12 Derb El Hara, Essaouira')
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

    $this->actingAs($buyer)->from(route('checkout.show'))->post(route('checkout.store'), checkoutPayload([
        'address_id' => $theirs->id,
        'postal_code' => null,
        'country' => null,
    ]))->assertSessionHasErrors('address_id');

    expect(Order::count())->toBe(0);
});

test('checkout store applies 20% commission and passes 40 delivery through to the seller for a 200 product', function () {
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

    $response = $this->actingAs($buyer)->post(route('checkout.store'), checkoutPayload([
        'shipping_address' => '99 Artisan Lane',
        'city_id' => $city->id,
        'notes' => 'Please handle with care',
    ]));

    $response->assertRedirect(route('buyer.account.orders.index'));
    $response->assertSessionHas('success', '1 commande(s) passée(s) avec succès !');

    expect(Order::count())->toBe(1);
    expect(Earning::count())->toBe(1);

    $order = Order::first();
    $earning = Earning::first();

    // The platform keeps its 20% (40) and the COD delivery fee is passed back to
    // the seller, who fronts the courier cost: 200 - 40 + 40 = 200.
    expect((float) $order->platform_fee)->toBe(40.0);
    expect((float) $order->delivery_fee)->toBe(40.0);
    expect((float) $order->seller_earnings)->toBe(200.0);

    expect((float) $earning->platform_fee)->toBe(40.0);
    expect((float) $earning->delivery_fee)->toBe(40.0);
    expect((float) $earning->net_amount)->toBe(200.0);
});

// ── What the redesigned checkout added ──────────────────────────────────

/*
 * Signup asks only for a password now (RegistrationCodeService::complete), so
 * checkout is the first screen most buyers give a name to. Keeping it means
 * they are not asked again on the next order.
 */
test('checkout fills in a name and phone the account was missing', function () {
    Notification::fake();

    $buyer = createBuyer();
    $buyer->forceFill(['first_name' => null, 'last_name' => null, 'phone_number' => null])->save();

    $city = City::create(['name' => 'Rabat', 'region' => 'Rabat-Sale-Kenitra']);
    $store = createStoreWithSeller('Alpha');

    $cart = Cart::create(['user_id' => $buyer->id]);
    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => createProductForStore($store, 'Tray', 60.00, 5)->id,
        'quantity' => 1,
    ]);

    $this->actingAs($buyer)->post(route('checkout.store'), checkoutPayload([
        'shipping_address' => '3 Avenue Hassan II',
        'city_id' => $city->id,
        'shipping_phone' => '0622334455',
    ]))->assertSessionHasNoErrors();

    expect($buyer->fresh())
        ->first_name->toBe('Yasmine')
        ->last_name->toBe('Alaoui')
        ->phone_number->toBe('0622334455');
});

/*
 * The other half of that rule. A buyer sending a parcel to a friend types the
 * friend's name into the delivery form; that must not rename their account —
 * the name they set from /account wins.
 */
test('checkout never overwrites a name the account already has', function () {
    Notification::fake();

    $buyer = createBuyer();
    $buyer->forceFill([
        'first_name' => 'Karim',
        'last_name' => 'Tazi',
        'phone_number' => '0699999999',
    ])->save();

    $city = City::create(['name' => 'Oujda', 'region' => 'Oriental']);
    $store = createStoreWithSeller('Alpha');

    $cart = Cart::create(['user_id' => $buyer->id]);
    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => createProductForStore($store, 'Bowl', 60.00, 5)->id,
        'quantity' => 1,
    ]);

    $this->actingAs($buyer)->post(route('checkout.store'), checkoutPayload([
        'shipping_address' => '8 Rue Zerktouni',
        'city_id' => $city->id,
        'shipping_phone' => '0600000001',
    ]))->assertSessionHasNoErrors();

    expect($buyer->fresh())
        ->first_name->toBe('Karim')
        ->last_name->toBe('Tazi')
        ->phone_number->toBe('0699999999');
});

/*
 * The postal code rides with the city on the snapshot, so the line reads as
 * one place rather than two — see Address::toShippingLine().
 */
test('the postal code is snapshotted onto the order address', function () {
    Notification::fake();

    $buyer = createBuyer();
    $city = City::create(['name' => 'Agadir', 'region' => 'Souss-Massa']);
    $store = createStoreWithSeller('Alpha');

    $cart = Cart::create(['user_id' => $buyer->id]);
    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => createProductForStore($store, 'Lamp', 60.00, 5)->id,
        'quantity' => 1,
    ]);

    $this->actingAs($buyer)->post(route('checkout.store'), checkoutPayload([
        'shipping_address' => '21 Boulevard du Port',
        'city_id' => $city->id,
        'postal_code' => '80000',
    ]))->assertSessionHasNoErrors();

    expect(Order::first()->shipping_address)
        ->toBe('Yasmine Alaoui, 21 Boulevard du Port, Agadir 80000');
});

/*
 * A new address saved from checkout keeps the code and country too, or the
 * next order placed against it would quietly lose them.
 */
test('saving an address from checkout keeps its postal code and country', function () {
    Notification::fake();

    $buyer = createBuyer();
    $city = City::create(['name' => 'Safi', 'region' => 'Marrakesh-Safi']);
    $store = createStoreWithSeller('Alpha');

    $cart = Cart::create(['user_id' => $buyer->id]);
    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => createProductForStore($store, 'Vase', 60.00, 5)->id,
        'quantity' => 1,
    ]);

    $this->actingAs($buyer)->post(route('checkout.store'), checkoutPayload([
        'shipping_address' => '5 Derb Sidi Bouzid',
        'city_id' => $city->id,
        'postal_code' => '46000',
        'save_address' => true,
        'address_label' => 'Maison',
    ]))->assertSessionHasNoErrors();

    expect($buyer->addresses()->first())
        ->postal_code->toBe('46000')
        ->country->toBe('MA')
        ->label->toBe('Maison');
});

/*
 * Cash on delivery is the only method wired up. The whitelist is what stops an
 * order being created as "paid by card" when no card was ever charged.
 */
test('checkout refuses a payment method that is not implemented', function () {
    $buyer = createBuyer();
    $city = City::create(['name' => 'Meknes', 'region' => 'Fes-Meknes']);
    $store = createStoreWithSeller('Alpha');

    $cart = Cart::create(['user_id' => $buyer->id]);
    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => createProductForStore($store, 'Plate', 60.00, 5)->id,
        'quantity' => 1,
    ]);

    $this->actingAs($buyer)->from(route('checkout.show'))->post(route('checkout.store'), checkoutPayload([
        'shipping_address' => '2 Rue Rouamzine',
        'city_id' => $city->id,
        'payment_method' => 'card',
    ]))->assertSessionHasErrors('payment_method');

    expect(Order::count())->toBe(0);
});

/*
 * Everything ships domestically today, and `cities` holds only Moroccan
 * cities — a request naming anywhere else is a mistake or a probe.
 */
test('checkout refuses a country it does not ship to', function () {
    $buyer = createBuyer();
    $city = City::create(['name' => 'Nador', 'region' => 'Oriental']);
    $store = createStoreWithSeller('Alpha');

    $cart = Cart::create(['user_id' => $buyer->id]);
    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => createProductForStore($store, 'Cup', 60.00, 5)->id,
        'quantity' => 1,
    ]);

    $this->actingAs($buyer)->from(route('checkout.show'))->post(route('checkout.store'), checkoutPayload([
        'shipping_address' => '14 Avenue Mohammed V',
        'city_id' => $city->id,
        'country' => 'FR',
    ]))->assertSessionHasErrors('country');

    expect(Order::count())->toBe(0);
});

/* The field is real and reachable; there is simply nothing behind it yet. */
test('every discount code is rejected for now', function () {
    $buyer = createBuyer();

    $this->actingAs($buyer)->from(route('checkout.show'))
        ->post(route('checkout.coupon'), ['code' => 'WELCOME10'])
        ->assertSessionHasErrors('code', null, 'coupon');
});
