<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Product;
use App\Models\SocialAccount;
use App\Models\Store;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'super_admin']);

    $this->category = Category::create([
        'slug' => 'poterie',
        'name' => ['fr' => 'Poterie', 'ar' => 'فخار', 'en' => 'Pottery'],
    ]);
    $this->city = City::create(['name' => 'Fès']);
});

test('super admins never appear in the list', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.users.index'))
        ->assertInertia(
            fn ($page) => $page
                ->component('admin/users/index')
                ->where(
                    'users.data',
                    fn ($users) => collect($users)->every(
                        fn ($u) => $u['role'] !== 'super_admin'
                    )
                )
        );
});

test('role counts are unaffected by the active search or filter', function () {
    User::factory()->create(['role' => 'buyer']);
    User::factory()->create(['role' => 'buyer']);
    User::factory()->create(['role' => 'professional_buyer']);

    $seller = User::factory()->create(['role' => 'seller']);
    Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Atelier Unique',
        'slug' => 'atelier-unique',
        'category_id' => $this->category->id,
        'city_id' => $this->city->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.users.index', ['search' => 'nonexistent-xyz']))
        ->assertInertia(
            fn ($page) => $page
                ->where('roleCounts.buyer', 2)
                ->where('roleCounts.professional_buyer', 1)
                ->where('roleCounts.seller', 1)
                ->where('totalUsers', 4)
                // The search matched nobody, but counts stay accurate.
                ->where('users.data', [])
        );
});

test('search matches the seller shop name, not just the person', function () {
    $seller = User::factory()->create([
        'role' => 'seller',
        'first_name' => 'Karim',
        'last_name' => 'Idrissi',
    ]);
    Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Zellige Marrakech',
        'slug' => 'zellige-marrakech',
        'category_id' => $this->category->id,
        'city_id' => $this->city->id,
    ]);

    User::factory()->create(['role' => 'buyer', 'first_name' => 'Sara']);

    $this->actingAs($this->admin)
        ->get(route('admin.users.index', ['search' => 'Zellige']))
        ->assertInertia(
            fn ($page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.id', $seller->id)
        );
});

test('search matches by phone number', function () {
    $buyer = User::factory()->create([
        'role' => 'buyer',
        'phone_number' => '0677889900',
    ]);
    User::factory()->create(['role' => 'buyer', 'phone_number' => '0611112222']);

    $this->actingAs($this->admin)
        ->get(route('admin.users.index', ['search' => '677889900']))
        ->assertInertia(
            fn ($page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.id', $buyer->id)
        );
});

test('filtering by role narrows the list without touching the counts', function () {
    User::factory()->create(['role' => 'buyer']);
    User::factory()->create(['role' => 'professional_buyer']);

    $this->actingAs($this->admin)
        ->get(route('admin.users.index', ['role' => 'professional_buyer']))
        ->assertInertia(
            fn ($page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.role', 'professional_buyer')
                ->where('roleCounts.buyer', 1)
        );
});

test('the product count only reflects a seller\'s own store', function () {
    $seller = User::factory()->create(['role' => 'seller']);
    $store = Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Atelier Compté',
        'slug' => 'atelier-compte',
        'category_id' => $this->category->id,
        'city_id' => $this->city->id,
    ]);

    Product::create([
        'store_id' => $store->id,
        'category_id' => $this->category->id,
        'name' => 'Vase',
        'slug' => 'vase-compte',
        'base_price' => 100,
        'stock' => 3,
        'status' => 'published',
    ]);
    Product::create([
        'store_id' => $store->id,
        'category_id' => $this->category->id,
        'name' => 'Tajine',
        'slug' => 'tajine-compte',
        'base_price' => 150,
        'stock' => 2,
        'status' => 'draft',
    ]);

    // A second seller with no products must show 0, not leak the first
    // seller's count.
    $otherSeller = User::factory()->create(['role' => 'seller']);
    Store::create([
        'user_id' => $otherSeller->id,
        'store_name' => 'Atelier Vide',
        'slug' => 'atelier-vide',
        'category_id' => $this->category->id,
        'city_id' => $this->city->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.users.index'))
        ->assertInertia(function ($page) use ($seller, $otherSeller) {
            $page->component('admin/users/index');

            $users = collect($page->toArray()['props']['users']['data']);

            expect($users->firstWhere('id', $seller->id)['products_count'])->toBe(2)
                ->and($users->firstWhere('id', $otherSeller->id)['products_count'])->toBe(0);
        });
});

test('the list exposes each seller\'s shop name, type, and city', function () {
    $seller = User::factory()->create(['role' => 'seller']);
    Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Coopérative Argent',
        'slug' => 'cooperative-argent',
        'type' => 'cooperative',
        'category_id' => $this->category->id,
        'city_id' => $this->city->id,
    ]);

    $buyer = User::factory()->create(['role' => 'buyer']);

    $this->actingAs($this->admin)
        ->get(route('admin.users.index'))
        ->assertInertia(function ($page) use ($seller, $buyer) {
            $users = collect($page->toArray()['props']['users']['data']);
            $sellerStore = $users->firstWhere('id', $seller->id)['store'];

            expect($sellerStore['store_name'])->toBe('Coopérative Argent')
                ->and($sellerStore['type'])->toBe('cooperative')
                ->and($sellerStore['city']['name'])->toBe('Fès')
                ->and($users->firstWhere('id', $buyer->id)['store'])->toBeNull();
        });
});

test('filtering by professional request status narrows the list', function () {
    $pending = User::factory()->create([
        'role' => 'buyer',
        'professional_request_status' => 'pending',
        'professional_requested_at' => now(),
    ]);
    User::factory()->create([
        'role' => 'professional_buyer',
        'professional_request_status' => 'approved',
        'professional_verified_at' => now(),
    ]);
    User::factory()->create([
        'role' => 'buyer',
        'professional_request_status' => 'declined',
        'professional_decline_reason' => 'Documents incomplets',
    ]);
    // Never asked — must not show up under any of the three tabs.
    User::factory()->create(['role' => 'buyer']);

    $this->actingAs($this->admin)
        ->get(route('admin.users.index', ['professional_request_status' => 'pending']))
        ->assertInertia(
            fn ($page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.id', $pending->id)
                ->where('professionalRequestCounts.pending', 1)
                ->where('professionalRequestCounts.approved', 1)
                ->where('professionalRequestCounts.declined', 1)
        );
});

test('the pending professional count only counts pending requests', function () {
    User::factory()->create([
        'role' => 'buyer',
        'professional_request_status' => 'pending',
    ]);
    User::factory()->create([
        'role' => 'professional_buyer',
        'professional_request_status' => 'approved',
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.users.index'))
        ->assertInertia(
            fn ($page) => $page->where('pendingProfessionalCount', 1)
        );
});

test('non admins cannot list users', function () {
    foreach (['buyer', 'professional_buyer', 'seller'] as $role) {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertRoleDenied();
    }
});

test('each row says whether an admin or the person opened the account', function () {
    $selfSignup = User::factory()->create(['role' => 'buyer']);
    $byAdmin = User::factory()->create(['role' => 'buyer']);
    $byAdmin->forceFill(['created_by' => $this->admin->id])->save();

    $this->actingAs($this->admin)
        ->get(route('admin.users.index'))
        ->assertInertia(
            fn ($page) => $page->where('users.data', function ($users) use ($selfSignup, $byAdmin) {
                $rows = collect($users)->keyBy('id');

                return $rows[$selfSignup->id]['created_by'] === null
                    && $rows[$selfSignup->id]['creator'] === null
                    && $rows[$byAdmin->id]['created_by'] === $this->admin->id
                    && $rows[$byAdmin->id]['creator']['first_name'] === $this->admin->first_name;
            })
        );
});

test('each row says whether the person registered by email, Google or Facebook', function () {
    $viaGoogle = User::factory()->create(['role' => 'buyer']);
    SocialAccount::create(['user_id' => $viaGoogle->id, 'provider' => 'google', 'provider_id' => 'g-1']);

    $viaFacebook = User::factory()->create(['role' => 'buyer']);
    SocialAccount::create(['user_id' => $viaFacebook->id, 'provider' => 'facebook', 'provider_id' => 'f-1']);

    // Registered with a code, then signed in with Google weeks later: the
    // link matched their address, it did not open the account.
    $viaEmail = User::factory()->create(['role' => 'buyer', 'created_at' => now()->subWeeks(3)]);
    SocialAccount::create(['user_id' => $viaEmail->id, 'provider' => 'google', 'provider_id' => 'g-2']);

    $byAdmin = User::factory()->create(['role' => 'seller']);
    $byAdmin->forceFill(['created_by' => $this->admin->id])->save();

    $this->actingAs($this->admin)
        ->get(route('admin.users.index'))
        ->assertInertia(
            fn ($page) => $page->where('users.data', function ($users) use ($viaGoogle, $viaFacebook, $viaEmail, $byAdmin) {
                $rows = collect($users)->keyBy('id');

                return $rows[$viaGoogle->id]['signup_method'] === 'google'
                    && $rows[$viaFacebook->id]['signup_method'] === 'facebook'
                    && $rows[$viaEmail->id]['signup_method'] === 'email'
                    && $rows[$viaEmail->id]['linked_providers'] === ['google']
                    && $rows[$byAdmin->id]['signup_method'] === 'admin'
                    && $rows[$byAdmin->id]['linked_providers'] === []
                    // The provider's subject id never reaches the page.
                    && ! array_key_exists('social_accounts', $rows[$viaGoogle->id]);
            })
        );
});
