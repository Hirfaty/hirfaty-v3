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
 * Trade pricing is gated on a *verified* professional account, and the same
 * gate decides both what the storefront quotes and what checkout bills — so
 * these assert the two together rather than trusting them to agree.
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

    test()->actingAs($user)->post(route('checkout.store'), [
        'shipping_address' => '1 Rue Test',
        'city_id' => $city->id,
    ]);
}

test('a verified trade buyer is billed the wholesale rate', function () {
    $buyer = User::factory()->create([
        'role' => 'professional_buyer',
        'email_verified_at' => now(),
        'professional_verified_at' => now(),
    ]);

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

test('an UNVERIFIED trade buyer is billed retail', function () {
    $buyer = User::factory()->create([
        'role' => 'professional_buyer',
        'email_verified_at' => now(),
        'professional_verified_at' => null,
    ]);

    $product = tradeProduct(tradeStore());

    checkoutAs($buyer, $product, 2);

    expect((float) OrderItem::first()->unit_price)->toBe(200.0)
        ->and((float) Order::first()->total_amount)->toBe(400.0);
});

test('an ordinary buyer is billed retail even when a trade rate exists', function () {
    $buyer = User::factory()->create(['role' => 'buyer', 'email_verified_at' => now()]);

    $product = tradeProduct(tradeStore());

    checkoutAs($buyer, $product, 2);

    expect((float) OrderItem::first()->unit_price)->toBe(200.0)
        ->and((float) Order::first()->total_amount)->toBe(400.0);
});

test('what a verified trade buyer is quoted matches what they are billed', function () {
    $buyer = User::factory()->create([
        'role' => 'professional_buyer',
        'email_verified_at' => now(),
        'professional_verified_at' => now(),
    ]);

    $product = tradeProduct(tradeStore());

    // Driven through the real page rather than instantiating the resource by
    // hand: the trade block is gated on $request->user(), so a hand-built
    // request can silently resolve to null and make this pass vacuously.
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

test('an unverified trade buyer is not even shown the wholesale rate', function () {
    $unverified = User::factory()->create([
        'role' => 'professional_buyer',
        'email_verified_at' => now(),
        'professional_verified_at' => null,
    ]);

    $product = tradeProduct(tradeStore());

    // The whole trade block is merged in conditionally; publishing a seller's
    // wholesale price to an unvetted account undercuts their retail price.
    $this->actingAs($unverified)
        ->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('product.professional_effective_price')
            ->where('product.base_price', '200.00')
        );
});

test('an unverified trade buyer browses as an ordinary buyer', function () {
    $store = tradeStore();

    // Visibility routes through audienceFor() too, so requiring verification
    // for pricing necessarily gates the trade-only catalogue as well.
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

    $unverified = User::factory()->create([
        'role' => 'professional_buyer',
        'email_verified_at' => now(),
        'professional_verified_at' => null,
    ]);

    $this->actingAs($unverified)
        ->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products', 1)
            ->where('products.0.name', 'Consumer only')
        );
});

test('a product without a trade rate falls back to retail for a verified buyer', function () {
    $buyer = User::factory()->create([
        'role' => 'professional_buyer',
        'email_verified_at' => now(),
        'professional_verified_at' => now(),
    ]);

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
