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

/**
 * Pin the clock to Wednesday 14 October 2026, noon, and push the accounts
 * beforeEach() made out of every chart window, so each test counts only the
 * signups it creates.
 */
function freezeRegistrationClock(): void
{
    User::query()->update(['created_at' => '2000-01-01 00:00:00']);
    test()->travelTo('2026-10-14 12:00:00');
}

test('the registration chart defaults to months and ignores an unknown period', function () {
    foreach (['', '?period=decade', '?period[]=day'] as $query) {
        $this->actingAs($this->admin)
            ->get(route('admin.dashboard').$query)
            ->assertSuccessful()
            ->assertInertia(
                fn ($page) => $page
                    ->where('registrations.period', 'month')
                    ->has('registrations.trend', 12)
                    // Order value is no longer plotted.
                    ->missing('salesTrend')
            );
    }
});

test('the registration chart has a bucket for every period, empty ones included', function (string $period, int $buckets, string $first, string $last) {
    freezeRegistrationClock();

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard', ['period' => $period]))
        ->assertInertia(
            fn ($page) => $page
                ->where('registrations.period', $period)
                ->has('registrations.trend', $buckets)
                ->where('registrations.trend.0.start', $first)
                ->where('registrations.trend.0.count', 0)
                ->where('registrations.trend.'.($buckets - 1).'.start', $last)
                ->where('registrations.trend.'.($buckets - 1).'.count', 0)
        );
})->with([
    'day' => ['day', 30, '2026-09-15', '2026-10-14'],
    // Weeks are labelled by their Monday.
    'week' => ['week', 12, '2026-07-27', '2026-10-12'],
    'month' => ['month', 12, '2025-11-01', '2026-10-01'],
    'year' => ['year', 5, '2022-01-01', '2026-01-01'],
]);

test('a week runs from Monday to Sunday', function () {
    freezeRegistrationClock();

    // The first minutes of this week's Monday, and the last of the Sunday before.
    User::factory()->create(['role' => 'buyer', 'created_at' => '2026-10-12 00:30:00']);
    User::factory()->create(['role' => 'buyer', 'created_at' => '2026-10-11 23:30:00']);
    User::factory()->create(['role' => 'buyer']);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard', ['period' => 'week']))
        ->assertInertia(
            fn ($page) => $page
                ->where('registrations.trend.11.start', '2026-10-12')
                ->where('registrations.trend.11.count', 2)
                ->where('registrations.trend.10.start', '2026-10-05')
                ->where('registrations.trend.10.count', 1)
        );
});

test('the registration chart counts signups per day, month and year and leaves out admins', function () {
    freezeRegistrationClock();

    User::factory()->create(['role' => 'buyer']);
    User::factory()->create(['role' => 'professional_buyer']);
    User::factory()->create(['role' => 'super_admin']);
    User::factory()->create(['role' => 'seller', 'created_at' => '2026-08-20 09:00:00']);
    User::factory()->create(['role' => 'buyer', 'created_at' => '2025-03-01 09:00:00']);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard', ['period' => 'day']))
        ->assertInertia(fn ($page) => $page
            ->where('registrations.trend.29.count', 2)
            ->where('registrations.trend.28.count', 0)
            // Every non-admin account, beforeEach's seller included.
            ->where('stats.total_users', 5));

    $this->get(route('admin.dashboard', ['period' => 'month']))
        ->assertInertia(fn ($page) => $page
            ->where('registrations.trend.11.count', 2)
            ->where('registrations.trend.9.start', '2026-08-01')
            ->where('registrations.trend.9.count', 1));

    $this->get(route('admin.dashboard', ['period' => 'year']))
        ->assertInertia(fn ($page) => $page
            ->where('registrations.trend.4.count', 3)
            ->where('registrations.trend.3.start', '2025-01-01')
            ->where('registrations.trend.3.count', 1));
});

test('the registration chart filters by account type', function (string $role, int $thisMonth, int $total) {
    freezeRegistrationClock();

    // beforeEach's seller (signed up "in 2000") plus, this month:
    User::factory()->count(2)->create(['role' => 'seller']);
    User::factory()->count(3)->create(['role' => 'buyer']);
    User::factory()->create(['role' => 'professional_buyer']);
    User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard', ['role' => $role]))
        ->assertInertia(fn ($page) => $page
            ->where('registrations.role', $role)
            ->where('registrations.trend.11.count', $thisMonth)
            ->where('registrations.total', $total));
})->with([
    'all' => ['all', 6, 7],
    'sellers' => ['seller', 2, 3],
    'buyers' => ['buyer', 3, 3],
    'pro buyers' => ['professional_buyer', 1, 1],
]);

test('the registration chart never counts staff, even when asked to', function () {
    foreach (['', '?role=super_admin', '?role=everyone', '?role[]=seller'] as $query) {
        $this->actingAs($this->admin)
            ->get(route('admin.dashboard').$query)
            ->assertInertia(fn ($page) => $page
                ->where('registrations.role', 'all')
                // beforeEach's seller only — the admin is not counted.
                ->where('registrations.total', 1));
    }
});

test('the period and account type combine', function () {
    freezeRegistrationClock();

    User::factory()->create(['role' => 'seller']);
    User::factory()->create(['role' => 'buyer']);
    User::factory()->create(['role' => 'seller', 'created_at' => '2026-10-13 09:00:00']);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard', ['period' => 'day', 'role' => 'seller']))
        ->assertInertia(fn ($page) => $page
            ->where('registrations.period', 'day')
            ->where('registrations.trend.29.count', 1)
            ->where('registrations.trend.28.count', 1));
});

test('changing a filter reloads only the registration chart', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.dashboard', ['period' => 'week', 'role' => 'seller']))
        ->assertInertia(fn ($page) => $page->reloadOnly(
            'registrations',
            fn ($reload) => $reload
                ->where('registrations.period', 'week')
                ->where('registrations.role', 'seller')
                ->has('registrations.trend', 12)
                ->missing('stats')
                ->missing('health')
        ));
});

test('the pending sellers stat counts shops awaiting verification', function () {
    foreach (['pending', 'approved', 'rejected'] as $status) {
        $seller = User::factory()->create(['role' => 'seller']);
        Store::create([
            'user_id' => $seller->id,
            'store_name' => "Atelier {$status}",
            'slug' => "atelier-{$status}",
            'verification_status' => $status,
        ]);
    }

    // beforeEach's shop is pending by default, plus the one above.
    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->where('stats.pending_sellers', 2));
});

test('the pending professional buyers stat counts trade requests awaiting review', function () {
    User::factory()->create(['role' => 'buyer', 'professional_request_status' => 'pending']);
    User::factory()->create(['role' => 'buyer', 'professional_request_status' => 'declined']);
    User::factory()->create(['role' => 'professional_buyer', 'professional_request_status' => 'approved']);
    User::factory()->create(['role' => 'buyer']);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->where('stats.pending_professional_buyers', 1));
});

test('the money figures are what buyers paid, delivery included', function () {
    $buyer = User::factory()->create(['role' => 'buyer']);
    $order = fn (string $status, float $products, float $delivery) => Order::create([
        'buyer_id' => $buyer->id,
        'store_id' => $this->store->id,
        'city_id' => $this->city->id,
        'status' => $status,
        'total_amount' => $products,
        'platform_fee' => $products * 0.2,
        'delivery_fee' => $delivery,
        'seller_earnings' => $products * 0.8 + $delivery,
        'shipping_address' => 'Test Address',
    ]);

    // A 450 MAD purchase: 400 of products plus 50 of delivery.
    $order('delivered', 400, 50);
    // A split basket in progress: the fee rides on the first order only.
    $order('pending', 300, 35);
    $order('shipped', 120, 0);
    $order('cancelled', 999, 40);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(
            fn ($page) => $page
                ->where('stats.total_revenue', 450)
                ->where('stats.pending_revenue', 455)
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
                // What the buyer paid is reported in full...
                ->where('stats.total_revenue', 220)
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
            ->assertRoleDenied();
    }
});
