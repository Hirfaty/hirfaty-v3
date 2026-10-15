<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\City;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\BuyNowService;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A buyer whose cart holds a vase, and a tajine (200 MAD, stock 2) they are
 * about to buy straight from its page.
 *
 * @return array{buyer: User, tajine: Product, vase: Product, payload: array<string, mixed>}
 */
function buyNowSetup(): array
{
    $buyer = User::factory()->create(['role' => 'buyer', 'email_verified_at' => now()]);
    $city = City::create(['name' => 'Casablanca', 'region' => 'Casablanca-Settat']);

    $seller = User::factory()->create(['role' => 'seller', 'email_verified_at' => now()]);
    $store = Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Atelier Zellige',
        'slug' => 'atelier-zellige-'.Str::random(6),
        'status' => 'active',
    ]);

    $make = fn (string $name, int $stock) => Product::create([
        'store_id' => $store->id,
        'name' => $name,
        'slug' => Str::slug($name).'-'.Str::random(6),
        'base_price' => 200,
        'stock' => $stock,
        'status' => 'published',
    ]);

    $tajine = $make('Tajine', 2);
    $vase = $make('Vase', 5);

    $cart = Cart::create(['user_id' => $buyer->id]);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $vase->id, 'quantity' => 1]);

    return [
        'buyer' => $buyer,
        'tajine' => $tajine,
        'vase' => $vase,
        'payload' => [
            'first_name' => 'Yasmine',
            'last_name' => 'Alaoui',
            'shipping_phone' => '0600000000',
            'shipping_address' => '12 Rue Ibn Battouta',
            'city_id' => $city->id,
            'postal_code' => '20000',
            'country' => 'MA',
            'payment_method' => 'card',
        ],
    ];
}

test('buy now goes to a checkout of that piece alone, leaving the cart untouched', function () {
    $setup = buyNowSetup();

    $this->actingAs($setup['buyer'])
        ->post(route('checkout.buy_now.start'), ['product_id' => $setup['tajine']->id, 'quantity' => 2])
        ->assertRedirect(route('checkout.buy_now'));

    $this->actingAs($setup['buyer'])
        ->get(route('checkout.buy_now'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('buyer/checkout')
            ->has('items', 1)
            ->where('items.0.product_id', $setup['tajine']->id)
            ->where('items.0.quantity', 2)
            ->where('buyNow.product_slug', $setup['tajine']->slug));

    // Nothing was put in the cart: it still holds the vase, and only the vase.
    expect(CartItem::pluck('product_id')->all())->toBe([$setup['vase']->id]);

    // The ordinary checkout is still the cart's.
    $this->actingAs($setup['buyer'])
        ->get(route('checkout.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('items.0.product_id', $setup['vase']->id)
            ->where('buyNow', null));
});

test('checking out a buy-now piece bills that piece and nothing from the cart', function () {
    $setup = buyNowSetup();

    $this->actingAs($setup['buyer'])
        ->post(route('checkout.buy_now.start'), ['product_id' => $setup['tajine']->id, 'quantity' => 1]);

    $this->actingAs($setup['buyer'])
        ->post(route('checkout.store'), ['buy_now' => true] + $setup['payload'])
        ->assertRedirect();

    $order = Order::withoutGlobalScope(Order::SETTLED_SCOPE)->with('items')->sole();
    expect($order->items->pluck('product_id')->all())->toBe([$setup['tajine']->id])
        ->and((float) $order->total_amount)->toBe(200.0)
        ->and($setup['tajine']->fresh()->stock)->toBe(1)
        ->and($setup['vase']->fresh()->stock)->toBe(5)
        ->and(session()->has(BuyNowService::SESSION_KEY))->toBeFalse();

    confirmCardPayment($order->group_reference);

    // Paid — and the vase is still waiting in the cart.
    expect(Order::sole()->payment_status)->toBe('paid')
        ->and(CartItem::pluck('product_id')->all())->toBe([$setup['vase']->id]);
});

test('buy now is refused for what the cart would refuse', function () {
    $setup = buyNowSetup();

    $this->actingAs($setup['buyer'])
        ->from(route('products.show', $setup['tajine']->slug))
        ->post(route('checkout.buy_now.start'), ['product_id' => $setup['tajine']->id, 'quantity' => 3])
        ->assertRedirect(route('products.show', $setup['tajine']->slug))
        ->assertSessionHasErrors('quantity');

    $setup['tajine']->update(['base_price' => null]);

    $this->actingAs($setup['buyer'])
        ->post(route('checkout.buy_now.start'), ['product_id' => $setup['tajine']->id, 'quantity' => 1])
        ->assertSessionHasErrors('product_id');

    expect(session()->has(BuyNowService::SESSION_KEY))->toBeFalse();

    // With nothing held, the buy-now checkout has nothing to show.
    $this->actingAs($setup['buyer'])
        ->get(route('checkout.buy_now'))
        ->assertRedirect(route('cart.show'));
});

test('a guest is sent to sign in first', function () {
    $setup = buyNowSetup();

    $this->post(route('checkout.buy_now.start'), ['product_id' => $setup['tajine']->id, 'quantity' => 1])
        ->assertRedirect(route('login'));
});

test('giving up on paying for a buy-now piece leads back to its page, not the cart', function () {
    $setup = buyNowSetup();

    $this->actingAs($setup['buyer'])
        ->post(route('checkout.buy_now.start'), ['product_id' => $setup['tajine']->id, 'quantity' => 1]);
    $this->actingAs($setup['buyer'])
        ->post(route('checkout.store'), ['buy_now' => true] + $setup['payload']);

    $reference = Order::withoutGlobalScope(Order::SETTLED_SCOPE)->value('group_reference');

    $this->actingAs($setup['buyer'])
        ->post(route('checkout.payment.cancel', $reference))
        ->assertRedirect(route('products.show', $setup['tajine']->slug));

    expect($setup['tajine']->fresh()->stock)->toBe(2);
});
