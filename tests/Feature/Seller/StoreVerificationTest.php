<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Notifications\StoreApproved;
use App\Notifications\StoreRejected;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * A seller with a shop, approved and complete unless overridden.
 *
 * Named for this file rather than something generic: Pest loads every test
 * file into one process, so a bare `sellerWithStore()` collided with the one
 * AdminEditsUsersTest already declares and took the whole suite down.
 *
 * @param  array<string, mixed>  $attributes
 */
function verifiableSeller(array $attributes = []): User
{
    $seller = User::factory()->create(['role' => 'seller']);

    // Category/City have no factories, so the rows are made directly.
    // firstOrCreate keeps the taxonomy to a single row across tests.
    $category = Category::firstOrCreate(
        ['slug' => 'test-craft'],
        ['name' => ['fr' => 'Métier test', 'ar' => 'حرفة', 'en' => 'Test craft']],
    );
    $city = City::firstOrCreate(['name' => 'Testville']);

    Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Atelier '.uniqid(),
        'slug' => 'atelier-'.uniqid(),
        'type' => 'artist',
        'category_id' => $category->id,
        'city_id' => $city->id,
        'description' => 'Une présentation suffisamment longue.',
        'verification_status' => Store::VERIFICATION_APPROVED,
        ...$attributes,
    ]);

    return $seller->refresh();
}

/**
 * A published listing on that seller's shop.
 *
 * Created straight through the model, because the seller-facing route that
 * used to do it is exactly what these tests assert no longer exists. Named
 * for this file for the same collision reason as `verifiableSeller` above.
 */
function verifiableSellerProduct(User $seller): Product
{
    return Product::create([
        'store_id' => $seller->store->id,
        'name' => 'Lanterne',
        'slug' => Str::slug('lanterne-'.Str::random(6)),
        'base_price' => 120,
        'stock' => 3,
        'status' => 'published',
    ]);
}

// ── The gate ────────────────────────────────────────────────────────

/*
 * There used to be five tests here covering `store.sellable`, the middleware
 * that let an approved-and-complete shop reach the product form and bounced
 * everyone else. That gate is gone with the form: artisans no longer file
 * their own listings at all — Hirfaty's team contacts them and publishes on
 * their behalf through Admin\ProductController.
 *
 * What replaces them is stricter. Rather than asserting *who* may write, the
 * tests below assert that no seller-facing write path exists to guard.
 */
test('no seller route can create or change a listing', function () {
    // Approved and complete — the shop that used to be allowed to publish.
    $seller = verifiableSeller();
    $product = verifiableSellerProduct($seller);

    foreach ([
        ['get', '/seller/products/create'],
        ['post', '/seller/products'],
        ['get', "/seller/products/{$product->id}/edit"],
        ['put', "/seller/products/{$product->id}"],
        ['patch', "/seller/products/{$product->id}"],
        ['delete', "/seller/products/{$product->id}"],
    ] as [$verb, $uri]) {
        /*
         * 404 where the URI is gone entirely, 405 where it survives as a
         * read-only GET (`/seller/products`) and only the write verb was
         * dropped. Either answer means the same thing here: nothing routes.
         */
        $status = $this->actingAs($seller)
            ->{$verb}($uri, ['name' => 'Contrebande'])
            ->getStatusCode();

        expect($status)
            ->toBeIn([404, 405], "{$verb} {$uri} should no longer be routable");
    }

    expect($seller->store->products()->count())->toBe(1);
});

test('the products list and detail stay reachable, verified or not', function () {
    $seller = verifiableSeller(['verification_status' => Store::VERIFICATION_PENDING]);
    $product = verifiableSellerProduct($seller);

    // Read-only, so shop standing is irrelevant — this is where the status bar
    // explains what is still outstanding.
    $this->actingAs($seller)
        ->get(route('seller.products.index'))
        ->assertOk();

    $this->actingAs($seller)
        ->get(route('seller.products.show', $product->id))
        ->assertOk();
});

// ── Admin review ────────────────────────────────────────────────────

test('an admin approves a shop and the seller is notified', function () {
    Notification::fake();

    $seller = verifiableSeller(['verification_status' => Store::VERIFICATION_PENDING]);
    $admin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($admin)
        ->patch(route('admin.stores.approve', $seller->store))
        ->assertRedirect();

    $store = $seller->store->refresh();

    expect($store->isApproved())->toBeTrue()
        ->and($store->approved_by)->toBe($admin->id)
        // Approval is also what lists the shop publicly.
        ->and($store->status)->toBe('active');

    Notification::assertSentTo($seller, StoreApproved::class);
});

test('an admin cannot approve a shop that is still missing information', function () {
    $seller = verifiableSeller([
        'verification_status' => Store::VERIFICATION_PENDING,
        'city_id' => null,
    ]);
    $admin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($admin)
        ->patch(route('admin.stores.approve', $seller->store))
        ->assertSessionHas('error');

    expect($seller->store->refresh()->isApproved())->toBeFalse();
});

test('an admin rejects a shop with a reason and the seller is notified', function () {
    Notification::fake();

    $seller = verifiableSeller(['verification_status' => Store::VERIFICATION_PENDING]);
    $admin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($admin)
        ->patch(route('admin.stores.reject', $seller->store), [
            'reason' => 'Les photos ne montrent pas vos propres créations.',
        ])
        ->assertRedirect();

    $store = $seller->store->refresh();

    expect($store->isRejected())->toBeTrue()
        ->and($store->rejection_reason)->toContain('photos')
        // Pulled from the public listing for as long as it is refused.
        ->and($store->status)->toBe('inactive');

    Notification::assertSentTo($seller, StoreRejected::class);
});

test('a rejection needs a substantive reason', function () {
    $seller = verifiableSeller(['verification_status' => Store::VERIFICATION_PENDING]);
    $admin = User::factory()->create(['role' => 'super_admin']);

    // The reason is the seller's only instruction on what to fix, so "no" is
    // not an acceptable one.
    $this->actingAs($admin)
        ->patch(route('admin.stores.reject', $seller->store), ['reason' => 'non'])
        ->assertSessionHasErrors('reason');

    expect($seller->store->refresh()->isRejected())->toBeFalse();
});

test('a non-admin cannot approve a shop', function () {
    $seller = verifiableSeller(['verification_status' => Store::VERIFICATION_PENDING]);
    $other = User::factory()->create(['role' => 'buyer']);

    $this->actingAs($other)
        ->patch(route('admin.stores.approve', $seller->store));

    expect($seller->store->refresh()->isApproved())->toBeFalse();
});

// ── Resubmission ────────────────────────────────────────────────────

test('completing a rejected shop puts it back in the queue and clears the reason', function () {
    $seller = verifiableSeller([
        'verification_status' => Store::VERIFICATION_REJECTED,
        'rejection_reason' => 'Présentation trop courte.',
    ]);

    $this->actingAs($seller)
        ->patch(route('seller.settings.store.update'), [
            'store_name' => 'Atelier corrigé',
            'description' => 'Une présentation nettement plus détaillée cette fois-ci.',
            'category_id' => $seller->store->category_id,
            'city_id' => $seller->store->city_id,
        ])
        ->assertRedirect();

    $store = $seller->store->refresh();

    expect($store->isPendingVerification())->toBeTrue()
        ->and($store->rejection_reason)->toBeNull();
});

test('editing an approved shop does not knock it back into review', function () {
    $seller = verifiableSeller();

    $this->actingAs($seller)
        ->patch(route('seller.settings.store.update'), [
            'store_name' => 'Atelier renommé',
            'description' => 'Une description mise à jour, toujours complète.',
            'category_id' => $seller->store->category_id,
            'city_id' => $seller->store->city_id,
        ]);

    expect($seller->store->refresh()->isApproved())->toBeTrue();
});

// ── Model rules ─────────────────────────────────────────────────────

test('a whitespace-only description does not count as complete', function () {
    $store = verifiableSeller(['description' => '   '])->store;

    expect($store->hasCompleteProfile())->toBeFalse()
        ->and($store->missingProfileFields())->toContain('description');
});
