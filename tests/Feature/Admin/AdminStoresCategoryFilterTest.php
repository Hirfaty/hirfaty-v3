<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Store;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The workshop count on the admin categories page opens this list filtered to
 * that category, so the two must agree.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'super_admin']);

    $this->pottery = Category::create(['slug' => 'poterie', 'name' => ['fr' => 'Poterie', 'ar' => 'فخار', 'en' => 'Pottery']]);
    $this->leather = Category::create(['slug' => 'cuir', 'name' => ['fr' => 'Cuir', 'ar' => 'جلد', 'en' => 'Leather']]);

    $shop = fn (string $name, Category $category, string $verification) => Store::create([
        'user_id' => User::factory()->create(['role' => 'seller'])->id,
        'store_name' => $name,
        'slug' => str($name)->slug(),
        'category_id' => $category->id,
        'city_id' => City::firstOrCreate(['name' => 'Fès'])->id,
        'verification_status' => $verification,
    ]);

    $shop('Atelier Fès', $this->pottery, Store::VERIFICATION_APPROVED);
    $shop('Atelier Safi', $this->pottery, Store::VERIFICATION_PENDING);
    $shop('Tannerie', $this->leather, Store::VERIFICATION_APPROVED);
});

test('the list shows only the workshops of the category, as many as the categories page counts', function () {
    $counted = Category::withCount('stores')->find($this->pottery->id)->stores_count;

    $this->actingAs($this->admin)
        ->get(route('admin.stores.index', ['category_id' => $this->pottery->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/stores/index')
            ->where('filters.category_id', $this->pottery->id)
            ->where('stores.total', $counted)
            ->where('stores.data', fn ($stores) => collect($stores)->every(fn ($store) => $store['category'] !== 'Cuir')));
});

test('the status tabs count within the category', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.stores.index', ['category_id' => $this->pottery->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('statusCounts.approved', 1)
            ->where('statusCounts.pending', 1));
});

test('no category lists every workshop, and a nonsense one is ignored', function (array $query) {
    $this->actingAs($this->admin)
        ->get(route('admin.stores.index', $query))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('stores.total', 3));
})->with([
    'none' => [[]],
    'not a number' => [['category_id' => 'abc']],
]);

test('the filter names its categories in French whatever the session language', function () {
    $this->actingAs($this->admin)
        ->withSession(['locale' => 'en'])
        ->get(route('admin.stores.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('categories', [
                ['id' => $this->leather->id, 'name' => 'Cuir'],
                ['id' => $this->pottery->id, 'name' => 'Poterie'],
            ]));
});
