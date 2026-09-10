<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Store;
use App\Models\User;
use App\Notifications\StoreApproved;
use App\Notifications\StoreRejected;
use Illuminate\Support\Facades\Notification;

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

// ── The gate ────────────────────────────────────────────────────────

test('an approved and complete shop can reach the product form', function () {
    $seller = verifiableSeller();

    $this->actingAs($seller)
        ->get(route('seller.products.create'))
        ->assertOk();
});

test('a pending shop cannot reach the product form', function () {
    $seller = verifiableSeller(['verification_status' => Store::VERIFICATION_PENDING]);

    $this->actingAs($seller)
        ->get(route('seller.products.create'))
        ->assertRedirect(route('seller.products.index'))
        ->assertSessionHas('error');
});

test('a rejected shop cannot reach the product form', function () {
    $seller = verifiableSeller(['verification_status' => Store::VERIFICATION_REJECTED]);

    $this->actingAs($seller)
        ->get(route('seller.products.create'))
        ->assertRedirect(route('seller.products.index'));
});

/**
 * The important half: approval alone is not enough. A shop cleared once and
 * later emptied out must stop being able to publish.
 */
test('an approved but incomplete shop cannot reach the product form', function () {
    $seller = verifiableSeller([
        'verification_status' => Store::VERIFICATION_APPROVED,
        'description' => null,
    ]);

    $this->actingAs($seller)
        ->get(route('seller.products.create'))
        ->assertRedirect(route('seller.products.index'));
});

/**
 * The screen is not the boundary — a hand-rolled POST must be refused too,
 * or an unreviewed shop could publish straight past the missing button.
 */
test('a pending shop cannot post a product', function () {
    $seller = verifiableSeller(['verification_status' => Store::VERIFICATION_PENDING]);

    $this->actingAs($seller)
        ->post(route('seller.products.store'), ['name' => 'Contrebande'])
        ->assertRedirect(route('seller.products.index'));

    expect($seller->store->products()->count())->toBe(0);
});

test('the products list stays reachable while unverified', function () {
    $seller = verifiableSeller(['verification_status' => Store::VERIFICATION_PENDING]);

    // This is where the status bar explains the block, so it must never be
    // caught by the same gate.
    $this->actingAs($seller)
        ->get(route('seller.products.index'))
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
