<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Notifications\StoreApproved;
use App\Notifications\StoreRejected;
use Illuminate\Support\Facades\Notification;

/**
 * The public "Vérifié par Hirfaty" mark.
 *
 * It is not a second flag: it reflects the shop approval an admin grants from
 * /admin/stores (StoreVerificationService), surfaced to the storefront as
 * `store.is_verified`. These tests pin that mapping down at both ends — the
 * flag follows the approval, and granting it from the seller's admin page
 * emails the artisan just as the stores table does.
 */
beforeEach(function () {
    $this->city = City::create(['name' => 'Safi', 'region' => 'Marrakech-Safi']);
    $this->category = Category::create([
        'name' => ['fr' => 'Poterie', 'ar' => 'فخار', 'en' => 'Pottery'],
        'slug' => 'poterie-test',
    ]);

    $this->seller = User::factory()->create(['role' => 'seller']);

    $this->store = Store::create([
        'user_id' => $this->seller->id,
        'store_name' => 'Atelier Vérifié',
        'slug' => 'atelier-verifie',
        'type' => 'artist',
        'city_id' => $this->city->id,
        'category_id' => $this->category->id,
        'description' => 'Un atelier de poterie à Safi.',
        'status' => 'active',
        'verification_status' => Store::VERIFICATION_APPROVED,
    ]);

    $this->product = Product::create([
        'store_id' => $this->store->id,
        'category_id' => $this->category->id,
        'name' => 'Tajine peint',
        'slug' => 'tajine-peint',
        'description' => 'Fait main.',
        'base_price' => 250,
        'stock' => 5,
        'status' => 'published',
        'visibility' => 'both',
    ]);
});

test('an approved shop is marked verified on its product page', function () {
    $this->get(route('products.show', $this->product->slug))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('product.store.is_verified', true));
});

test('an approved shop is marked verified on its own page', function () {
    $this->get(route('stores.show', $this->store->slug))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('store.is_verified', true));
});

test('a shop still awaiting review is not marked verified', function () {
    // Kept `status` active so the page still resolves — this isolates the
    // verification flag from the separate active/inactive switch.
    $this->store->update(['verification_status' => Store::VERIFICATION_PENDING]);

    $this->get(route('products.show', $this->product->slug))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('product.store.is_verified', false));

    $this->get(route('stores.show', $this->store->slug))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('store.is_verified', false));
});

test('a rejected shop is not marked verified', function () {
    $this->store->update(['verification_status' => Store::VERIFICATION_REJECTED]);

    $this->get(route('products.show', $this->product->slug))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('product.store.is_verified', false));
});

test('verifying a shop from the admin seller page marks it and emails the artisan', function () {
    Notification::fake();

    $admin = User::factory()->create(['role' => 'super_admin']);
    $this->store->update(['verification_status' => Store::VERIFICATION_PENDING]);

    $this->actingAs($admin)
        ->patch(route('admin.stores.approve', $this->store))
        ->assertRedirect();

    expect($this->store->fresh()->isApproved())->toBeTrue();

    Notification::assertSentTo($this->seller, StoreApproved::class);

    // And the badge the admin just granted is what the storefront renders.
    $this->get(route('stores.show', $this->store->slug))
        ->assertInertia(fn ($page) => $page->where('store.is_verified', true));
});

test('withdrawing verification unmarks the shop and emails the artisan', function () {
    Notification::fake();

    $admin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($admin)
        ->patch(route('admin.stores.reject', $this->store), [
            'reason' => 'Les photos ne correspondent pas aux pièces livrées.',
        ])
        ->assertRedirect();

    expect($this->store->fresh()->isApproved())->toBeFalse();

    Notification::assertSentTo($this->seller, StoreRejected::class);
});

test('the admin users table carries the verification status it ticks on', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);

    // The eager load is column-constrained, so a missing column would arrive
    // as `undefined` and the tick would silently never render.
    $this->actingAs($admin)
        ->get(route('admin.users.index', ['role' => 'seller']))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->has(
            'users.data',
            fn ($users) => $users->where('0.store.verification_status', 'approved')->etc()
        ));
});
