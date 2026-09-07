<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Support\Str;

/**
 * The `products.wishlist` route pointed at a controller method that was never
 * written, so the heart on the product page threw on every click and no
 * wishlist could ever be populated. These pin the behaviour it should have
 * had all along.
 */
beforeEach(function () {
    $this->buyer = User::factory()->create(['role' => 'buyer']);

    $category = Category::create([
        'slug' => 'poterie',
        'name' => ['fr' => 'Poterie', 'ar' => 'فخار', 'en' => 'Pottery'],
    ]);
    $seller = User::factory()->create(['role' => 'seller']);
    $this->store = Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Atelier Safi',
        'slug' => 'atelier-safi',
        'category_id' => $category->id,
        'city_id' => City::create(['name' => 'Safi'])->id,
    ]);

    $this->product = Product::create([
        'store_id' => $this->store->id,
        'category_id' => $category->id,
        'name' => 'Tajine émaillé',
        'slug' => 'tajine-'.Str::random(5),
        'base_price' => 320,
        'stock' => 5,
        'visibility' => 'both',
        'status' => 'published',
    ]);
});

test('a buyer can add a product to their wishlist', function () {
    $this->actingAs($this->buyer)
        ->post(route('products.wishlist', $this->product))
        ->assertRedirect();

    expect(Wishlist::where('user_id', $this->buyer->id)
        ->where('product_id', $this->product->id)
        ->exists())->toBeTrue();
});

/**
 * The button is a toggle, and wishlists carries a (user_id, product_id)
 * unique index — a second click that tried to insert again would trip it.
 */
test('clicking again removes it rather than failing on the unique index', function () {
    $this->actingAs($this->buyer)->post(route('products.wishlist', $this->product));
    $this->actingAs($this->buyer)
        ->post(route('products.wishlist', $this->product))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Wishlist::where('user_id', $this->buyer->id)->count())->toBe(0);
});

test('the wishlist page lists saved pieces', function () {
    $this->actingAs($this->buyer)->post(route('products.wishlist', $this->product));

    $this->actingAs($this->buyer)
        ->get(route('buyer.wishlist'))
        ->assertSuccessful()
        ->assertInertia(
            fn ($page) => $page->component('buyer/wishlists/index')
                ->has('products', 1)
                ->where('products.0.name', 'Tajine émaillé')
        );
});

/**
 * A piece can be wishlisted and later archived, or have its price cleared by
 * an admin. Showing a card that can't be opened or bought would be worse than
 * dropping it — and the row stays, so it comes back if the product does.
 */
test('a piece that has left the storefront is hidden but not forgotten', function () {
    $this->actingAs($this->buyer)->post(route('products.wishlist', $this->product));

    $this->product->update(['base_price' => null]);

    $this->actingAs($this->buyer)
        ->get(route('buyer.wishlist'))
        ->assertInertia(fn ($page) => $page->has('products', 0));

    // The wishlist row survives, so re-pricing the product brings it back.
    expect(Wishlist::where('user_id', $this->buyer->id)->count())->toBe(1);

    $this->product->update(['base_price' => 320]);

    $this->actingAs($this->buyer)
        ->get(route('buyer.wishlist'))
        ->assertInertia(fn ($page) => $page->has('products', 1));
});

test('one buyer never sees another buyer\'s wishlist', function () {
    $other = User::factory()->create(['role' => 'buyer']);
    $this->actingAs($other)->post(route('products.wishlist', $this->product));

    $this->actingAs($this->buyer)
        ->get(route('buyer.wishlist'))
        ->assertInertia(fn ($page) => $page->has('products', 0));
});

test('a guest is bounced to login rather than wishlisting anonymously', function () {
    $this->post(route('products.wishlist', $this->product))
        ->assertRedirect(route('login'));

    expect(Wishlist::count())->toBe(0);
});
