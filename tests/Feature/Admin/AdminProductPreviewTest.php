<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Product;
use App\Models\ProductView;
use App\Models\Store;
use App\Models\User;

/**
 * The admin preview renders the real public product page for products the
 * storefront refuses to show — the whole point is checking a listing before
 * it goes live.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'super_admin']);

    $this->category = Category::create([
        'slug' => 'poterie',
        'name' => ['fr' => 'Poterie', 'ar' => 'فخار', 'en' => 'Pottery'],
    ]);

    $this->seller = User::factory()->create(['role' => 'seller']);
    $this->store = Store::create([
        'user_id' => $this->seller->id,
        'store_name' => 'Atelier Fès',
        'slug' => 'atelier-fes',
        'category_id' => $this->category->id,
        'city_id' => City::create(['name' => 'Fès'])->id,
        'status' => 'inactive',
    ]);

    // Everything that keeps a product off the storefront at once.
    $this->product = Product::create([
        'store_id' => $this->store->id,
        'category_id' => $this->category->id,
        'name' => 'Vase en terre',
        'slug' => 'vase-en-terre',
        'base_price' => null,
        'stock' => 5,
        'status' => 'pending_review',
        'visibility' => 'professional',
    ]);
});

test('an unpublished, unpriced product is previewed on the public product page', function () {
    // The public URL refuses it…
    $this->get(route('products.show', $this->product->slug))->assertNotFound();

    // …the preview does not.
    $this->actingAs($this->admin)
        ->get(route('admin.products.preview', $this->product))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('buyer/products/show')
            ->where('product.id', $this->product->id)
            ->where('product.base_price', null)
            ->where('inCart', false)
            ->where('inWishlist', false)
            ->where('preview.back_url', route('admin.products.show', $this->product))
            ->where('preview.issues', fn ($issues) => count($issues) === 4
                && str_contains($issues[0], 'En attente de validation')
                && str_contains($issues[1], 'Aucun prix')
                && str_contains($issues[2], 'boutique n’est pas active')
                && str_contains($issues[3], 'professionnels'))
        );
});

test('a live product previews with no issues', function () {
    $this->store->update(['status' => 'active']);
    $this->product->update(['status' => 'published', 'base_price' => 200, 'visibility' => 'both']);

    $this->actingAs($this->admin)
        ->get(route('admin.products.preview', $this->product))
        ->assertInertia(fn ($page) => $page->where('preview.issues', []));
});

test('a blocked seller is named as the reason', function () {
    $this->store->update(['status' => 'active']);
    $this->seller->forceFill(['is_banned' => true])->save();
    $this->product->update(['status' => 'published', 'base_price' => 200, 'visibility' => 'both']);

    $this->actingAs($this->admin)
        ->get(route('admin.products.preview', $this->product))
        ->assertInertia(fn ($page) => $page->where('preview.issues', fn ($issues) => count($issues) === 1
            && str_contains($issues[0], 'vendeur est bloqué')));
});

test('previewing does not count as a view', function () {
    $this->actingAs($this->admin)->get(route('admin.products.preview', $this->product));

    expect(ProductView::count())->toBe(0);
});

test('only admins can preview', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role]))
        ->get(route('admin.products.preview', $this->product))
        ->assertRoleDenied();
})->with(['buyer', 'seller']);

test('the storefront page is unchanged and carries no preview', function () {
    $this->store->update(['status' => 'active']);
    $this->product->update(['status' => 'published', 'base_price' => 200, 'visibility' => 'both']);

    $this->get(route('products.show', $this->product->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('buyer/products/show')
            ->where('product.id', $this->product->id)
            ->missing('preview')
            ->has('moreFromStore')
            ->has('relatedProducts')
            ->has('reviewsPagination'));
});
