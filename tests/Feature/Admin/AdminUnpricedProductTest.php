<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\City;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantOption;
use App\Models\Store;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Validation\ValidationException;

/**
 * The admin catalogues an artisan's work before the price has been agreed, so
 * `base_price` is optional even when publishing. The bargain is that nothing
 * unpriced reaches a buyer: it stays out of every storefront query and cannot
 * be billed.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'super_admin']);

    $this->category = Category::create([
        'slug' => 'tapis',
        'name' => ['fr' => 'Tapis', 'ar' => 'زربية', 'en' => 'Rugs'],
    ]);
    $this->city = City::create(['name' => 'Fès']);

    $seller = User::factory()->create(['role' => 'seller']);
    $this->store = Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Atelier Fès',
        'slug' => 'atelier-fes',
        'category_id' => $this->category->id,
        'city_id' => $this->city->id,
    ]);
});

function publishProduct(array $attributes = []): Product
{
    return Product::create(array_merge([
        'store_id' => test()->store->id,
        'category_id' => test()->category->id,
        'name' => 'Tapis',
        'slug' => 'tapis-'.Str::random(5),
        'base_price' => 1500,
        'stock' => 5,
        'visibility' => 'both',
        'status' => 'published',
    ], $attributes));
}

test('admin publishes a product with no price', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), [
            'store_id' => $this->store->id,
            'name' => 'Prix à convenir',
            'category_id' => $this->category->id,
            'stock' => 3,
            'status' => 'published',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $product = Product::firstWhere('name', 'Prix à convenir');

    // NULL, not 0 — "not priced yet" is a different statement from "free",
    // and the old `?? 0` fallback conflated the two.
    expect($product->base_price)->toBeNull()
        ->and($product->isPriced())->toBeFalse()
        ->and($product->status)->toBe('published');
});

test('an unpriced product is kept out of the storefront', function () {
    $priced = publishProduct(['name' => 'Avec prix', 'slug' => 'avec-prix']);
    $unpriced = publishProduct([
        'name' => 'Sans prix',
        'slug' => 'sans-prix',
        'base_price' => null,
    ]);

    $listed = Product::storefront()->pluck('id');

    expect($listed)->toContain($priced->id)
        ->and($listed)->not->toContain($unpriced->id);

    // ...and it is not reachable by direct URL either, which is the hole a
    // listing-only filter would leave open.
    $this->get(route('products.show', $unpriced->slug))->assertNotFound();
    $this->get(route('products.show', $priced->slug))->assertSuccessful();
});

test('the public listing hides unpriced products', function () {
    publishProduct(['name' => 'Avec prix', 'slug' => 'avec-prix']);
    publishProduct([
        'name' => 'Sans prix',
        'slug' => 'sans-prix',
        'base_price' => null,
    ]);

    $this->get(route('products.index'))
        ->assertSuccessful()
        ->assertInertia(
            fn ($page) => $page->has('products', 1)
                ->where('products.0.name', 'Avec prix')
        );
});

/**
 * The real money risk: a product priced when it went into the basket, then
 * cleared by an admin before the buyer checks out. Without a guard the line
 * would resolve to 0.0 and be billed as free, with the seller's Earning
 * written against that nothing.
 */
test('checkout refuses a basket holding an unpriced product', function () {
    $buyer = User::factory()->create(['role' => 'buyer']);
    $product = publishProduct(['base_price' => null]);

    $cart = Cart::create(['user_id' => $buyer->id]);
    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'quantity' => 1,
    ]);

    $checkout = fn () => app(CheckoutService::class)->process(
        $cart->fresh('items.product'),
        [
            'city_id' => $this->city->id,
            'shipping_address' => '12 rue des Potiers, Fès',
        ],
    );

    expect($checkout)->toThrow(ValidationException::class);

    // Nothing was written on the way out — no half-order, no earning.
    expect($product->store->orders()->count())->toBe(0)
        ->and($cart->fresh()->items()->count())->toBe(1);
});

/**
 * A variant that sets its own price_override is sellable even when the parent
 * product carries no base price, so the guard must key on the *resolved*
 * price rather than on products.base_price.
 */
test('a variant price override prices a line whose product has none', function () {
    $product = publishProduct(['base_price' => null, 'has_variants' => true, 'stock' => 0]);

    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'stock' => 4,
        'price_override' => 900,
        'is_active' => true,
        'position' => 0,
    ]);
    ProductVariantOption::create([
        'product_variant_id' => $variant->id,
        'type' => 'color',
        'name' => 'Couleur',
        'value' => 'Rouge',
    ]);

    $item = new CartItem(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'quantity' => 1]);
    $item->setRelation('product', $product);
    $item->setRelation('variant', $variant);

    expect($item->unitPriceFor(Product::AUDIENCE_INDIVIDUAL))->toBe(900.0);
});

test('an unpriced line resolves to null rather than zero', function () {
    $product = publishProduct(['base_price' => null]);

    $item = new CartItem(['product_id' => $product->id, 'quantity' => 1]);
    $item->setRelation('product', $product);
    $item->setRelation('variant', null);

    expect($item->unitPriceFor(Product::AUDIENCE_INDIVIDUAL))->toBeNull();
});

/**
 * Optional does not mean unvalidated: a discount off a price that does not
 * exist is meaningless, and `lt:base_price` passes silently when base_price
 * is absent — so a sale price drags the base price back to being required.
 */
test('a sale price without a base price is rejected', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), [
            'store_id' => $this->store->id,
            'name' => 'Promo sans base',
            'category_id' => $this->category->id,
            'stock' => 1,
            'sale_price' => 200,
            'status' => 'published',
        ])
        ->assertSessionHasErrors('base_price');
});

test('a price can be filled in later and the product then appears', function () {
    $product = publishProduct(['base_price' => null]);

    expect(Product::storefront()->pluck('id'))->not->toContain($product->id);

    $this->actingAs($this->admin)
        ->post(route('admin.products.update', $product), [
            'name' => $product->name,
            'category_id' => $this->category->id,
            'base_price' => 1200,
            'stock' => 5,
            'status' => 'published',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($product->fresh()->base_price)->toEqual(1200)
        ->and(Product::storefront()->pluck('id'))->toContain($product->id);
});
