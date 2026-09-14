<?php

use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * What a freshly seeded taxonomy is supposed to look like.
 *
 * Two of these categories were renamed after launch, and the slug is the URL key
 * so the rename deliberately did *not* change it — `ExploreByCategories` matches
 * its homepage tiles on slug fragments, and `Str::slug` of the new French names
 * would not contain them. That is the pairing this file pins: if someone later
 * "tidies up" the seeder by letting the slug follow the name again, a fresh
 * database silently stops matching every existing one.
 */
beforeEach(function () {
    (new CategorySeeder)->run();
});

test('the renamed categories keep the slugs their tiles match on', function () {
    $lighting = Category::firstWhere('slug', 'lanterne-marocaine');
    $tableware = Category::firstWhere('slug', 'textiles-tissage');

    expect($lighting)->not->toBeNull()
        ->and($lighting->nameFor('fr'))->toBe('Luminaires & dinanderie d\'art')
        ->and($lighting->nameFor('en'))->toBe('Lighting & Art Metalwork')
        ->and($tableware)->not->toBeNull()
        ->and($tableware->nameFor('fr'))->toBe('Art de la table & célébrations')
        ->and($tableware->nameFor('en'))->toBe('Tableware & Celebrations');
});

test('the retired jewellery category is not seeded', function () {
    expect(Category::firstWhere('slug', 'bijoux-accessoires'))->toBeNull();
});

test('every seeded category is named in all three locales', function () {
    foreach (Category::all() as $category) {
        foreach (Category::LOCALES as $locale) {
            expect($category->name[$locale] ?? null)
                ->not->toBeEmpty("'{$category->slug}' has no {$locale} name");
        }
    }
});

test('seeding twice does not duplicate a category', function () {
    $before = Category::count();

    (new CategorySeeder)->run();

    expect(Category::count())->toBe($before);
});
