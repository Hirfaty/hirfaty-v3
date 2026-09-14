<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The "retire Bijoux & Accessoires" half of
 * `2026_09_14_140000_rename_and_retire_categories`, run against the shapes real
 * databases actually turned out to have.
 *
 * `RefreshDatabase` has already run every migration by the time a test starts,
 * so these re-create the relevant rows and invoke the migration class directly
 * rather than trying to replay it.
 */
function retireMigration(): object
{
    return require database_path('migrations/2026_09_14_140000_rename_and_retire_categories.php');
}

function category(string $slug, string $fr): Category
{
    return Category::create([
        'slug' => $slug,
        'name' => ['fr' => $fr, 'ar' => $fr, 'en' => $fr],
    ]);
}

function productIn(Category $category): Product
{
    $seller = User::factory()->create(['role' => 'seller']);
    $store = Store::create([
        'user_id' => $seller->id,
        'category_id' => $category->id,
        'store_name' => 'Atelier '.$category->id,
        'slug' => 'atelier-'.$category->id.'-'.uniqid(),
    ]);

    return Product::create([
        'store_id' => $store->id,
        'category_id' => $category->id,
        'name' => 'Piece',
        'slug' => 'piece-'.uniqid(),
        'base_price' => 100,
        'stock' => 1,
        'status' => 'published',
    ]);
}

beforeEach(function () {
    // The migration has already run against the empty schema; start from a
    // clean taxonomy so each case controls exactly what exists.
    Product::query()->delete();
    Store::query()->delete();
    Category::query()->delete();
});

/**
 * Staging's shape, and the one that failed the deploy: the jewellery category
 * is empty, and the destination category does not exist at all because an admin
 * had deleted it. There is nothing to move, so the missing destination is
 * irrelevant and the retirement must simply go ahead.
 */
test('an empty jewellery category is retired even with no destination present', function () {
    category('bijoux-accessoires', 'Bijoux & Accessoires');
    category('lanterne-marocaine', 'Lanterne marocaine');

    retireMigration()->up();

    expect(Category::firstWhere('slug', 'bijoux-accessoires'))->toBeNull();
});

/**
 * The local shape: products are attached, the destination exists, so they move
 * rather than being orphaned.
 */
test('attached products are moved to the destination rather than orphaned', function () {
    $bijoux = category('bijoux-accessoires', 'Bijoux & Accessoires');
    $maroquinerie = category('maroquinerie', 'Maroquinerie');
    $product = productIn($bijoux);

    retireMigration()->up();

    expect(Category::firstWhere('slug', 'bijoux-accessoires'))->toBeNull()
        ->and($product->refresh()->category_id)->toBe($maroquinerie->id);

    expect(Product::whereNull('category_id')->count())->toBe(0);
});

/**
 * The case the guard exists for. Products attached AND no destination is the
 * one combination where carrying on would silently null their `category_id`,
 * so it must stop the deploy instead.
 */
test('it refuses to orphan products when the destination is missing', function () {
    $bijoux = category('bijoux-accessoires', 'Bijoux & Accessoires');
    $product = productIn($bijoux);

    expect(fn () => retireMigration()->up())
        ->toThrow(RuntimeException::class);

    // Nothing touched: the product still belongs where it did.
    expect($product->refresh()->category_id)->toBe($bijoux->id);
});

test('the failure message names the slugs that do exist', function () {
    $bijoux = category('bijoux-accessoires', 'Bijoux & Accessoires');
    category('peinture-art', 'Peinture & Art');
    productIn($bijoux);

    expect(fn () => retireMigration()->up())
        ->toThrow(RuntimeException::class, 'peinture-art');
});

test('it is a no-op when the jewellery category was already removed', function () {
    category('lanterne-marocaine', 'Lanterne marocaine');

    retireMigration()->up();

    expect(Category::count())->toBe(1);
});

/**
 * The renames must apply regardless of what happens to the jewellery category —
 * on staging both of these carry live products.
 */
test('the renames apply and keep their slugs', function () {
    category('lanterne-marocaine', 'Lanterne marocaine');
    category('textiles-tissage', 'Textiles & Tissage');

    retireMigration()->up();

    expect(Category::firstWhere('slug', 'lanterne-marocaine')->nameFor('fr'))
        ->toBe('Luminaires & dinanderie d\'art')
        ->and(Category::firstWhere('slug', 'textiles-tissage')->nameFor('fr'))
        ->toBe('Art de la table & célébrations');
});

/**
 * Re-running a deploy must not fail on a taxonomy the migration already
 * processed — the retry loop in the deploy script does exactly this.
 */
test('running it twice is safe', function () {
    $bijoux = category('bijoux-accessoires', 'Bijoux & Accessoires');
    $maroquinerie = category('maroquinerie', 'Maroquinerie');
    $product = productIn($bijoux);

    retireMigration()->up();
    retireMigration()->up();

    expect($product->refresh()->category_id)->toBe($maroquinerie->id)
        ->and(Category::firstWhere('slug', 'bijoux-accessoires'))->toBeNull();
});
