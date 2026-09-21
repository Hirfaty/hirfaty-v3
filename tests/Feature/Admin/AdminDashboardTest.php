<?php

use App\Health\Checks\DiskSpaceCheck;
use App\Models\Category;
use App\Models\City;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Facades\Health;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'super_admin']);

    $this->category = Category::create([
        'slug' => 'poterie',
        'name' => ['fr' => 'Poterie', 'ar' => 'فخار', 'en' => 'Pottery'],
    ]);
    $this->city = City::create(['name' => 'Fès']);

    $seller = User::factory()->create(['role' => 'seller']);
    $this->store = Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Atelier Fès',
        'slug' => 'atelier-fes',
        'category_id' => $this->category->id,
        'city_id' => $this->city->id,
    ]);
});

test('the dashboard renders for an admin', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('admin/dashboard'));
});

test('the pending products stat counts products actually awaiting review', function () {
    Product::create([
        'store_id' => $this->store->id,
        'category_id' => $this->category->id,
        'name' => 'En attente',
        'slug' => 'en-attente',
        'base_price' => 100,
        'stock' => 1,
        'status' => 'pending_review',
    ]);
    Product::create([
        'store_id' => $this->store->id,
        'category_id' => $this->category->id,
        'name' => 'Publié',
        'slug' => 'publie',
        'base_price' => 100,
        'stock' => 1,
        'status' => 'published',
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(
            fn ($page) => $page
                ->where('stats.pending_products', 1)
                ->has('pendingProducts', 1)
                ->where('pendingProducts.0.name', 'En attente')
        );
});

test('the sales trend always has six months, even with no orders', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(
            fn ($page) => $page
                ->has('salesTrend', 6)
                // Every month must still report zeros, not be missing.
                ->where('salesTrend.5.total', 0)
                ->where('salesTrend.5.count', 0)
        );
});

test('the sales trend totals orders placed this month and excludes cancelled ones', function () {
    $buyer = User::factory()->create(['role' => 'buyer']);

    Order::create([
        'buyer_id' => $buyer->id,
        'store_id' => $this->store->id,
        'city_id' => $this->city->id,
        'status' => 'delivered',
        'total_amount' => 200,
        'platform_fee' => 40,
        'delivery_fee' => 20,
        'seller_earnings' => 140,
        'shipping_address' => 'Test Address',
    ]);
    Order::create([
        'buyer_id' => $buyer->id,
        'store_id' => $this->store->id,
        'city_id' => $this->city->id,
        'status' => 'cancelled',
        'total_amount' => 500,
        'platform_fee' => 100,
        'delivery_fee' => 20,
        'seller_earnings' => 380,
        'shipping_address' => 'Test Address',
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(
            // The current month is always the last of the six buckets.
            fn ($page) => $page
                ->where('salesTrend.5.total', 200)
                ->where('salesTrend.5.count', 1)
                // The platform's cut is no longer reported anywhere, so the
                // trend carries order value alone.
                ->missing('salesTrend.5.commission')
        );
});

test('no admin dashboard figure reports the platform commission or seller payouts', function () {
    $buyer = User::factory()->create(['role' => 'buyer']);

    Order::create([
        'buyer_id' => $buyer->id,
        'store_id' => $this->store->id,
        'city_id' => $this->city->id,
        'status' => 'delivered',
        'total_amount' => 200,
        'platform_fee' => 40,
        'delivery_fee' => 20,
        'seller_earnings' => 140,
        'shipping_address' => 'Test Address',
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(
            fn ($page) => $page
                // Order money is still reported...
                ->where('stats.total_revenue', 200)
                // ...but nothing derived from the fee split is.
                ->missing('stats.total_platform_fees')
                ->missing('stats.pending_payouts')
                ->missing('stats.total_paid_out')
                ->missing('stats.seller_balance_pending')
                ->missing('stats.seller_balance_available')
        );
});

test('the dashboard exposes a health report even before any check has run', function () {
    // Nothing has written to the result store in a fresh test database, so the
    // panel must degrade to "unknown" rather than erroring out.
    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertSuccessful()
        ->assertInertia(
            fn ($page) => $page
                ->where('health.status', 'unknown')
                ->where('health.stale', true)
                ->where('health.checked_at', null)
                ->has('health.checks', 0)
                ->where('health.counts.ok', 0)
        );
});

test('the dashboard reports the stored health check results', function () {
    // Replace the app's real checks so this test doesn't depend on the host.
    Health::clearChecks()->checks([
        DatabaseCheck::new(),
    ]);

    $this->artisan('health:check')->assertSuccessful();

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(
            fn ($page) => $page
                ->where('health.status', 'ok')
                ->where('health.stale', false)
                ->has('health.checks', 1)
                ->where('health.checks.0.name', 'Database')
                ->where('health.checks.0.status', 'ok')
                ->where('health.counts.ok', 1)
        );
});

test('a failing check turns the overall health status red', function () {
    // Replace the app's real checks so this test doesn't depend on the host.
    Health::clearChecks()->checks([
        // 0% would pass; -1 forces the failure branch without touching the
        // real disk, so the assertion doesn't depend on the host machine.
        DiskSpaceCheck::new()
            ->failWhenUsedSpaceIsAbovePercentage(-1),
    ]);

    $this->artisan('health:check');

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(
            fn ($page) => $page
                ->where('health.status', 'failed')
                ->where('health.checks.0.status', 'failed')
                ->where('health.counts.failed', 1)
        );
});

test('non admins cannot view the dashboard', function () {
    foreach (['buyer', 'professional_buyer', 'seller'] as $role) {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }
});
