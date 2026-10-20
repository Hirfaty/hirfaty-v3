<?php

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('super admin can view categories', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);

    $response = $this->actingAs($admin)
        ->get(route('admin.categories.index'));

    $response->assertOk();
});

// Category names are stored per locale — see CategoryTranslationTest for the
// translation behaviour itself.
test('super admin can create a category', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);

    $response = $this->actingAs($admin)

        ->post(route('admin.categories.store'), [
            'name' => ['fr' => 'Catégorie test', 'ar' => 'فئة', 'en' => 'Test Category'],
        ]);

    $response->assertRedirect();

    expect(Category::firstWhere('slug', 'categorie-test'))->not->toBeNull();
});

test('super admin can update a category', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);
    $category = Category::create([
        'name' => ['fr' => 'Ancien', 'ar' => 'قديم', 'en' => 'Old Name'],
        'slug' => 'old-name',
    ]);

    $response = $this->actingAs($admin)

        ->patch(route('admin.categories.update', $category), [
            'name' => ['fr' => 'Nouveau', 'ar' => 'جديد', 'en' => 'New Name'],
        ]);

    $response->assertRedirect();

    expect($category->refresh()->nameFor('en'))->toBe('New Name');
});

test('super admin can delete a category', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);
    $category = Category::create([
        'name' => ['fr' => 'À supprimer', 'ar' => 'حذف', 'en' => 'To Delete'],
        'slug' => 'to-delete',
    ]);

    $response = $this->actingAs($admin)

        ->delete(route('admin.categories.destroy', $category));

    $response->assertRedirect();
    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
});

test('super admin can view cities', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);

    $response = $this->actingAs($admin)
        ->get(route('admin.cities.index'));

    $response->assertOk();
});

test('buyers cannot access taxonomy management', function () {
    $buyer = User::factory()->create(['role' => 'buyer']);

    $response = $this->actingAs($buyer)
        ->get(route('admin.categories.index'));

    $response->assertRoleDenied();
});
