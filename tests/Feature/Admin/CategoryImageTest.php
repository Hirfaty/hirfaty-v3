<?php

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * A real one-pixel PNG: the dimension rule reads pixel data via
 * getimagesize(), and UploadedFile::fake()->image() needs GD, which isn't
 * installed locally (see AdminCreatesUsersTest::fakeImageUpload).
 */
function categoryImageUpload(string $name): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
    ));
}

beforeEach(function () {
    Storage::fake(config('filesystems.default'));
    $this->admin = User::factory()->create(['role' => 'super_admin']);
});

$names = ['fr' => 'Poterie', 'ar' => 'فخار', 'en' => 'Pottery'];

test('a top-level category stores its uploaded image', function () use ($names) {
    $this->actingAs($this->admin)
        ->post(route('admin.categories.store'), [
            'name' => $names,
            'image' => categoryImageUpload('poterie.png'),
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $category = Category::firstWhere('slug', 'poterie');

    expect($category->image)->not->toBeNull()
        ->and($category->image_url)->not->toBeNull();
    Storage::disk(config('filesystems.default'))->assertExists($category->image);
});

test('a subcategory never stores an image', function () use ($names) {
    $parent = Category::create(['slug' => 'artisanat', 'name' => $names]);

    $this->actingAs($this->admin)
        ->post(route('admin.categories.store'), [
            'name' => ['fr' => 'Tajines', 'ar' => 'طواجن', 'en' => 'Tagines'],
            'parent_ids' => [$parent->id],
            'image' => categoryImageUpload('tajine.png'),
        ])
        ->assertRedirect();

    expect(Category::firstWhere('slug', 'tajines')->image)->toBeNull();
    expect(Storage::disk(config('filesystems.default'))->allFiles())->toBeEmpty();
});

test('replacing an image deletes the previous file', function () use ($names) {
    $disk = Storage::disk(config('filesystems.default'));
    $old = categoryImageUpload('old.png')->store('categories', config('filesystems.default'));
    $category = Category::create(['slug' => 'poterie', 'name' => $names, 'image' => $old]);

    $this->actingAs($this->admin)
        ->post(route('admin.categories.update', $category), [
            '_method' => 'patch',
            'name' => $names,
            'image' => categoryImageUpload('new.png'),
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $category->refresh();

    $disk->assertMissing($old);
    $disk->assertExists($category->image);
});

test('an image can be removed without uploading a new one', function () use ($names) {
    $disk = Storage::disk(config('filesystems.default'));
    $path = categoryImageUpload('a.png')->store('categories', config('filesystems.default'));
    $category = Category::create(['slug' => 'poterie', 'name' => $names, 'image' => $path]);

    $this->actingAs($this->admin)
        ->post(route('admin.categories.update', $category), [
            '_method' => 'patch',
            'name' => $names,
            'remove_image' => true,
        ])
        ->assertRedirect();

    expect($category->refresh()->image)->toBeNull();
    $disk->assertMissing($path);
});

test('moving a category under a parent drops its image', function () use ($names) {
    $disk = Storage::disk(config('filesystems.default'));
    $parent = Category::create(['slug' => 'artisanat', 'name' => ['fr' => 'Artisanat', 'ar' => 'حرف', 'en' => 'Crafts']]);
    $path = categoryImageUpload('a.png')->store('categories', config('filesystems.default'));
    $category = Category::create(['slug' => 'poterie', 'name' => $names, 'image' => $path]);

    $this->actingAs($this->admin)
        ->post(route('admin.categories.update', $category), [
            '_method' => 'patch',
            'name' => $names,
            'parent_ids' => [$parent->id],
        ])
        ->assertRedirect();

    expect($category->refresh()->image)->toBeNull();
    $disk->assertMissing($path);
});

test('deleting a category deletes its image', function () use ($names) {
    $disk = Storage::disk(config('filesystems.default'));
    $path = categoryImageUpload('a.png')->store('categories', config('filesystems.default'));
    $category = Category::create(['slug' => 'poterie', 'name' => $names, 'image' => $path]);

    $this->actingAs($this->admin)
        ->delete(route('admin.categories.destroy', $category))
        ->assertRedirect();

    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    $disk->assertMissing($path);
});

test('a subcategory can be filed under several parents at once', function () use ($names) {
    $poterie = Category::create(['slug' => 'poterie', 'name' => $names]);
    $dinanderie = Category::create(['slug' => 'dinanderie', 'name' => ['fr' => 'Dinanderie', 'ar' => 'نحاسيات', 'en' => 'Metalwork']]);

    $this->actingAs($this->admin)
        ->post(route('admin.categories.store'), [
            'name' => ['fr' => 'Art de la table', 'ar' => 'فن المائدة', 'en' => 'Tableware'],
            'parent_ids' => [$poterie->id, $dinanderie->id],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $child = Category::firstWhere('slug', 'art-de-la-table');

    expect($child->parents->pluck('id')->all())
        ->toEqualCanonicalizing([$poterie->id, $dinanderie->id]);

    // Both ends of the same pivot: it has to show up beneath each craft, or
    // the nav would only ever surface it under one of them.
    expect($poterie->children->pluck('id')->all())->toEqual([$child->id])
        ->and($dinanderie->children->pluck('id')->all())->toEqual([$child->id]);
});

test('the admin index sends every parent id so the picker can preselect them', function () use ($names) {
    $poterie = Category::create(['slug' => 'poterie', 'name' => $names]);
    $dinanderie = Category::create(['slug' => 'dinanderie', 'name' => ['fr' => 'Dinanderie', 'ar' => 'نحاسيات', 'en' => 'Metalwork']]);
    $child = Category::create(['slug' => 'art-de-la-table', 'name' => ['fr' => 'Art de la table', 'ar' => 'فن المائدة', 'en' => 'Tableware']]);
    $child->parents()->attach([$poterie->id, $dinanderie->id]);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.categories.index'))
        // Only roots are offered as parents, so the child is not among them.
        ->assertInertia(fn ($page) => $page->has('parents', 2));

    // Read by id rather than by position: the three rows share a timestamp,
    // so `latest()` leaves their order up to the database.
    $categories = collect($response->viewData('page')['props']['categories']);

    // Flat — one entry per category, however many parents it has.
    expect($categories)->toHaveCount(3)
        ->and($categories->firstWhere('id', $child->id)['parent_ids'])
        ->toEqualCanonicalizing([$poterie->id, $dinanderie->id])
        ->and($categories->firstWhere('id', $poterie->id)['parent_ids'])
        ->toBe([]);
});

test('parents are replaced wholesale on update, not merged', function () use ($names) {
    $poterie = Category::create(['slug' => 'poterie', 'name' => $names]);
    $dinanderie = Category::create(['slug' => 'dinanderie', 'name' => ['fr' => 'Dinanderie', 'ar' => 'نحاسيات', 'en' => 'Metalwork']]);
    $child = Category::create(['slug' => 'tajines', 'name' => ['fr' => 'Tajines', 'ar' => 'طواجن', 'en' => 'Tagines']]);
    $child->parents()->attach([$poterie->id, $dinanderie->id]);

    $this->actingAs($this->admin)
        ->post(route('admin.categories.update', $child), [
            '_method' => 'patch',
            'name' => ['fr' => 'Tajines', 'ar' => 'طواجن', 'en' => 'Tagines'],
            'parent_ids' => [$dinanderie->id],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($child->refresh()->parents->pluck('id')->all())->toEqual([$dinanderie->id]);
});

test('a category cannot be made its own parent', function () use ($names) {
    $category = Category::create(['slug' => 'poterie', 'name' => $names]);

    $this->actingAs($this->admin)
        ->post(route('admin.categories.update', $category), [
            '_method' => 'patch',
            'name' => $names,
            'parent_ids' => [$category->id],
        ])
        ->assertSessionHasErrors('parent_ids.0');

    expect($category->refresh()->parents)->toBeEmpty();
});

test('deleting a parent leaves a shared subcategory under its other parent', function () use ($names) {
    $poterie = Category::create(['slug' => 'poterie', 'name' => $names]);
    $dinanderie = Category::create(['slug' => 'dinanderie', 'name' => ['fr' => 'Dinanderie', 'ar' => 'نحاسيات', 'en' => 'Metalwork']]);
    $child = Category::create(['slug' => 'tajines', 'name' => ['fr' => 'Tajines', 'ar' => 'طواجن', 'en' => 'Tagines']]);
    $child->parents()->attach([$poterie->id, $dinanderie->id]);

    $this->actingAs($this->admin)
        ->delete(route('admin.categories.destroy', $poterie))
        ->assertRedirect();

    // The pivot cascades, so only the row naming the deleted craft goes —
    // the subcategory keeps its remaining parent rather than being orphaned
    // or promoted to a root.
    expect($child->refresh()->parents->pluck('id')->all())->toEqual([$dinanderie->id]);
});

test('the shared nav payload lists every top-level category with its image', function () use ($names) {
    $path = categoryImageUpload('a.png')->store('categories', config('filesystems.default'));
    $withImage = Category::create(['slug' => 'poterie', 'name' => $names, 'image' => $path]);
    $withoutChildren = Category::create(['slug' => 'bois', 'name' => ['fr' => 'Bois', 'ar' => 'خشب', 'en' => 'Wood']]);
    Category::create([
        'slug' => 'tajines',
        'name' => ['fr' => 'Tajines', 'ar' => 'طواجن', 'en' => 'Tagines'],
    ])->parents()->attach($withImage);

    $this->get('/fr')
        ->assertInertia(fn ($page) => $page
            ->has('navCategories', 2)
            ->where('navCategories.0.id', $withImage->id)
            ->where('navCategories.0.image_url', $withImage->image_url)
            ->has('navCategories.0.children', 1)
            ->where('navCategories.1.id', $withoutChildren->id)
            ->where('navCategories.1.image_url', null)
            ->has('navCategories.1.children', 0));
});
