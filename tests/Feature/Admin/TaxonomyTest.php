<?php

use App\Models\Category;
use App\Models\City;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('super admin can view categories', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);

    $response = $this->actingAs($admin)
        ->get(route('admin.categories.index'));

    $response->assertOk();
});

test('super admin can create a category', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);

    $response = $this->actingAs($admin)
        ->withoutMiddleware()
        ->post(route('admin.categories.store'), [
            'name' => 'Test Category',
            'icon' => '🧶',
        ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('categories', ['name' => 'Test Category']);
});

test('super admin can update a category', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);
    $category = Category::create(['name' => 'Old Name', 'slug' => 'old-name']);

    $response = $this->actingAs($admin)
        ->withoutMiddleware()
        ->patch(route('admin.categories.update', $category), [
            'name' => 'New Name',
        ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'New Name']);
});

test('super admin can delete a category', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);
    $category = Category::create(['name' => 'To Delete', 'slug' => 'to-delete']);

    $response = $this->actingAs($admin)
        ->withoutMiddleware()
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

test('super admin can create a city', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);

    $response = $this->actingAs($admin)
        ->withoutMiddleware()
        ->post(route('admin.cities.store'), [
            'name' => 'Marrakech',
            'region' => 'Marrakech-Safi',
        ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('cities', ['name' => 'Marrakech']);
});

test('super admin can update a city', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);
    $city = City::create(['name' => 'Old City']);

    $response = $this->actingAs($admin)
        ->withoutMiddleware()
        ->patch(route('admin.cities.update', $city), [
            'name' => 'New City',
        ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('cities', ['id' => $city->id, 'name' => 'New City']);
});

test('super admin can delete a city', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);
    $city = City::create(['name' => 'To Delete']);

    $response = $this->actingAs($admin)
        ->withoutMiddleware()
        ->delete(route('admin.cities.destroy', $city));

    $response->assertRedirect();
    $this->assertDatabaseMissing('cities', ['id' => $city->id]);
});

test('buyers cannot access taxonomy management', function () {
    $buyer = User::factory()->create(['role' => 'buyer']);

    $response = $this->actingAs($buyer)
        ->get(route('admin.categories.index'));

    $response->assertForbidden();
});
