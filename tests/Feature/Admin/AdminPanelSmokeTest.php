<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Store;
use App\Models\User;

/**
 * The admin panel is what ships to the CEO, so every screen they need must at
 * least render. These are deliberately shallow — behaviour lives in the
 * dedicated admin tests.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'super_admin']);

    $this->category = Category::create([
        'slug' => 'poterie',
        'name' => ['fr' => 'Poterie', 'ar' => 'فخار', 'en' => 'Pottery'],
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

test('admin screens render', function (string $routeName) {
    $this->actingAs($this->admin)
        ->get(route($routeName))
        ->assertSuccessful();
})->with([
    'admin.users.index',
    'admin.users.create',
    'admin.products.index',
    'admin.products.create',
    'admin.categories.index',
    'admin.cities.index',
]);

test('the product form is prefilled when opened from a seller', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.products.create', ['store_id' => $this->store->id]))
        ->assertSuccessful()
        ->assertInertia(
            fn ($page) => $page
                ->component('admin/products/create')
                ->where('store.id', $this->store->id)
                ->where('store.store_name', 'Atelier Fès')
        );
});

test('the product form offers every seller when opened directly', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.products.create'))
        ->assertSuccessful()
        ->assertInertia(
            fn ($page) => $page
                ->component('admin/products/create')
                ->where('store', null)
                ->has('stores', 1)
        );
});

test('the user wizard receives cities and categories it can render', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.users.create'))
        ->assertSuccessful()
        ->assertInertia(
            fn ($page) => $page
                ->component('admin/users/create')
                ->has('cities', 1)
                ->has('categories', 1)
                // The shop step renders translated_name in a <select>; without
                // it React would try to render the per-locale object and the
                // whole page would blank out.
                ->where('categories.0.translated_name', 'Poterie')
        );
});

test('categories reach the frontend with a resolved name', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.categories.index'))
        ->assertSuccessful()
        ->assertInertia(
            fn ($page) => $page
                ->component('admin/categories/index')
                // The UI renders translated_name; without it the object would
                // be printed raw.
                ->where('categories.0.translated_name', 'Poterie')
                ->has('locales', 3)
        );
});

test('non admins cannot reach the admin panel', function () {
    foreach (['buyer', 'professional_buyer', 'seller'] as $role) {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertRoleDenied();
    }
});
