<?php

use App\Models\Category;
use App\Models\User;

test('super admin creates a category with all three locales', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($admin)
        ->post(route('admin.categories.store'), [
            'name' => [
                'fr' => 'Tapis Berbères',
                'ar' => 'زرابي أمازيغية',
                'en' => 'Berber Rugs',
            ],
        ])
        ->assertRedirect();

    // The slug is derived from the canonical (French) name, not the English one.
    $category = Category::firstWhere('slug', 'tapis-berberes');

    expect($category)->not->toBeNull()
        ->and($category->name)->toBe([
            'fr' => 'Tapis Berbères',
            'ar' => 'زرابي أمازيغية',
            'en' => 'Berber Rugs',
        ]);
});

test('a category name is rejected when a locale is missing', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($admin)
        ->post(route('admin.categories.store'), [
            'name' => ['fr' => 'Sans arabe', 'en' => 'No arabic'],
        ])
        ->assertSessionHasErrors('name.ar');

    expect(Category::count())->toBe(0);
});

test('name resolves per locale and falls back when a locale is absent', function () {
    $category = Category::create([
        'slug' => 'poterie',
        'name' => ['fr' => 'Poterie', 'en' => 'Pottery'],
    ]);

    app()->setLocale('en');
    expect($category->nameFor())->toBe('Pottery');

    app()->setLocale('fr');
    expect($category->nameFor())->toBe('Poterie');

    // Arabic was never filled in, so it falls back to French rather than blank.
    expect($category->nameFor('ar'))->toBe('Poterie');
});

test('translated_name is serialised for the frontend', function () {
    app()->setLocale('fr');

    $category = Category::create([
        'slug' => 'bois',
        'name' => ['fr' => 'Bois', 'ar' => 'خشب', 'en' => 'Wood'],
    ]);

    expect($category->toArray())->toHaveKey('translated_name')
        ->and($category->toArray()['translated_name'])->toBe('Bois');
});

test('updating a category keeps its slug so existing links do not break', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);
    $category = Category::create([
        'slug' => 'poterie-ceramique',
        'name' => ['fr' => 'Poterie', 'ar' => 'فخار', 'en' => 'Pottery'],
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.categories.update', $category), [
            'name' => ['fr' => 'Céramique', 'ar' => 'خزف', 'en' => 'Ceramics'],
        ])
        ->assertRedirect();

    $category->refresh();

    expect($category->slug)->toBe('poterie-ceramique')
        ->and($category->nameFor('fr'))->toBe('Céramique');
});
