<?php

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function orderCategory(string $slug, array $parents = [], int $sortOrder = 0): Category
{
    $category = Category::create([
        'slug' => $slug,
        'name' => ['fr' => $slug, 'ar' => $slug, 'en' => $slug],
        'sort_order' => $sortOrder,
    ]);

    $category->parents()->attach($parents);

    return $category;
}

/** @return array<int, string> */
function rootSlugs(): array
{
    return Category::roots()->ordered()->pluck('slug')->all();
}

/** @return array<int, string> */
function childSlugs(Category $parent): array
{
    return $parent->children()->pluck('slug')->all();
}

function categoryPayload(string $slug): array
{
    return [
        'name' => ['fr' => $slug, 'ar' => $slug, 'en' => $slug],
    ];
}

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'super_admin']);
    $this->actingAs($this->admin);

    $this->poterie = orderCategory('poterie', sortOrder: 1);
    $this->bois = orderCategory('bois', sortOrder: 2);
    $this->tapis = orderCategory('tapis', sortOrder: 3);
});

test('an admin reorders the main categories', function () {
    $this->patch(route('admin.categories.reorder'), [
        'ids' => [$this->tapis->id, $this->poterie->id, $this->bois->id],
    ])->assertRedirect();

    expect(rootSlugs())->toBe(['tapis', 'poterie', 'bois']);
});

test('an admin reorders one parent\'s subcategories', function () {
    $bols = orderCategory('bols', [$this->poterie->id]);
    $tajines = orderCategory('tajines', [$this->poterie->id]);

    $this->patch(route('admin.categories.reorder'), [
        'parent_id' => $this->poterie->id,
        'ids' => [$tajines->id, $bols->id],
    ])->assertRedirect();

    expect(childSlugs($this->poterie))->toBe(['tajines', 'bols']);
});

test('a shared subcategory holds its own place under each parent', function () {
    $shared = orderCategory('art-de-la-table', [$this->poterie->id, $this->bois->id]);
    $bols = orderCategory('bols', [$this->poterie->id]);
    $coffrets = orderCategory('coffrets', [$this->bois->id]);

    $this->patch(route('admin.categories.reorder'), [
        'parent_id' => $this->poterie->id,
        'ids' => [$bols->id, $shared->id],
    ]);
    $this->patch(route('admin.categories.reorder'), [
        'parent_id' => $this->bois->id,
        'ids' => [$shared->id, $coffrets->id],
    ]);

    expect(childSlugs($this->poterie))->toBe(['bols', 'art-de-la-table'])
        ->and(childSlugs($this->bois))->toBe(['art-de-la-table', 'coffrets']);
});

test('the admin page, the header and the storefront all follow the order', function () {
    $bols = orderCategory('bols', [$this->tapis->id]);
    $tajines = orderCategory('tajines', [$this->tapis->id]);

    // Primed first, so this also proves a reorder drops the cached header menu.
    $this->get(route('products.index'))->assertOk();

    $this->patch(route('admin.categories.reorder'), [
        'ids' => [$this->tapis->id, $this->bois->id, $this->poterie->id],
    ]);
    $this->patch(route('admin.categories.reorder'), [
        'parent_id' => $this->tapis->id,
        'ids' => [$tajines->id, $bols->id],
    ]);

    // The admin list is flat, subcategories included, and the page picks the
    // main categories out of it, so only their relative order is promised.
    $this->get(route('admin.categories.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('categories', fn ($rows) => collect($rows)
                ->filter(fn ($row) => $row['parent_ids'] === [])
                ->pluck('slug')
                ->all() === ['tapis', 'bois', 'poterie'])
            ->where('categories', fn ($rows) => collect($rows)
                ->firstWhere('slug', 'tapis')['child_ids'] === [$tajines->id, $bols->id])
        );

    $this->get(route('products.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('categories.0.slug', 'tapis')
            ->where('categories.0.children.0.slug', 'tajines')
            ->where('navCategories.0.slug', 'tapis')
            ->where('navCategories.0.children.0.slug', 'tajines')
        );
});

test('reordering sends a main category to its new first subcategory', function () {
    $bols = orderCategory('bols', [$this->poterie->id]);
    $tajines = orderCategory('tajines', [$this->poterie->id]);

    $this->get(route('products.index', ['category_id' => $this->poterie->id]))
        ->assertRedirect(route('products.index', ['category_id' => $bols->id]));

    $this->patch(route('admin.categories.reorder'), [
        'parent_id' => $this->poterie->id,
        'ids' => [$tajines->id, $bols->id],
    ]);

    $this->get(route('products.index', ['category_id' => $this->poterie->id]))
        ->assertRedirect(route('products.index', ['category_id' => $tajines->id]));
});

test('a stale page cannot drop or invent siblings', function () {
    $child = orderCategory('bols', [$this->poterie->id]);

    // `bois` is missing (added after the page loaded), and `bols` is not a
    // main category at all.
    $this->patch(route('admin.categories.reorder'), [
        'ids' => [$this->tapis->id, $child->id, $this->poterie->id],
    ])->assertRedirect();

    expect(rootSlugs())->toBe(['tapis', 'poterie', 'bois'])
        ->and($child->refresh()->sort_order)->toBe(0);
});

test('a subcategory cannot be moved through another parent\'s list', function () {
    $bols = orderCategory('bols', [$this->poterie->id]);
    $coffrets = orderCategory('coffrets', [$this->bois->id]);

    $this->patch(route('admin.categories.reorder'), [
        'parent_id' => $this->poterie->id,
        'ids' => [$coffrets->id, $bols->id],
    ])->assertRedirect();

    expect(childSlugs($this->poterie))->toBe(['bols'])
        ->and(childSlugs($this->bois))->toBe(['coffrets']);
});

test('a new main category joins the end of the list', function () {
    $this->post(route('admin.categories.store'), categoryPayload('cuir'))->assertRedirect();

    expect(rootSlugs())->toBe(['poterie', 'bois', 'tapis', 'cuir']);
});

test('a new subcategory joins the end of its parent\'s list', function () {
    $first = orderCategory('bols', [$this->poterie->id]);
    $this->poterie->children()->updateExistingPivot($first->id, ['sort_order' => 5]);
    orderCategory('assiettes');

    $this->post(route('admin.categories.store'), [
        ...categoryPayload('tajines'),
        'parent_ids' => [$this->poterie->id],
    ])->assertRedirect();

    expect(childSlugs($this->poterie))->toBe(['bols', 'tajines']);
});

test('editing a subcategory keeps its place under a parent it already had', function () {
    $bols = orderCategory('bols', [$this->poterie->id]);
    $tajines = orderCategory('tajines', [$this->poterie->id]);

    $this->patch(route('admin.categories.reorder'), [
        'parent_id' => $this->poterie->id,
        'ids' => [$tajines->id, $bols->id],
    ]);

    $this->patch(route('admin.categories.update', $bols), [
        ...categoryPayload('bols'),
        'parent_ids' => [$this->poterie->id, $this->bois->id],
    ])->assertRedirect();

    expect(childSlugs($this->poterie))->toBe(['tajines', 'bols'])
        ->and(childSlugs($this->bois))->toBe(['bols']);
});

test('a subcategory that loses its last parent joins the end of the main categories', function () {
    $bols = orderCategory('bols', [$this->poterie->id]);

    $this->patch(route('admin.categories.update', $bols), [
        ...categoryPayload('bols'),
        'parent_ids' => [],
    ])->assertRedirect();

    expect(rootSlugs())->toBe(['poterie', 'bois', 'tapis', 'bols']);
});

test('deleting a main category sends its orphaned subcategories to the end', function () {
    $orphan = orderCategory('bols', [$this->poterie->id]);
    $shared = orderCategory('art-de-la-table', [$this->poterie->id, $this->bois->id]);

    $this->delete(route('admin.categories.destroy', $this->poterie))->assertRedirect();

    expect(rootSlugs())->toBe(['bois', 'tapis', 'bols'])
        ->and(childSlugs($this->bois))->toBe(['art-de-la-table'])
        ->and($orphan->refresh()->sort_order)->toBeGreaterThan(3)
        ->and($shared->refresh()->parents()->count())->toBe(1);
});

test('a reorder forgets the cached header menu for every locale', function () {
    foreach (Category::LOCALES as $locale) {
        Cache::put(Category::NAV_CACHE_KEY.$locale, ['stale'], 3600);
    }

    $this->patch(route('admin.categories.reorder'), [
        'ids' => [$this->bois->id, $this->poterie->id, $this->tapis->id],
    ]);

    foreach (Category::LOCALES as $locale) {
        expect(Cache::has(Category::NAV_CACHE_KEY.$locale))->toBeFalse();
    }
});

test('the list must be a list of distinct ids', function (array $payload) {
    $this->patch(route('admin.categories.reorder'), $payload)
        ->assertSessionHasErrors();
})->with([
    'nothing' => [[]],
    'not a list' => [['ids' => 'poterie']],
    'not ids' => [['ids' => ['poterie']]],
    'repeated' => [['ids' => [1, 1]]],
    'unknown parent' => [['parent_id' => 999999, 'ids' => [1]]],
]);

test('only an admin can reorder', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role]));

    $this->patch(route('admin.categories.reorder'), [
        'ids' => [$this->tapis->id, $this->poterie->id, $this->bois->id],
    ])->assertRoleDenied();

    expect(rootSlugs())->toBe(['poterie', 'bois', 'tapis']);
})->with(['buyer', 'seller']);
