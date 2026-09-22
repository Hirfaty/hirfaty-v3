<?php

/*
 * The shared `sellerStore` prop (HandleInertiaRequests::getSellerStoreStatus)
 * gained `slug` and `is_public` so SellerSidebar can link straight to the
 * shop's public page. This covers the two states that matter: the normal
 * approved shop (link should work) and a shop an admin has switched off
 * (link must not point at a 404).
 */

use App\Models\Category;
use App\Models\City;
use App\Models\Store;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function storeLinkSeller(array $attributes = []): User
{
    $seller = User::factory()->create(['role' => 'seller']);

    $category = Category::firstOrCreate(
        ['slug' => 'store-link-craft'],
        ['name' => ['fr' => 'Métier', 'ar' => 'حرفة', 'en' => 'Craft']],
    );
    $city = City::firstOrCreate(['name' => 'Storelinkville']);

    Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Atelier '.uniqid(),
        'slug' => 'atelier-link-'.uniqid(),
        'type' => 'artist',
        'category_id' => $category->id,
        'city_id' => $city->id,
        'description' => 'Une présentation suffisamment longue.',
        'verification_status' => Store::VERIFICATION_APPROVED,
        ...$attributes,
    ]);

    return $seller->refresh();
}

test('an active shop shares its slug and is marked public', function () {
    $seller = storeLinkSeller();

    $this->actingAs($seller)
        ->get(route('seller.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sellerStore.slug', $seller->store->slug)
            ->where('sellerStore.is_public', true)
        );

    // And the slug it shared actually resolves on the public page.
    $this->get(route('stores.show', $seller->store->slug))->assertOk();
});

test('a shop an admin switched off is not marked public', function () {
    $seller = storeLinkSeller(['status' => 'inactive']);

    $this->actingAs($seller)
        ->get(route('seller.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sellerStore.is_public', false)
        );

    // Confirms the flag is telling the truth, not just present.
    $this->get(route('stores.show', $seller->store->slug))->assertNotFound();
});
