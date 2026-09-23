<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'super_admin']);
    $this->city = City::create(['name' => 'Fès']);
    $this->category = Category::create(['name' => 'Tapis', 'slug' => 'tapis']);
});

function sellerWithStore(array $userAttributes = []): array
{
    $seller = User::factory()->create(array_merge(['role' => 'seller'], $userAttributes));
    $store = Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Atelier '.Str::random(5),
        'slug' => 'atelier-'.Str::random(6),
        'category_id' => test()->category->id,
        'city_id' => test()->city->id,
        'status' => 'active',
    ]);

    return [$seller->fresh(), $store];
}

/** Minimal valid payload for the update endpoint. */
function editPayload(User $user, array $overrides = []): array
{
    return array_merge([
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'email' => $user->email,
        'role' => $user->role,
    ], $overrides);
}

test('the edit screen loads for a buyer', function () {
    $buyer = User::factory()->create(['role' => 'buyer']);

    $this->actingAs($this->admin)
        ->get(route('admin.users.edit', $buyer))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/edit')
            ->where('user.id', $buyer->id)
            ->where('canLeaveSellerRole', true)
            ->has('cities')
            ->has('categories')
            ->etc()
        );
});

test('admin updates a buyer’s identity', function () {
    $buyer = User::factory()->create(['role' => 'buyer', 'first_name' => 'Ancien']);

    $this->actingAs($this->admin)
        ->post(route('admin.users.update', $buyer), editPayload($buyer, [
            'first_name' => 'Nouveau',
            'email' => 'nouveau@hirfaty.ma',
            'phone_number' => '0600000001',
            'description' => 'Revendeur à Casablanca.',
        ]))
        ->assertRedirect(route('admin.users.show', $buyer))
        ->assertSessionHas('success');

    $buyer->refresh();

    expect($buyer->first_name)->toBe('Nouveau')
        ->and($buyer->email)->toBe('nouveau@hirfaty.ma')
        ->and($buyer->phone_number)->toBe('0600000001')
        ->and($buyer->bio)->toBe('Revendeur à Casablanca.');
});

test('re-saving without changing the email does not trip the unique rule', function () {
    $buyer = User::factory()->create(['role' => 'buyer']);

    // The rule has to ignore this user's own row, or editing anything else
    // becomes impossible.
    $this->actingAs($this->admin)
        ->post(route('admin.users.update', $buyer), editPayload($buyer, [
            'first_name' => 'Inchangé',
        ]))
        ->assertSessionHasNoErrors();
});

test('an email already taken by someone else is rejected', function () {
    $other = User::factory()->create(['role' => 'buyer', 'email' => 'pris@hirfaty.ma']);
    $buyer = User::factory()->create(['role' => 'buyer']);

    $this->actingAs($this->admin)
        ->post(route('admin.users.update', $buyer), editPayload($buyer, [
            'email' => $other->email,
        ]))
        ->assertSessionHasErrors('email');

    expect($buyer->fresh()->email)->not->toBe('pris@hirfaty.ma');
});

test('admin edits a seller’s shop details', function () {
    [$seller, $store] = sellerWithStore();

    $this->actingAs($this->admin)
        ->post(route('admin.users.update', $seller), editPayload($seller, [
            'store_name' => 'Atelier Rénové',
            'store_type' => 'cooperative',
            'category_id' => $this->category->id,
            'city_id' => $this->city->id,
            'description' => 'Tapis noués main depuis 1970.',
        ]))
        ->assertSessionHasNoErrors();

    $store->refresh();

    expect($store->store_name)->toBe('Atelier Rénové')
        ->and($store->type)->toBe('cooperative')
        ->and($store->description)->toBe('Tapis noués main depuis 1970.')
        // A seller's blurb belongs to the shop, not the person.
        ->and($seller->fresh()->bio)->toBeNull();
});

test('re-saving a seller keeps their own store name available', function () {
    [$seller, $store] = sellerWithStore();

    $this->actingAs($this->admin)
        ->post(route('admin.users.update', $seller), editPayload($seller, [
            'store_name' => $store->store_name,
            'category_id' => $this->category->id,
            'city_id' => $this->city->id,
        ]))
        ->assertSessionHasNoErrors();
});

test('promoting a buyer to seller creates their shop', function () {
    $buyer = User::factory()->create(['role' => 'buyer']);

    $this->actingAs($this->admin)
        ->post(route('admin.users.update', $buyer), editPayload($buyer, [
            'role' => 'seller',
            'store_name' => 'Nouvel Atelier',
            'category_id' => $this->category->id,
            'city_id' => $this->city->id,
        ]))
        ->assertSessionHasNoErrors();

    $buyer->refresh();

    // Without a Store, EnsureStoreIsSetup would bounce them out of /seller/*.
    expect($buyer->role)->toBe('seller')
        ->and($buyer->store)->not->toBeNull()
        ->and($buyer->store->store_name)->toBe('Nouvel Atelier');
});

test('promoting to seller requires the shop fields', function () {
    $buyer = User::factory()->create(['role' => 'buyer']);

    $this->actingAs($this->admin)
        ->post(route('admin.users.update', $buyer), editPayload($buyer, [
            'role' => 'seller',
        ]))
        ->assertSessionHasErrors(['store_name', 'category_id', 'city_id']);

    expect($buyer->fresh()->role)->toBe('buyer');
});

test('a seller with an empty shop can be demoted', function () {
    [$seller] = sellerWithStore();

    $this->actingAs($this->admin)
        ->post(route('admin.users.update', $seller), editPayload($seller, [
            'role' => 'buyer',
        ]))
        ->assertSessionHasNoErrors();

    expect($seller->fresh()->role)->toBe('buyer');
});

test('a seller with products cannot be demoted', function () {
    [$seller, $store] = sellerWithStore();
    Product::create([
        'store_id' => $store->id, 'name' => 'Tapis', 'slug' => 'tapis-'.Str::random(5),
        'base_price' => 100, 'stock' => 2, 'status' => 'published',
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.users.update', $seller), editPayload($seller, [
            'role' => 'buyer',
        ]))
        ->assertSessionHasErrors('role');

    // Demotion locks them out of /seller/*, stranding anything to fulfil.
    expect($seller->fresh()->role)->toBe('seller');
});

test('a seller with orders cannot be demoted', function () {
    [$seller, $store] = sellerWithStore();
    $buyer = User::factory()->create(['role' => 'buyer']);
    Order::create([
        'buyer_id' => $buyer->id, 'store_id' => $store->id, 'city_id' => $this->city->id,
        'status' => 'pending', 'total_amount' => 100, 'platform_fee' => 5,
        'delivery_fee' => 15, 'seller_earnings' => 80, 'shipping_address' => 'Adresse',
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.users.update', $seller), editPayload($seller, [
            'role' => 'buyer',
        ]))
        ->assertSessionHasErrors('role');

    expect($seller->fresh()->role)->toBe('seller');
});

test('the edit screen flags a seller who can no longer change role', function () {
    [$seller, $store] = sellerWithStore();
    Product::create([
        'store_id' => $store->id, 'name' => 'Tapis', 'slug' => 'tapis-'.Str::random(5),
        'base_price' => 100, 'stock' => 2, 'status' => 'published',
    ]);

    // The form disables the other roles off this flag, so it has to agree
    // with what the server would enforce.
    $this->actingAs($this->admin)
        ->get(route('admin.users.edit', $seller))
        ->assertInertia(fn (Assert $page) => $page->where('canLeaveSellerRole', false)->etc());
});

test('leaving the professional role clears the verification stamp', function () {
    $pro = User::factory()->create([
        'role' => 'professional_buyer',
        'professional_verified_at' => now(),
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.users.update', $pro), editPayload($pro, [
            'role' => 'buyer',
        ]))
        ->assertSessionHasNoErrors();

    // Otherwise switching back would silently restore trade pricing without
    // anyone reviewing the account a second time.
    expect($pro->fresh()->professional_verified_at)->toBeNull();
});

test('a verified professional keeps their stamp when other fields change', function () {
    $pro = User::factory()->create([
        'role' => 'professional_buyer',
        'professional_verified_at' => now(),
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.users.update', $pro), editPayload($pro, [
            'first_name' => 'Renommé',
        ]))
        ->assertSessionHasNoErrors();

    expect($pro->fresh()->professional_verified_at)->not->toBeNull();
});

test('a super admin cannot be edited', function () {
    $other = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($this->admin)
        ->get(route('admin.users.edit', $other))
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->post(route('admin.users.update', $other), editPayload($other, [
            'first_name' => 'Piraté',
            'role' => 'buyer',
        ]))
        ->assertForbidden();

    expect($other->fresh()->role)->toBe('super_admin');
});

test('a non-admin cannot reach the edit screen', function () {
    $buyer = User::factory()->create(['role' => 'buyer']);
    $target = User::factory()->create(['role' => 'buyer']);

    $this->actingAs($buyer)->get(route('admin.users.edit', $target))->assertRoleDenied();
    $this->actingAs($buyer)
        ->post(route('admin.users.update', $target), editPayload($target, [
            'first_name' => 'Piraté',
        ]))
        ->assertRoleDenied();

    expect($target->fresh()->first_name)->not->toBe('Piraté');
});

/**
 * The completion half of contactless creation: the admin comes back once they
 * have the artisan's details. Filling the address in is the same trusted act
 * as supplying one at creation, so it is pre-verified the same way and the
 * account can log in immediately.
 */
test('adding an email to a contactless user pre-verifies it', function () {
    $user = User::factory()->create([
        'role' => 'buyer',
        'email' => null,
        'email_verified_at' => null,
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.users.update', $user), [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => 'enfin@joignable.ma',
            'role' => 'buyer',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->email)->toBe('enfin@joignable.ma')
        ->and($user->email_verified_at)->not->toBeNull();
});

/**
 * The reverse: a verification that vouches for an address no longer on file
 * would be meaningless, so it is dropped with the address.
 */
test('clearing the email drops its verification', function () {
    $user = User::factory()->create([
        'role' => 'buyer',
        'email' => 'partant@exemple.ma',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.users.update', $user), [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => '',
            'role' => 'buyer',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->email)->toBeNull()
        ->and($user->email_verified_at)->toBeNull();
});
