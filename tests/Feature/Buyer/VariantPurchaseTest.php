<?php

use App\Models\Cart;
use App\Models\City;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantOption;
use App\Models\Store;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * A product whose stock lives on its variants — which means products.stock is
 * 0, exactly as Admin\ProductController writes it.
 */
function variantProduct(array $variants = [], float $basePrice = 100): array
{
    $seller = User::factory()->create(['role' => 'seller', 'email_verified_at' => now()]);
    $store = Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Atelier '.Str::random(5),
        'slug' => Str::slug('atelier-'.Str::random(6)),
        'status' => 'active',
    ]);

    $product = Product::create([
        'store_id' => $store->id,
        'name' => 'Vase',
        'slug' => Str::slug('vase-'.Str::random(6)),
        'base_price' => $basePrice,
        'stock' => 0,
        'has_variants' => true,
        'status' => 'published',
    ]);

    $made = [];

    foreach ($variants ?: [['value' => 'Rouge', 'stock' => 5, 'price' => null]] as $i => $spec) {
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'stock' => $spec['stock'],
            'price_override' => $spec['price'] ?? null,
            'position' => $i,
            'is_active' => $spec['active'] ?? true,
        ]);

        ProductVariantOption::create([
            'product_variant_id' => $variant->id,
            'type' => 'color',
            'name' => 'Couleur',
            'value' => $spec['value'],
        ]);

        $made[$spec['value']] = $variant->fresh();
    }

    return [$product, $made, $store];
}

function buyerWithCart(): array
{
    $buyer = User::factory()->create(['role' => 'buyer', 'email_verified_at' => now()]);

    return [$buyer, Cart::create(['user_id' => $buyer->id])];
}

test('the detail page exposes variants, their stock and option groups', function () {
    [$product] = variantProduct([
        ['value' => 'Rouge', 'stock' => 3, 'price' => 150],
        ['value' => 'Bleu', 'stock' => 0, 'price' => null],
    ]);

    $this->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('buyer/products/show')
            ->has('product.variants', 2)
            ->where('product.variants.0.label', 'Rouge')
            ->where('product.variants.0.stock', 3)
            ->where('product.variants.0.in_stock', true)
            // The override (150) wins over the product's base_price (100).
            // Compared as a float because JSON gives back a bare number whose
            // int/float-ness depends on the decimal cast, not on intent.
            ->where('product.variants.0.price', fn ($price) => (float) $price === 150.0)
            ->where('product.variants.1.in_stock', false)
            // Product-level stock is 0, but the product is still buyable.
            ->where('product.stock', 0)
            ->where('product.in_stock', true)
            ->has('product.option_groups', 1)
            ->where('product.option_groups.0.name', 'Couleur')
            ->where('product.option_groups.0.type', 'color')
            ->has('product.option_groups.0.values', 2)
            ->etc()
        );
});

test('an inactive variant is not offered on the page', function () {
    [$product] = variantProduct([
        ['value' => 'Rouge', 'stock' => 3],
        ['value' => 'Retiré', 'stock' => 9, 'active' => false],
    ]);

    $this->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('product.variants', 1)
            ->where('product.variants.0.label', 'Rouge')
            ->etc()
        );
});

test('two variants of one product can sit in the cart as separate lines', function () {
    [$product, $variants] = variantProduct([
        ['value' => 'Rouge', 'stock' => 5],
        ['value' => 'Bleu', 'stock' => 5],
    ]);
    [$buyer, $cart] = buyerWithCart();

    $this->actingAs($buyer)
        ->post(route('cart.add'), ['product_id' => $product->id, 'variant_id' => $variants['Rouge']->id, 'quantity' => 1]);
    $this->actingAs($buyer)
        ->post(route('cart.add'), ['product_id' => $product->id, 'variant_id' => $variants['Bleu']->id, 'quantity' => 2]);

    // The old unique(cart_id, product_id) made this impossible.
    expect($cart->items()->count())->toBe(2)
        ->and($cart->items()->where('product_variant_id', $variants['Bleu']->id)->first()->quantity)->toBe(2);
});

test('adding the same variant twice tops up the existing line', function () {
    [$product, $variants] = variantProduct([['value' => 'Rouge', 'stock' => 9]]);
    [$buyer, $cart] = buyerWithCart();

    foreach ([2, 3] as $qty) {
        $this->actingAs($buyer)->post(route('cart.add'), [
            'product_id' => $product->id, 'variant_id' => $variants['Rouge']->id, 'quantity' => $qty,
        ]);
    }

    expect($cart->items()->count())->toBe(1)
        ->and($cart->items()->first()->quantity)->toBe(5);
});

test('a variant product cannot be added without choosing a variant', function () {
    [$product] = variantProduct();
    [$buyer, $cart] = buyerWithCart();

    $this->actingAs($buyer)
        ->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 1])
        ->assertSessionHasErrors('variant_id');

    expect($cart->items()->count())->toBe(0);
});

test('a variant belonging to another product is rejected', function () {
    [$productA] = variantProduct([['value' => 'Rouge', 'stock' => 5]]);
    [, $otherVariants] = variantProduct([['value' => 'Vert', 'stock' => 5]]);
    [$buyer, $cart] = buyerWithCart();

    // Otherwise a crafted request could attach a cheaper variant to any product.
    $this->actingAs($buyer)
        ->post(route('cart.add'), [
            'product_id' => $productA->id,
            'variant_id' => $otherVariants['Vert']->id,
        ])
        ->assertSessionHasErrors('variant_id');

    expect($cart->items()->count())->toBe(0);
});

test('adding more than the variant has in stock is refused', function () {
    [$product, $variants] = variantProduct([['value' => 'Rouge', 'stock' => 2]]);
    [$buyer, $cart] = buyerWithCart();

    $this->actingAs($buyer)
        ->post(route('cart.add'), [
            'product_id' => $product->id,
            'variant_id' => $variants['Rouge']->id,
            'quantity' => 3,
        ])
        ->assertSessionHasErrors('quantity');

    expect($cart->items()->count())->toBe(0);
});

test('stock is checked against the running total, not just the added quantity', function () {
    [$product, $variants] = variantProduct([['value' => 'Rouge', 'stock' => 3]]);
    [$buyer, $cart] = buyerWithCart();

    // 2 then 2 = 4, which exceeds 3 even though neither request does alone.
    $this->actingAs($buyer)->post(route('cart.add'), [
        'product_id' => $product->id, 'variant_id' => $variants['Rouge']->id, 'quantity' => 2,
    ])->assertSessionHasNoErrors();

    $this->actingAs($buyer)->post(route('cart.add'), [
        'product_id' => $product->id, 'variant_id' => $variants['Rouge']->id, 'quantity' => 2,
    ])->assertSessionHasErrors('quantity');

    expect($cart->items()->first()->quantity)->toBe(2);
});

test('checkout bills the variant price and decrements the variant stock', function () {
    [$product, $variants, $store] = variantProduct([
        ['value' => 'Rouge', 'stock' => 5, 'price' => 150],
    ], basePrice: 100);
    [$buyer, $cart] = buyerWithCart();
    $city = City::create(['name' => 'Casablanca']);

    $cart->items()->create([
        'product_id' => $product->id,
        'product_variant_id' => $variants['Rouge']->id,
        'quantity' => 2,
    ]);

    $orders = app(CheckoutService::class)->process($cart->fresh('items'), [
        'city_id' => $city->id,
        'shipping_address' => '1 rue du Souk',
    ]);

    $line = OrderItem::where('order_id', $orders[0]->id)->first();

    // 150 (the override), not 100 (the product's base_price).
    expect((float) $line->unit_price)->toBe(150.0)
        ->and((float) $line->subtotal)->toBe(300.0)
        ->and((float) Order::find($orders[0]->id)->total_amount)->toBe(300.0)
        ->and($line->product_variant_id)->toBe($variants['Rouge']->id)
        // Snapshot survives the variant later changing or going away.
        ->and($line->variant_label)->toBe('Rouge');

    // Variant stock moved; the product's own column stays untouched at 0.
    expect(ProductVariant::find($variants['Rouge']->id)->stock)->toBe(3)
        ->and(Product::find($product->id)->stock)->toBe(0);
});

test('checkout refuses when the variant sold out after it entered the cart', function () {
    [$product, $variants] = variantProduct([['value' => 'Rouge', 'stock' => 5]]);
    [$buyer, $cart] = buyerWithCart();
    $city = City::create(['name' => 'Casablanca']);

    $cart->items()->create([
        'product_id' => $product->id,
        'product_variant_id' => $variants['Rouge']->id,
        'quantity' => 4,
    ]);

    // Someone else buys them out in the meantime.
    ProductVariant::where('id', $variants['Rouge']->id)->update(['stock' => 1]);

    expect(fn () => app(CheckoutService::class)->process($cart->fresh('items'), [
        'city_id' => $city->id,
        'shipping_address' => '1 rue du Souk',
    ]))->toThrow(ValidationException::class);

    expect(Order::count())->toBe(0)
        ->and(ProductVariant::find($variants['Rouge']->id)->stock)->toBe(1);
});

test('the order line survives its variant being deleted', function () {
    [$product, $variants] = variantProduct([['value' => 'Rouge', 'stock' => 5]]);
    [$buyer, $cart] = buyerWithCart();
    $city = City::create(['name' => 'Casablanca']);

    $cart->items()->create([
        'product_id' => $product->id,
        'product_variant_id' => $variants['Rouge']->id,
        'quantity' => 1,
    ]);

    $orders = app(CheckoutService::class)->process($cart->fresh('items'), [
        'city_id' => $city->id,
        'shipping_address' => '1 rue du Souk',
    ]);

    ProductVariant::find($variants['Rouge']->id)->delete();

    $line = OrderItem::where('order_id', $orders[0]->id)->first();

    // Financial history intact: the line is still there, still says what was
    // bought, and only the (now dangling) FK was nulled.
    expect($line)->not->toBeNull()
        ->and($line->variant_label)->toBe('Rouge')
        ->and($line->product_variant_id)->toBeNull()
        ->and((float) $line->subtotal)->toBe(100.0);
});

test('variants defining different option axes are each still buyable', function () {
    /*
     * Real seeded data looks like this: one variant carries only a colour,
     * another only a size, so no single variant covers every option group on
     * the product. The page must still resolve each one (it matches against a
     * variant's own options, not the product's full group list), and the cart
     * must still accept them.
     */
    $seller = User::factory()->create(['role' => 'seller', 'email_verified_at' => now()]);
    $store = Store::create([
        'user_id' => $seller->id, 'store_name' => 'Mixed', 'slug' => 'mixed-'.Str::random(5), 'status' => 'active',
    ]);
    $product = Product::create([
        'store_id' => $store->id, 'name' => 'Coussin', 'slug' => 'coussin-'.Str::random(5),
        'base_price' => 90, 'stock' => 0, 'has_variants' => true, 'status' => 'published',
    ]);

    $red = ProductVariant::create(['product_id' => $product->id, 'stock' => 3, 'position' => 0]);
    ProductVariantOption::create([
        'product_variant_id' => $red->id, 'type' => 'color', 'name' => 'Couleur', 'value' => 'Red',
    ]);

    $xl = ProductVariant::create(['product_id' => $product->id, 'stock' => 8, 'price_override' => 60, 'position' => 1]);
    ProductVariantOption::create([
        'product_variant_id' => $xl->id, 'type' => 'dimension', 'name' => 'Dimension', 'value' => 'XL',
    ]);

    $this->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('product.variants', 2)
            // Two groups, each contributed by a different variant.
            ->has('product.option_groups', 2)
            ->where('product.in_stock', true)
            ->etc()
        );

    [$buyer, $cart] = buyerWithCart();

    $this->actingAs($buyer)
        ->post(route('cart.add'), ['product_id' => $product->id, 'variant_id' => $xl->id, 'quantity' => 1])
        ->assertSessionHasNoErrors();

    expect($cart->items()->count())->toBe(1)
        ->and($cart->items()->first()->product_variant_id)->toBe($xl->id);
});

test('a non-variant product still checks out on the product stock', function () {
    $seller = User::factory()->create(['role' => 'seller', 'email_verified_at' => now()]);
    $store = Store::create([
        'user_id' => $seller->id, 'store_name' => 'Simple', 'slug' => 'simple-'.Str::random(5), 'status' => 'active',
    ]);
    $product = Product::create([
        'store_id' => $store->id, 'name' => 'Tapis', 'slug' => 'tapis-'.Str::random(5),
        'base_price' => 80, 'stock' => 4, 'has_variants' => false, 'status' => 'published',
    ]);
    [$buyer, $cart] = buyerWithCart();
    $city = City::create(['name' => 'Casablanca']);

    $this->actingAs($buyer)->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 2])
        ->assertSessionHasNoErrors();

    app(CheckoutService::class)->process($cart->fresh('items'), [
        'city_id' => $city->id, 'shipping_address' => '1 rue du Souk',
    ]);

    expect(Product::find($product->id)->stock)->toBe(2)
        ->and(OrderItem::first()->product_variant_id)->toBeNull()
        ->and(OrderItem::first()->variant_label)->toBeNull();
});
