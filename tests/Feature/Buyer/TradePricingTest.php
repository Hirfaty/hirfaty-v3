<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\City;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * Pro prices are a choice anyone makes (PriceModeService), and the same
 * choice decides both what the storefront quotes and what checkout bills —
 * so these assert the two together rather than trusting them to agree.
 */
function tradeStore(): Store
{
    $seller = User::factory()->create(['role' => 'seller', 'email_verified_at' => now()]);

    return Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Trade Store',
        'slug' => Str::slug('trade-store-'.Str::random(6)),
        'status' => 'active',
    ]);
}

function tradeProduct(Store $store): Product
{
    return Product::create([
        'store_id' => $store->id,
        'name' => 'Wholesale Lantern',
        'slug' => Str::slug('wholesale-lantern-'.Str::random(6)),
        'base_price' => 200,
        'sale_price' => null,
        'professional_price' => 120,
        'stock' => 50,
        'status' => 'published',
    ]);
}

function checkoutAs(User $user, Product $product, int $qty = 2): void
{
    $city = City::create(['name' => 'Fès', 'region' => 'Fès-Meknès']);
    // firstOrCreate, not create: carts.user_id is unique and merely *viewing*
    // a page already provisions one via the shared cart prop.
    $cart = Cart::firstOrCreate(['user_id' => $user->id]);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => $qty]);

    /*
     * The full payload, not just the address. Checkout also insists on a
     * recipient name, a postal code, a country and a payment method — see
     * CheckoutRequest. Asserted here rather than fired blind: without it a
     * validation change silently stops creating orders, and these tests then
     * fail much later on a null OrderItem instead of on the actual cause.
     */
    test()->actingAs($user)->post(route('checkout.store'), [
        'first_name' => 'Yasmine',
        'last_name' => 'Alaoui',
        'shipping_address' => '1 Rue Test',
        'city_id' => $city->id,
        'postal_code' => '30000',
        'country' => 'MA',
        // Required now: Amana rings the buyer to deliver.
        'shipping_phone' => '0600000000',
        'payment_method' => 'card',
    ])->assertSessionHasNoErrors();

    // Placed once the card payment is confirmed.
    confirmCardPayment();
}

test('a buyer in pro mode is billed the wholesale rate', function () {
    $buyer = proBuyer();

    $product = tradeProduct(tradeStore());

    checkoutAs($buyer, $product, 2);

    $item = OrderItem::first();
    $order = Order::first();

    expect((float) $item->unit_price)->toBe(120.0)
        ->and((float) $item->subtotal)->toBe(240.0)
        // The order total must equal the sum of its own lines — they are
        // computed in separate places in CheckoutService.
        ->and((float) $order->total_amount)->toBe(240.0);
});

test('a buyer on personal prices is billed retail even when a trade rate exists', function () {
    $buyer = User::factory()->create(['role' => 'buyer', 'email_verified_at' => now()]);

    $product = tradeProduct(tradeStore());

    checkoutAs($buyer, $product, 2);

    expect((float) OrderItem::first()->unit_price)->toBe(200.0)
        ->and((float) Order::first()->total_amount)->toBe(400.0);
});

/**
 * The role an admin may still grant (the old trade-account review) decides
 * nothing on its own any more: prices follow the buyer's own choice.
 */
test('a professional_buyer role alone does not change the bill', function () {
    $buyer = User::factory()->create([
        'role' => 'professional_buyer',
        'email_verified_at' => now(),
        'professional_verified_at' => now(),
    ]);

    $product = tradeProduct(tradeStore());

    checkoutAs($buyer, $product, 2);

    expect((float) OrderItem::first()->unit_price)->toBe(200.0);
});

test('what a pro-mode buyer is quoted matches what they are billed', function () {
    $buyer = proBuyer();

    $product = tradeProduct(tradeStore());

    // Driven through the real page rather than instantiating the resource by
    // hand, so the quote comes from the same request path as the bill.
    $this->actingAs($buyer)
        ->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('buyer/products/show')
            // 120 not 120.0: the float round-trips through JSON as an int,
            // and Inertia's where() compares strictly.
            ->where('product.professional_effective_price', 120)
        );

    checkoutAs($buyer, $product, 1);

    expect((float) OrderItem::first()->unit_price)->toBe(120.0);
});

test('personal prices do not show the wholesale rate', function () {
    $buyer = User::factory()->create(['role' => 'buyer', 'email_verified_at' => now()]);

    $product = tradeProduct(tradeStore());

    $this->actingAs($buyer)
        ->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('product.professional_effective_price')
            ->where('product.base_price', '200.00')
        );
});

test('personal prices list consumer pieces, not trade-only ones', function () {
    $store = tradeStore();

    Product::create([
        'store_id' => $store->id,
        'name' => 'Trade only',
        'slug' => Str::slug('trade-only-'.Str::random(6)),
        'base_price' => 100,
        'stock' => 5,
        'status' => 'published',
        'visibility' => 'professional',
    ]);
    Product::create([
        'store_id' => $store->id,
        'name' => 'Consumer only',
        'slug' => Str::slug('consumer-only-'.Str::random(6)),
        'base_price' => 100,
        'stock' => 5,
        'status' => 'published',
        'visibility' => 'individual',
    ]);

    $buyer = User::factory()->create(['role' => 'buyer', 'email_verified_at' => now()]);

    $this->actingAs($buyer)
        ->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products', 1)
            ->where('products.0.name', 'Consumer only')
        );
});

test('a product without a trade rate falls back to retail in pro mode', function () {
    $buyer = proBuyer();

    $store = tradeStore();
    $retailOnly = Product::create([
        'store_id' => $store->id,
        'name' => 'Retail Only Rug',
        'slug' => Str::slug('retail-only-rug-'.Str::random(6)),
        'base_price' => 300,
        'stock' => 10,
        'status' => 'published',
    ]);

    checkoutAs($buyer, $retailOnly, 1);

    expect((float) OrderItem::first()->unit_price)->toBe(300.0);
});

/**
 * The checkout page posts the price list it quoted from. If the buyer flipped
 * the switch since (another tab), the total on screen is not what would be
 * charged — refused rather than billed.
 */
test('checkout refuses a total quoted on another price list', function () {
    $buyer = proBuyer();

    $product = tradeProduct(tradeStore());
    $city = City::create(['name' => 'Fès', 'region' => 'Fès-Meknès']);
    Cart::firstOrCreate(['user_id' => $buyer->id])
        ->items()->create(['product_id' => $product->id, 'quantity' => 1]);

    $this->actingAs($buyer)->post(route('checkout.store'), [
        'first_name' => 'Yasmine',
        'last_name' => 'Alaoui',
        'shipping_address' => '1 Rue Test',
        'city_id' => $city->id,
        'postal_code' => '30000',
        'country' => 'MA',
        'shipping_phone' => '0600000000',
        'payment_method' => 'card',
        'price_mode' => 'individual',
    ])->assertSessionHasErrors('cart');

    expect(Order::withoutGlobalScope(Order::SETTLED_SCOPE)->count())->toBe(0);
});
