<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * A shop's public page.
 *
 * The storefront had none until now: both the admin panel and the product page
 * linked to /seller/{slug}, which is the artisan's own back office behind
 * `role:seller` — so following either link 403'd for the shoppers it was for.
 */
beforeEach(function () {
    $this->category = Category::create([
        'slug' => 'poterie',
        'name' => ['fr' => 'Poterie', 'ar' => 'فخار', 'en' => 'Pottery'],
    ]);
    $this->city = City::create(['name' => 'Safi']);

    $this->store = Store::create([
        'user_id' => User::factory()->create(['role' => 'seller'])->id,
        'store_name' => 'Atelier Safi',
        'slug' => 'atelier-safi',
        'description' => 'Poterie tournée à la main.',
        'type' => 'cooperative',
        'category_id' => $this->category->id,
        'city_id' => $this->city->id,
        'status' => 'active',
    ]);
});

function storeProduct(Store $store, array $attributes = []): Product
{
    $name = $attributes['name'] ?? 'Vase';

    return Product::create(array_merge([
        'store_id' => $store->id,
        'name' => $name,
        'slug' => Str::slug($name).'-'.Str::random(6),
        'base_price' => 120,
        'stock' => 4,
        'status' => 'published',
        'visibility' => 'both',
    ], $attributes));
}

test('a guest can open a shop page', function () {
    storeProduct($this->store, ['name' => 'Tajine']);

    $this->get(route('stores.show', $this->store->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('buyer/stores/show')
            ->where('store.store_name', 'Atelier Safi')
            ->where('store.city_name', 'Safi')
            ->where('store.type', 'cooperative')
            ->has('products', 1)
            ->where('pagination.total', 1)
        );
});

test('a shop page only lists that shop\'s sellable products', function () {
    storeProduct($this->store, ['name' => 'Publié']);
    storeProduct($this->store, ['name' => 'Brouillon', 'status' => 'draft']);
    // Published but never priced — scopeStorefront keeps it off the shopfront.
    storeProduct($this->store, ['name' => 'Sans prix', 'base_price' => null]);

    // Another shop's piece must not leak onto this page.
    $other = Store::create([
        'user_id' => User::factory()->create(['role' => 'seller'])->id,
        'store_name' => 'Autre',
        'slug' => 'autre-atelier',
        'category_id' => $this->category->id,
        'city_id' => $this->city->id,
        'status' => 'active',
    ]);
    storeProduct($other, ['name' => 'Ailleurs']);

    $this->get(route('stores.show', $this->store->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->has('products', 1)
            ->where('products.0.name', 'Publié')
        );
});

/**
 * The gate the listing and the product page already apply. Without it the shop
 * page would be the one place a wholesale-only piece leaked to consumers.
 */
test('a consumer does not see trade-only pieces on a shop page', function () {
    storeProduct($this->store, ['name' => 'Grand public']);
    storeProduct($this->store, ['name' => 'Pro only', 'visibility' => 'professional']);

    $this->actingAs(User::factory()->create(['role' => 'buyer']))
        ->get(route('stores.show', $this->store->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->has('products', 1)
            ->where('products.0.name', 'Grand public')
        );
});

test('a verified trade buyer does see them', function () {
    storeProduct($this->store, ['name' => 'Grand public']);
    storeProduct($this->store, ['name' => 'Pro only', 'visibility' => 'professional']);

    $this->actingAs(User::factory()->verifiedProfessional()->create())
        ->get(route('stores.show', $this->store->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->has('products', 2)
            ->where('audience', 'professional')
        );
});

/**
 * A shop an admin switched off should read as gone rather than as an eerie
 * page with no products.
 */
test('an inactive shop is not reachable', function () {
    $this->store->update(['status' => 'inactive']);

    $this->get(route('stores.show', $this->store->slug))->assertNotFound();
});

test('an unknown slug 404s', function () {
    $this->get(route('stores.show', 'no-such-shop'))->assertNotFound();
});

/**
 * The whole point of the route: it must be reachable without the
 * `role:seller` middleware that made the old /seller/{slug} link 403.
 */
test('the shop page is open to every role, not just sellers', function () {
    storeProduct($this->store);

    foreach (['buyer', 'professional_buyer', 'seller', 'super_admin'] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]))
            ->get(route('stores.show', $this->store->slug))
            ->assertOk();
    }
});
