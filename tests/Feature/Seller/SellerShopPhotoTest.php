<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * A real (tiny) PNG — the dimension rule reads actual pixel data, and GD
 * (which UploadedFile::fake()->image() needs) isn't installed here. Named for
 * this file so it can't collide with AdminCreatesUsersTest's helper.
 */
function shopPhotoUpload(string $name = 'atelier.png'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
    ));
}

beforeEach(function () {
    Storage::fake(config('filesystems.default'));

    $this->seller = User::factory()->create(['role' => 'seller']);
    $this->store = Store::create([
        'user_id' => $this->seller->id,
        'store_name' => 'Atelier Photo',
        'slug' => 'atelier-photo',
        'category_id' => Category::create([
            'slug' => 'poterie',
            'name' => ['fr' => 'Poterie', 'ar' => 'فخار', 'en' => 'Pottery'],
        ])->id,
        'city_id' => City::create(['name' => 'Safi'])->id,
        'verification_status' => Store::VERIFICATION_APPROVED,
    ]);
});

test('a seller uploads the photo of their shop', function () {
    $this->actingAs($this->seller)
        ->post(route('seller.settings.store.logo.update'), ['logo' => shopPhotoUpload()])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $logo = $this->store->refresh()->logo;

    expect($logo)->toContain("stores/{$this->store->id}/");
    Storage::disk(config('filesystems.default'))
        ->assertExists(substr($logo, strpos($logo, 'stores/')));
});

test('a new photo replaces the old file instead of piling up', function () {
    $this->actingAs($this->seller)
        ->post(route('seller.settings.store.logo.update'), ['logo' => shopPhotoUpload('one.png')]);
    $first = $this->store->refresh()->logo;

    $this->actingAs($this->seller)
        ->post(route('seller.settings.store.logo.update'), ['logo' => shopPhotoUpload('two.png')]);

    $disk = Storage::disk(config('filesystems.default'));

    expect($this->store->refresh()->logo)->not->toBe($first)
        ->and($disk->allFiles("stores/{$this->store->id}"))->toHaveCount(1);
    $disk->assertMissing(substr($first, strpos($first, 'stores/')));
});

test('changing the photo does not send an approved shop back to review', function () {
    $this->actingAs($this->seller)
        ->post(route('seller.settings.store.logo.update'), ['logo' => shopPhotoUpload()]);

    expect($this->store->refresh()->verification_status)->toBe(Store::VERIFICATION_APPROVED);
});

test('only images are accepted', function () {
    $this->actingAs($this->seller)
        ->post(route('seller.settings.store.logo.update'), [
            'logo' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ])
        ->assertSessionHasErrors('logo');

    $this->actingAs($this->seller)
        ->post(route('seller.settings.store.logo.update'), [])
        ->assertSessionHasErrors('logo');

    expect($this->store->refresh()->logo)->toBeNull();
});

test('buyers cannot reach the upload', function () {
    $this->actingAs(User::factory()->create(['role' => 'buyer']))
        ->post(route('seller.settings.store.logo.update'), ['logo' => shopPhotoUpload()])
        ->assertRoleDenied();
});
