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
            'parent_id' => $parent->id,
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
            'parent_id' => $parent->id,
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

test('the shared nav payload lists every top-level category with its image', function () use ($names) {
    $path = categoryImageUpload('a.png')->store('categories', config('filesystems.default'));
    $withImage = Category::create(['slug' => 'poterie', 'name' => $names, 'image' => $path]);
    $withoutChildren = Category::create(['slug' => 'bois', 'name' => ['fr' => 'Bois', 'ar' => 'خشب', 'en' => 'Wood']]);
    Category::create([
        'slug' => 'tajines',
        'name' => ['fr' => 'Tajines', 'ar' => 'طواجن', 'en' => 'Tagines'],
        'parent_id' => $withImage->id,
    ]);

    $this->get('/')
        ->assertInertia(fn ($page) => $page
            ->has('navCategories', 2)
            ->where('navCategories.0.id', $withImage->id)
            ->where('navCategories.0.image_url', $withImage->image_url)
            ->has('navCategories.0.children', 1)
            ->where('navCategories.1.id', $withoutChildren->id)
            ->where('navCategories.1.image_url', null)
            ->has('navCategories.1.children', 0));
});
