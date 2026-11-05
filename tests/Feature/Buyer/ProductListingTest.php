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

// ── The relevance ordering must actually run ─────────────────────────

test('the listing renders on the relevance ordering', function () {
    listed();

    // Regression: the scoring query used MySQL's DATEDIFF/GREATEST, which do
    // not exist on Postgres — the page 500'd for every visitor. Relevance is
    // now the only ordering the listing offers, so this covers every visit.
    $this->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('buyer/products/index')
            ->has('products', 1)
        );
});

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

    // Verified: trade pricing and trade-only visibility are gated on an
    // admin having reviewed the account, not on the role alone.
    $pro = User::factory()->create([
        'role' => 'professional_buyer',
        'professional_verified_at' => now(),
    ]);

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

    // Verified: trade pricing and trade-only visibility are gated on an
    // admin having reviewed the account, not on the role alone.
    $pro = User::factory()->create([
        'role' => 'professional_buyer',
        'professional_verified_at' => now(),
    ]);

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

    // Verified: trade pricing and trade-only visibility are gated on an
    // admin having reviewed the account, not on the role alone.
    $pro = User::factory()->create([
        'role' => 'professional_buyer',
        'professional_verified_at' => now(),
    ]);

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

test('search ignores accents, whichever side carries them', function () {
    listed(['name' => 'Tajine émaillé']);
    listed(['name' => 'Coeur de cèdre']);
    listed(['name' => 'ÉTAGÈRE ANCIENNE']);
    listed(['name' => "Cafe\u{0301} marocain"]);

    foreach ([
        'emaille' => 'Tajine émaillé',
        'EMAILLÉ' => 'Tajine émaillé',
        'Tâjïne' => 'Tajine émaillé',
        'cœur de cedre' => 'Coeur de cèdre',
        'etagere' => 'ÉTAGÈRE ANCIENNE',
        'étagère' => 'ÉTAGÈRE ANCIENNE',
        'cafe' => "Cafe\u{0301} marocain",
    ] as $needle => $name) {
        $this->get(route('products.index', ['search' => $needle]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('products', 1)
                ->where('products.0.name', $name));
    }
});

test('search reaches the description and the shop without their accents', function () {
    listed(['name' => 'Autre chose', 'description' => 'Une pièce décorée à la main']);

    $this->get(route('products.index', ['search' => 'decoree']))
        ->assertInertia(fn (Assert $page) => $page->has('products', 1));

    // The shop in this file is "Atelier Fès".
    $this->get(route('products.index', ['search' => 'atelier fes']))
        ->assertInertia(fn (Assert $page) => $page->has('products', 1));
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

// ── Which hearts start filled ────────────────────────────────────────

test('a guest is offered no wishlist state', function () {
    listed();

    // The wishlist route is behind `role:buyer`, so the card must send a
    // guest to log in rather than fire a request that would be rejected.
    $this->get(route('products.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canWishlist', false)
            ->where('wishlisted', [])
        );
});

test('a buyer sees their own wishlisted pieces on this page marked', function () {
    $wanted = listed(['name' => 'Convoitée']);
    listed(['name' => 'Ignorée']);

    $buyer = User::factory()->create(['role' => 'buyer']);
    $buyer->wishlists()->create(['product_id' => $wanted->id]);

    $this->actingAs($buyer)
        ->get(route('products.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canWishlist', true)
            ->where('wishlisted', [$wanted->id])
        );
});

/**
 * Three subcategories under the test's main category, placed against id order
 * so a passing test proves the admin's order decides, not the id.
 *
 * @return array<int, Category> [first, second, third] in the admin's order
 */
function orderedSubcategories(): array
{
    $third = Category::create(['slug' => 'c', 'name' => ['fr' => 'C', 'ar' => 'C', 'en' => 'C']]);
    $first = Category::create(['slug' => 'a', 'name' => ['fr' => 'A', 'ar' => 'A', 'en' => 'A']]);
    $second = Category::create(['slug' => 'b', 'name' => ['fr' => 'B', 'ar' => 'B', 'en' => 'B']]);

    test()->category->children()->attach([
        $third->id => ['sort_order' => 3],
        $first->id => ['sort_order' => 1],
        $second->id => ['sort_order' => 2],
    ]);

    return [$first, $second, $third];
}

/** @return array<int, string> Product names in the order the listing returned them. */
function listingOrder(array $query): array
{
    return collect(test()->get(route('products.index', $query))->assertOk()->viewData('page')['props']['products'])
        ->pluck('name')
        ->all();
}

test('a main category lists all its subcategories, one after another in the admin order', function () {
    [$a, $b, $c] = orderedSubcategories();

    // Created against the expected order, so insertion order cannot pass this.
    listed(['name' => 'In C', 'category_id' => $c->id]);
    listed(['name' => 'In B', 'category_id' => $b->id]);
    listed(['name' => 'In A', 'category_id' => $a->id]);

    expect(listingOrder(['category_id' => $this->category->id]))->toBe(['In A', 'In B', 'In C']);
});

test('the subcategory order outranks the demand score, which only ranks within a subcategory', function () {
    [$a, $b] = orderedSubcategories();

    listed(['name' => 'B star', 'category_id' => $b->id])->forceFill(['average_rating' => 5])->save();
    listed(['name' => 'A plain', 'category_id' => $a->id]);
    listed(['name' => 'A star', 'category_id' => $a->id])->forceFill(['average_rating' => 5])->save();

    expect(listingOrder(['category_id' => $this->category->id]))->toBe(['A star', 'A plain', 'B star']);
});

test('products filed directly on the main category come after its subcategories', function () {
    [$a] = orderedSubcategories();

    listed(['name' => 'On the parent']);
    listed(['name' => 'In A', 'category_id' => $a->id]);

    expect(listingOrder(['category_id' => $this->category->id]))->toBe(['In A', 'On the parent']);
});

test('the subcategory order carries across pages', function () {
    [$a, $b] = orderedSubcategories();

    foreach (range(1, 12) as $n) {
        listed(['name' => "B {$n}", 'category_id' => $b->id]);
    }
    listed(['name' => 'Only A', 'category_id' => $a->id]);
    listed(['name' => 'Last B', 'category_id' => $b->id]);

    $firstPage = listingOrder(['category_id' => $this->category->id]);
    $secondPage = listingOrder(['category_id' => $this->category->id, 'page' => 2]);

    expect($firstPage)->toHaveCount(12)
        ->and($firstPage[0])->toBe('Only A')
        ->and($secondPage)->toHaveCount(2)
        ->and(array_intersect($firstPage, $secondPage))->toBe([]);
});

test('searching inside a main category keeps the subcategory order', function () {
    [$a, $b] = orderedSubcategories();

    listed(['name' => 'Tajine in B', 'category_id' => $b->id]);
    listed(['name' => 'Tajine in A', 'category_id' => $a->id]);
    listed(['name' => 'Bol in A', 'category_id' => $a->id]);

    expect(listingOrder(['category_id' => $this->category->id, 'search' => 'tajine']))
        ->toBe(['Tajine in A', 'Tajine in B']);
});

test('a subcategory lists only its own products', function () {
    $child = Category::create([
        'slug' => 'tajines',
        'name' => ['fr' => 'Tajines', 'ar' => 'طواجن', 'en' => 'Tagines'],
    ]);
    $child->parents()->attach($this->category);

    listed(['name' => 'Dans la racine']);
    listed(['name' => 'Dans l enfant', 'category_id' => $child->id]);

    $this->get(route('products.index', ['category_id' => $child->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products', 1)
            ->where('products.0.name', 'Dans l enfant')
            ->where('filters.category_id', (string) $child->id)
        );
});

test('a main category with no subcategories still lists its own products', function () {
    listed(['name' => 'Dans la racine']);

    $this->get(route('products.index', ['category_id' => $this->category->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products', 1));
});

test('an unknown category lists nothing', function () {
    listed(['name' => 'Ailleurs']);

    $this->get(route('products.index', ['category_id' => 999999]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products', 0));
});

test('a subcategory shared by two crafts is reachable from either of them', function () {
    $other = Category::create([
        'slug' => 'dinanderie',
        'name' => ['fr' => 'Dinanderie', 'ar' => 'نحاسيات', 'en' => 'Metalwork'],
    ]);
    $child = Category::create([
        'slug' => 'art-de-la-table',
        'name' => ['fr' => 'Art de la table', 'ar' => 'فن المائدة', 'en' => 'Tableware'],
    ]);
    $child->parents()->attach([$this->category->id, $other->id]);

    listed(['name' => 'Partagé', 'category_id' => $child->id]);

    $this->get(route('products.index', ['category_id' => $other->id]))
        ->assertInertia(fn (Assert $page) => $page->has('products', 1));

    $this->get(route('products.index', ['category_id' => $this->category->id]))
        ->assertInertia(fn (Assert $page) => $page->has('products', 1));
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
