<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin creates a buyer and receives a working set-password link', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);

    $response = $this->actingAs($admin)->postJson(route('admin.users.store'), [
        'first_name' => 'Amina',
        'last_name' => 'Bennani',
        'email' => 'amina@example.com',
        'phone_number' => null,
        'role' => 'buyer',
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['user_id', 'store_id', 'invite_url']);

    $user = User::findOrFail($response->json('user_id'));
    expect($user->role)->toBe('buyer');
    expect($user->email_verified_at)->not->toBeNull();

    $inviteUrl = $response->json('invite_url');
    expect($inviteUrl)->toContain('/reset-password/');
    expect($inviteUrl)->toContain('email=amina%40example.com');

    parse_str(parse_url($inviteUrl, PHP_URL_QUERY), $query);
    $path = parse_url($inviteUrl, PHP_URL_PATH);
    $token = basename($path);

    // The link is meant for the invited user, not the admin who is still
    // logged in — the reset route requires a guest, just like in production.
    auth()->logout();

    // The token must actually be usable to set a new password.
    $resetResponse = $this->post(route('password.update'), [
        'token' => $token,
        'email' => $query['email'],
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
    ]);

    $resetResponse->assertSessionHasNoErrors();
    expect($user->fresh()->password)->not->toBe($user->password);
});

test('admin creates a seller with a store and can add draft products', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);
    $category = Category::create(['name' => 'Poterie', 'slug' => 'poterie']);
    $city = City::create(['name' => 'Fès']);

    $userResponse = $this->actingAs($admin)->postJson(route('admin.users.store'), [
        'first_name' => 'Youssef',
        'last_name' => 'Amrani',
        'email' => 'youssef@example.com',
        'phone_number' => null,
        'role' => 'seller',
        'store_name' => 'Atelier Amrani',
        'category_id' => $category->id,
        'city_id' => $city->id,
    ]);

    $userResponse->assertOk();
    $storeId = $userResponse->json('store_id');
    expect($storeId)->not->toBeNull();
    expect(Store::find($storeId)->store_name)->toBe('Atelier Amrani');

    $productResponse = $this->actingAs($admin)->postJson(route('admin.users.store'), [
        'step' => 'product',
        'store_id' => $storeId,
        'products' => [
            [
                'name' => 'Tajine artisanal',
                'description' => 'Fait main',
                'base_price' => 150,
                'sale_price' => null,
                'stock' => 10,
                'category_id' => $category->id,
            ],
        ],
    ]);

    $productResponse->assertOk();

    $product = Product::where('store_id', $storeId)->firstOrFail();
    expect($product->status)->toBe('draft');
    expect($product->name)->toBe('Tajine artisanal');
});

test('admin can regenerate a set-password link for an existing user', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);
    $buyer = User::factory()->create(['role' => 'buyer']);

    $response = $this->actingAs($admin)->postJson(route('admin.users.invite-link', $buyer));

    $response->assertOk();
    $response->assertJsonStructure(['invite_url']);
    expect($response->json('invite_url'))->toContain('/reset-password/');
});

test('non-admins cannot create users or request invite links', function () {
    $seller = User::factory()->create(['role' => 'seller']);

    $this->actingAs($seller)
        ->postJson(route('admin.users.store'), ['role' => 'buyer'])
        ->assertForbidden();

    $buyer = User::factory()->create(['role' => 'buyer']);

    $this->actingAs($seller)
        ->postJson(route('admin.users.invite-link', $buyer))
        ->assertForbidden();
});
