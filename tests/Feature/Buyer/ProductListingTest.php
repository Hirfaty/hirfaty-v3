<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->category = Category::create([
        'slug' => 'poterie',
        'name' => ['fr' => 'Poterie', 'ar' => 'فخار', 'en' => 'Pottery'],
    ]);

    $seller = User::factory()->create(['role' => 'seller']);
    $this->store = Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Atelier Fès',
        'slug' => 'atelier-fes',
        'category_id' => $this->category->id,
        'city_id' => City::create(['name' => 'Fès'])->id,
    ]);
});

function listed(array $attributes = []): Product
{
    static $n = 0;
    $n++;

    return Product::create(array_merge([
        'store_id' => test()->store->id,
        'category_id' => test()->category->id,
        'name' => "Piece {$n}",
        'slug' => Str::slug("piece-{$n}").'-'.Str::random(5),
        'base_price' => 200,
        'stock' => 5,
        'status' => 'published',
        'visibility' => 'both',
    ], $attributes));
}

// ── The default sort must actually run ───────────────────────────────

test('the listing renders on the default relevance sort', function () {
    listed();

    // Regression: the scoring query used MySQL's DATEDIFF/GREATEST, which do
    // not exist on Postgres — the page 500'd for every visitor who did not
    // pick an explicit sort.
    $this->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('buyer/products/index')
            ->has('products', 1)
        );
});

test('every sort option returns a successful response', function (string $sort) {
    listed();

    $this->get(route('products.index', ['sort' => $sort]))->assertOk();
})->with(['score', 'latest', 'price_asc', 'price_desc', 'rating']);

// ── Visibility is scoped to the viewer's audience ────────────────────

test('guests never see trade-only products', function () {
    listed(['name' => 'Public piece', 'visibility' => 'both']);
    listed(['name' => 'Trade only', 'visibility' => 'professional']);

    $this->get(route('products.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('products', 1)
            ->where('products.0.name', 'Public piece')
            ->where('audience', 'individual')
        );
});

test('professional buyers see trade products but not consumer-only ones', function () {
    listed(['name' => 'Trade only', 'visibility' => 'professional']);
    listed(['name' => 'Consumer only', 'visibility' => 'individual']);

    $pro = User::factory()->create(['role' => 'professional_buyer']);

    $this->actingAs($pro)
        ->get(route('products.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('products', 1)
            ->where('products.0.name', 'Trade only')
            ->where('audience', 'professional')
        );
});

// ── Wholesale figures are not leaked to consumers ────────────────────

test('the trade price is withheld from guests and ordinary buyers', function () {
    listed(['professional_price' => 120, 'min_order_qty_professional' => 5]);

    $assertHidden = fn (Assert $page) => $page
        ->missing('products.0.professional_price')
        ->missing('products.0.professional_effective_price');

    $this->get(route('products.index'))->assertInertia($assertHidden);

    $buyer = User::factory()->create(['role' => 'buyer']);
    $this->actingAs($buyer)->get(route('products.index'))->assertInertia($assertHidden);
});

test('a professional buyer receives the trade price and minimum quantity', function () {
    listed(['base_price' => 200, 'professional_price' => 120, 'min_order_qty_professional' => 5]);

    $pro = User::factory()->create(['role' => 'professional_buyer']);

    $this->actingAs($pro)
        ->get(route('products.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.0.professional_effective_price', 120)
            ->where('products.0.min_order_qty_professional', 5)
        );
});

test('a product with no trade rate reports none rather than echoing retail', function () {
    // priceFor() deliberately falls back to retail, which would otherwise
    // render as a "pro price" identical to the public one.
    listed(['base_price' => 200, 'professional_price' => null]);

    $pro = User::factory()->create(['role' => 'professional_buyer']);

    $this->actingAs($pro)
        ->get(route('products.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.0.professional_effective_price', null)
        );
});

// ── Search and filters ───────────────────────────────────────────────

test('search ignores case', function () {
    // Regression: Postgres LIKE is case-sensitive, so a lowercase query
    // matched nothing against a capitalised product name.
    listed(['name' => 'Tajine émaillé']);

    foreach (['tajine', 'TAJINE', 'TaJiNe'] as $needle) {
        $this->get(route('products.index', ['search' => $needle]))
            ->assertInertia(fn (Assert $page) => $page->has('products', 1));
    }
});

test('search matches the product name, its description and its shop', function () {
    listed(['name' => 'Tajine émaillé']);
    listed(['name' => 'Autre chose', 'description' => 'Un tajine décrit ici']);
    listed(['name' => 'Sans rapport']);

    $this->get(route('products.index', ['search' => 'tajine']))
        ->assertInertia(fn (Assert $page) => $page->has('products', 2));

    $this->get(route('products.index', ['search' => 'Atelier Fès']))
        ->assertInertia(fn (Assert $page) => $page->has('products', 3));
});

test('price sorting uses the discounted price the card displays', function () {
    listed(['name' => 'Plein tarif', 'base_price' => 140, 'sale_price' => null]);
    listed(['name' => 'Soldé', 'base_price' => 300, 'sale_price' => 90]);

    // Sorted on base_price the discounted piece (300) would land last, even
    // though the shopper sees 90 on its card.
    $this->get(route('products.index', ['sort' => 'price_asc']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.0.name', 'Soldé')
            ->where('products.1.name', 'Plein tarif')
        );
});

test('the in-stock filter hides sold-out pieces', function () {
    listed(['name' => 'Disponible', 'stock' => 3]);
    listed(['name' => 'Épuisé', 'stock' => 0]);

    $this->get(route('products.index', ['in_stock' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('products', 1)
            ->where('products.0.name', 'Disponible')
        );
});

test('choosing a root category also matches its child categories', function () {
    $child = Category::create([
        'slug' => 'tajines',
        'parent_id' => $this->category->id,
        'name' => ['fr' => 'Tajines', 'ar' => 'طواجن', 'en' => 'Tagines'],
    ]);

    listed(['name' => 'Dans la racine']);
    listed(['name' => 'Dans l enfant', 'category_id' => $child->id]);

    $this->get(route('products.index', ['category_id' => $this->category->id]))
        ->assertInertia(fn (Assert $page) => $page->has('products', 2));
});

test('only published products are listed', function () {
    listed(['name' => 'Publié']);
    listed(['name' => 'Brouillon', 'status' => 'draft']);
    listed(['name' => 'En attente', 'status' => 'pending_review']);

    $this->get(route('products.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('products', 1)
            ->where('products.0.name', 'Publié')
        );
});

// ── Payload shape the page depends on ────────────────────────────────

test('the card payload carries a resolved category and a safe city', function () {
    listed();

    $this->get(route('products.index'))
        ->assertInertia(fn (Assert $page) => $page
            // `name` is a per-locale map on the model; the grid needs the
            // resolved string, never the raw map.
            ->where('products.0.category.translated_name', 'Poterie')
            ->where('products.0.store.city_name', 'Fès')
            ->has('products.0.average_rating')
        );
});

test('a shop without a city does not break the listing', function () {
    $this->store->update(['city_id' => null]);
    listed();

    $this->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.0.store.city_name', null)
        );
});

test('pagination reports the full total and flags further pages', function () {
    foreach (range(1, 14) as $ignored) {
        listed();
    }

    $this->get(route('products.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('products', 12)
            ->where('pagination.total', 14)
            ->where('pagination.has_more', true)
        );

    $this->get(route('products.index', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('products', 2)
            ->where('pagination.has_more', false)
        );
});
