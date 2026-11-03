<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Str;

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

function suggestable(array $attributes = []): Product
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

// ── The panel's contract with the header ─────────────────────────────

test('a guest gets live suggestions for a term', function () {
    suggestable(['name' => 'Tajine émaillé']);
    suggestable(['name' => 'Sans rapport']);

    $this->getJson(route('search.suggestions', ['q' => 'tajine']))
        ->assertOk()
        ->assertJsonCount(1, 'products')
        ->assertJsonPath('products.0.name', 'Tajine émaillé')
        ->assertJsonPath('products.0.store_name', 'Atelier Fès')
        ->assertJsonPath('products.0.category_name', 'Poterie')
        ->assertJsonPath('total', 1);
});

test('the panel is given more than a handful of rows to show', function () {
    // The header renders every row it is handed; the design calls for at
    // least five under the field, so the service must not cap below that.
    foreach (range(1, 9) as $ignored) {
        suggestable(['name' => 'Tajine '.$ignored]);
    }

    $this->getJson(route('search.suggestions', ['q' => 'tajine']))
        ->assertJsonCount(6, 'products')
        // The count is of the whole match, not the preview — it drives the
        // "see all N results" hand-off to the listing.
        ->assertJsonPath('total', 9);
});

test('a term shorter than two characters queries nothing', function () {
    suggestable(['name' => 'Tajine émaillé']);

    $this->getJson(route('search.suggestions', ['q' => 't']))
        ->assertOk()
        ->assertJsonCount(0, 'products')
        ->assertJsonPath('total', 0);

    $this->getJson(route('search.suggestions'))
        ->assertOk()
        ->assertJsonCount(0, 'products');
});

test('suggestions ignore case and match the shop name too', function () {
    suggestable(['name' => 'Tajine émaillé']);
    suggestable(['name' => 'Autre chose', 'description' => 'Un tajine décrit ici']);

    $this->getJson(route('search.suggestions', ['q' => 'TAJINE']))
        ->assertJsonCount(2, 'products');

    $this->getJson(route('search.suggestions', ['q' => 'atelier']))
        ->assertJsonCount(2, 'products');
});

test('suggestions ignore accents and still rank a name match first', function () {
    suggestable(['name' => 'Autre chose', 'description' => 'Un tajine émaillé décrit ici']);
    suggestable(['name' => 'Tajine émaillé']);

    $this->getJson(route('search.suggestions', ['q' => 'EMAILLE']))
        ->assertJsonCount(2, 'products')
        ->assertJsonPath('products.0.name', 'Tajine émaillé')
        ->assertJsonPath('total', 2);
});

test('a name match is listed above a description-only match', function () {
    // Someone typing "tajine" wants the tajines, not every blurb that
    // mentions one.
    suggestable(['name' => 'Autre chose', 'description' => 'Un tajine décrit ici']);
    suggestable(['name' => 'Tajine émaillé']);

    $this->getJson(route('search.suggestions', ['q' => 'tajine']))
        ->assertJsonPath('products.0.name', 'Tajine émaillé');
});

// ── The same gates the listing applies ───────────────────────────────

test('the search box never leaks trade-only or unpublished pieces', function () {
    suggestable(['name' => 'Tajine public', 'visibility' => 'both']);
    suggestable(['name' => 'Tajine de gros', 'visibility' => 'professional']);
    suggestable(['name' => 'Tajine brouillon', 'status' => 'draft']);

    $this->getJson(route('search.suggestions', ['q' => 'tajine']))
        ->assertJsonCount(1, 'products')
        ->assertJsonPath('products.0.name', 'Tajine public')
        ->assertJsonPath('total', 1);
});

test('a professional buyer is quoted the trade rate in the panel', function () {
    suggestable([
        'name' => 'Tajine émaillé',
        'base_price' => 200,
        'professional_price' => 120,
    ]);

    $pro = User::factory()->create([
        'role' => 'professional_buyer',
        'professional_verified_at' => now(),
    ]);

    // Guest first: actingAs() persists for the rest of the test, so the
    // retail assertion has to run before the trade one.
    $this->getJson(route('search.suggestions', ['q' => 'tajine']))
        ->assertJsonPath('products.0.price', 200);

    $this->actingAs($pro)
        ->getJson(route('search.suggestions', ['q' => 'tajine']))
        ->assertJsonPath('products.0.price', 120);
});
