<?php

use App\Models\Category;
use App\Models\City;
use App\Models\PendingRegistration;
use App\Models\Store;
use App\Models\User;
use App\Notifications\RegistrationCode;
use App\Services\RegistrationCodeService;
use Illuminate\Support\Facades\Notification;

/**
 * The artisan door — /sell.
 *
 * Shares the code machinery with the shopper flow (see AuthFlowTest), so what
 * is pinned here is only what differs: the intent recorded on the pending row,
 * the branch each step takes off it, and the fact that finishing produces a
 * seller *and* a workshop that is filed for review but cannot yet sell.
 *
 * The security-critical case is the last one. `seller` is a role, and the only
 * thing standing between this public form and self-assigning it is that the
 * role is read from the pending row rather than from the submitted data.
 */
beforeEach(function () {
    Notification::fake();
});

/**
 * Walks /sell and the code step, handing back the resume cookie and the code
 * that was actually mailed — the plaintext exists nowhere else, which is the
 * point of hashing it.
 *
 * @return array{cookie: string, code: string}
 */
function startSellerSignup(string $email = 'artisan@example.com'): array
{
    $response = test()->post(route('sell.identify'), ['email' => $email]);

    // Decrypted on the way out: `withCookie()` encrypts whatever it is given,
    // so feeding it the raw response value would double-encrypt and `resolve()`
    // would quietly find nothing — indistinguishable from a broken guard.
    $cookie = $response->getCookie(RegistrationCodeService::COOKIE)->getValue();

    $code = null;
    Notification::assertSentOnDemand(
        RegistrationCode::class,
        function (RegistrationCode $notification) use (&$code) {
            $code = $notification->code;

            return true;
        },
    );

    return ['cookie' => $cookie, 'code' => $code];
}

/** The two lists the workshop half of the form picks from. */
function sellerTaxonomy(): array
{
    return [
        Category::create([
            'name' => ['fr' => 'Poterie', 'ar' => 'الخزف', 'en' => 'Pottery'],
            'slug' => 'poterie',
        ]),
        City::create(['name' => 'Fès', 'region' => 'Fès-Meknès']),
    ];
}

/**
 * Everything the last step wants. Spelled out rather than hidden in a factory
 * so the "what happens if one is missing" tests can unset a single key.
 */
function sellerDetails(Category $category, City $city): array
{
    return [
        'first_name' => 'Youssef',
        'last_name' => 'El Fassi',
        'phone_number' => '+212612345678',
        'password' => 'Zellige!2026Fes',
        'password_confirmation' => 'Zellige!2026Fes',
        'terms_accepted' => true,
        'store_name' => 'Atelier Bab Boujloud',
        'type' => 'artist',
        'category_id' => $category->id,
        'city_id' => $city->id,
        'description' => 'Trois générations de zellige taillé à la main.',
    ];
}

// ── The door itself ────────────────────────────────────────────────────

test('the artisan door starts a seller-intent signup', function () {
    $this->post(route('sell.identify'), ['email' => 'Artisan@Example.com'])
        ->assertRedirect(route('login.verify'));

    $pending = PendingRegistration::firstWhere('email', 'artisan@example.com');

    // Lowercased to match `fortify.lowercase_usernames`; a capitalised address
    // that stayed as typed would collide on the unique index at the far end.
    expect($pending)->not->toBeNull()
        ->and($pending->intent)->toBe(PendingRegistration::INTENT_SELLER);
});

/*
 * An address that already has an account is a sign-in, not a sign-up — and
 * that holds for a *buyer* too. Starting a seller signup for them would hand
 * out the seller role on the strength of a form post; promotion is an admin
 * decision (Admin\UserController).
 */
test('an existing account is sent to sign in rather than signed up', function (string $role) {
    $user = User::factory()->create(['role' => $role, 'email' => 'known@example.com']);

    $this->post(route('sell.identify'), ['email' => $user->email])
        ->assertRedirect(route('login.password'));

    expect(PendingRegistration::where('email', $user->email)->exists())->toBeFalse();
    Notification::assertNothingSentTo($user);
})->with(['buyer', 'seller']);

test('a signed-in seller is sent to their dashboard', function () {
    $seller = User::factory()->create(['role' => 'seller']);
    Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Atelier Existant',
        'slug' => 'atelier-existant',
        'type' => 'artist',
    ]);

    $this->actingAs($seller)
        ->get(route('sell'))
        ->assertRedirect(route('seller.dashboard'));
});

// ── The steps branch on intent ─────────────────────────────────────────

test('the code and details screens wear the seller chrome', function () {
    ['cookie' => $cookie, 'code' => $code] = startSellerSignup();

    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->get(route('login.verify'))
        ->assertInertia(fn ($page) => $page->component('auth/seller/verify'));

    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->post(route('login.verify.store'), ['code' => $code])
        ->assertRedirect(route('login.details'));

    // The workshop half of the form needs both lists to pick from, or the
    // seller is asked for a craft and a city with nothing to choose.
    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->get(route('login.details'))
        ->assertInertia(fn ($page) => $page
            ->component('auth/seller/details')
            ->has('categories')
            ->has('cities')
            ->has('types'),
        );
});

// ── Finishing ──────────────────────────────────────────────────────────

test('finishing creates a seller and a workshop awaiting review', function () {
    [$category, $city] = sellerTaxonomy();
    ['cookie' => $cookie, 'code' => $code] = startSellerSignup();

    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->post(route('login.verify.store'), ['code' => $code]);

    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->post(route('login.details.store'), sellerDetails($category, $city))
        ->assertRedirect(route('seller.welcome'));

    $user = User::firstWhere('email', 'artisan@example.com');

    expect($user->role)->toBe('seller')
        // Typing back a code sent to that inbox is the proof of ownership —
        // no second trip through an emailed link.
        ->and($user->email_verified_at)->not->toBeNull();

    $store = $user->store;

    expect($store->store_name)->toBe('Atelier Bab Boujloud')
        ->and($store->slug)->toBe('atelier-bab-boujloud')
        ->and($store->verification_status)->toBe(Store::VERIFICATION_PENDING)
        // Not publicly listable, and not publishable, until an admin clears it.
        ->and($store->status)->toBe('inactive')
        ->and($store->canSell())->toBeFalse()
        /*
         * The holding page promises a review, so one has to have actually been
         * requested. StoreVerificationService::submit only stamps this once the
         * profile is complete — which is why every workshop field is required.
         */
        ->and($store->submitted_at)->not->toBeNull();

    // The pending row has done its job and held a code; it should not outlive
    // the account it created.
    expect(PendingRegistration::where('email', 'artisan@example.com')->exists())->toBeFalse();

    $this->assertAuthenticatedAs($user);
});

/*
 * Each of these is in Store::REQUIRED_PROFILE_FIELDS. Skipping any one leaves
 * `hasCompleteProfile()` false, which makes `submit()` a silent no-op — the
 * workshop would sit forever while its owner waited on a review nobody was
 * ever asked for. So they are required at the form, not optional.
 */
test('the workshop half of the form is not skippable', function (string $field) {
    [$category, $city] = sellerTaxonomy();
    ['cookie' => $cookie, 'code' => $code] = startSellerSignup();

    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->post(route('login.verify.store'), ['code' => $code]);

    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->post(route('login.details.store'), [
            ...sellerDetails($category, $city),
            $field => '',
        ])
        ->assertSessionHasErrors($field);

    expect(User::where('email', 'artisan@example.com')->exists())->toBeFalse();
})->with(['store_name', 'type', 'category_id', 'city_id', 'description']);

// ── The line that matters ──────────────────────────────────────────────

/*
 * The role is read off the pending row, never off the request. Without that,
 * anyone could open a shopper signup on /login and then POST workshop fields
 * at the last step to hand themselves `seller` — a public endpoint minting a
 * privileged role.
 */
test('a shopper signup cannot self-assign the seller role', function () {
    [$category, $city] = sellerTaxonomy();

    // Deliberately the *buyer* door.
    $response = $this->post(route('login.identify'), ['email' => 'shopper@example.com']);
    $cookie = $response->getCookie(RegistrationCodeService::COOKIE)->getValue();

    $code = null;
    Notification::assertSentOnDemand(
        RegistrationCode::class,
        function (RegistrationCode $notification) use (&$code) {
            $code = $notification->code;

            return true;
        },
    );

    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->post(route('login.verify.store'), ['code' => $code]);

    // The details screen stays the shopper's, lists and all.
    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->get(route('login.details'))
        ->assertInertia(fn ($page) => $page->component('auth/login/details'));

    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->post(route('login.details.store'), sellerDetails($category, $city));

    $user = User::firstWhere('email', 'shopper@example.com');

    expect($user->role)->toBe('buyer')
        ->and($user->store)->toBeNull()
        ->and(Store::count())->toBe(0);
});
