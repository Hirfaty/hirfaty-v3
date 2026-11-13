<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * The "new arrivals" row on the home page.
 *
 * It shares `NewArrivalsService` with the /new-arrivals page, so what is worth
 * pinning here is the part that differs — a fixed eighteen rather than a
 * paginator — plus the audience gate, which is the one way this row could leak
 * something the visitor is not allowed to see.
 */
beforeEach(function () {
    $this->category = Category::create([
        'slug' => 'poterie',
        'name' => ['fr' => 'Poterie', 'ar' => 'فخار', 'en' => 'Pottery'],
    ]);

    $seller = User::factory()->create(['role' => 'seller']);

    $this->store = Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Atelier Fès',
        'slug' => 'atelier-fes',
        'category_id' => $this->category->id,
        'city_id' => City::create(['name' => 'Fès'])->id,
    ]);
});

function arrival(array $attributes = []): Product
{
    static $n = 0;
    $n++;

    return Product::create(array_merge([
        'store_id' => test()->store->id,
        'category_id' => test()->category->id,
        'name' => "Arrival {$n}",
        'slug' => Str::slug("arrival-{$n}").'-'.Str::random(5),
        'base_price' => 200,
        'stock' => 5,
        'status' => 'published',
        'visibility' => 'both',
    ], $attributes));
}

it('sends the newest eighteen to the home page', function () {
    foreach (range(1, 21) as $ignored) {
        arrival();
    }

    $this->get('/fr')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('newArrivals', 18));
});

/**
 * Newest first, and on `id` rather than `created_at` — a seeded batch shares a
 * timestamp, and a timestamp sort would leave those rows in an order that
 * changes between requests.
 */
it('orders them newest first', function () {
    $first = arrival(['name' => 'Oldest piece']);
    $last = arrival(['name' => 'Newest piece']);

    $this->get('/fr')->assertInertia(fn (Assert $page) => $page
        ->where('newArrivals.0.id', $last->id)
        ->where('newArrivals.1.id', $first->id)
    );
});

// ── Turns across categories ──────────────────────────────────────────────────

function arrivalCategory(string $slug): Category
{
    return Category::create([
        'slug' => $slug,
        'name' => ['fr' => ucfirst($slug), 'ar' => $slug, 'en' => ucfirst($slug)],
    ]);
}

/**
 * A batch filed in one category must not take over the row: each category
 * gets its newest piece in before any category gets a second.
 */
it('takes turns across categories rather than letting one batch fill the row', function () {
    $rugs = arrivalCategory('tapis');
    $lamps = arrivalCategory('luminaires');

    $pot = arrival(['name' => 'Old pot']);
    $lamp = arrival(['name' => 'Lamp', 'category_id' => $lamps->id]);

    $batch = [];

    foreach (range(1, 20) as $n) {
        $batch[] = arrival(['name' => "Rug {$n}", 'category_id' => $rugs->id]);
    }

    $this->get('/fr')->assertInertia(fn (Assert $page) => $page
        ->has('newArrivals', 18)
        // First round: the newest of each category, newest first.
        ->where('newArrivals.0.id', end($batch)->id)
        ->where('newArrivals.1.id', $lamp->id)
        ->where('newArrivals.2.id', $pot->id)
        // Then the rest of the batch.
        ->where('newArrivals.3.id', $batch[18]->id)
    );
});

it('interleaves categories on the new arrivals page too', function () {
    $rugs = arrivalCategory('tapis');

    $pot = arrival(['name' => 'Old pot']);

    foreach (range(1, 15) as $n) {
        arrival(['name' => "Rug {$n}", 'category_id' => $rugs->id]);
    }

    $this->get('/fr/new-arrivals')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products', 12)
            ->where('products.1.id', $pot->id)
            ->where('pagination.total', 16)
        );
});

/**
 * The rank is taken over what the visitor may see, so a hidden piece never
 * uses up its category's turn.
 */
it('does not let hidden pieces take a category turn', function () {
    $rugs = arrivalCategory('tapis');

    $pot = arrival(['name' => 'Visible pot']);
    arrival(['name' => 'Hidden pot', 'status' => 'pending_review']);
    arrival(['name' => 'Trade pot', 'visibility' => 'professional']);

    foreach (range(1, 3) as $n) {
        arrival(['name' => "Rug {$n}", 'category_id' => $rugs->id]);
    }

    $this->get('/fr')->assertInertia(fn (Assert $page) => $page
        ->has('newArrivals', 4)
        ->where('newArrivals.1.id', $pot->id)
    );
});

it('sends fewer than eighteen when the catalogue is short', function () {
    arrival();
    arrival();

    $this->get('/fr')->assertInertia(fn (Assert $page) => $page->has('newArrivals', 2));
});

it('sends an empty row when nothing is listed', function () {
    $this->get('/fr')->assertInertia(fn (Assert $page) => $page->has('newArrivals', 0));
});

// ── The audience gate ────────────────────────────────────────────────────────

it('hides trade-only pieces from a visitor who is not signed in', function () {
    arrival(['name' => 'Open to all', 'visibility' => 'both']);
    arrival(['name' => 'Trade only', 'visibility' => 'professional']);

    $this->get('/fr')->assertInertia(fn (Assert $page) => $page
        ->has('newArrivals', 1)
        ->where('newArrivals.0.name', 'Open to all')
    );
});

it('hides trade-only pieces from an ordinary buyer', function () {
    arrival(['visibility' => 'professional']);

    $this->actingAs(User::factory()->create(['role' => 'buyer']))
        ->get('/fr')
        ->assertInertia(fn (Assert $page) => $page->has('newArrivals', 0));
});

it('shows trade-only pieces to a visitor shopping in pro mode', function () {
    arrival(['name' => 'Trade only', 'visibility' => 'professional']);

    // The "Pro" choice in the header — no account needed (PriceModeService).
    shopInProMode();

    $this->get('/fr')
        ->assertInertia(fn (Assert $page) => $page
            ->has('newArrivals', 1)
            ->where('newArrivals.0.name', 'Trade only')
        );
});

// ── What must not reach the row ──────────────────────────────────────────────

it('leaves unpublished pieces out', function () {
    arrival(['status' => 'published']);
    arrival(['status' => 'pending_review']);
    arrival(['status' => 'rejected']);
    arrival(['status' => 'archived']);

    $this->get('/fr')->assertInertia(fn (Assert $page) => $page->has('newArrivals', 1));
});

/**
 * `BuyerProductResource` reaches through to `store->city->name`, so a product
 * whose shop has gone would throw while the page was being rendered. The
 * `whereHas('store')` guard in `NewArrivalsService::forHome()` is what stops
 * that; it cannot be provoked from here, since `products.store_id` is NOT NULL
 * and an orphan can only appear if a store row is deleted out from under it.
 */
it('renders even when a shop is removed under its products', function () {
    arrival();

    $this->store->delete();

    $this->get('/fr')->assertOk();
});

it('ships the row translations in all three locales', function () {
    foreach (['fr', 'en', 'ar'] as $locale) {
        $welcome = require lang_path("{$locale}/welcome.php");

        foreach (['heading', 'view_all', 'prev', 'next'] as $key) {
            expect($welcome["new_arrivals.{$key}"] ?? null)
                ->not->toBeEmpty("lang/{$locale}/welcome.php is missing 'new_arrivals.{$key}'");
        }
    }
});
