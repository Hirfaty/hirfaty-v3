<?php

use App\Models\SocialAccount;
use App\Models\Store;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * "Continue with Google / Facebook".
 *
 * The provider round trip is faked throughout — what is worth pinning is not
 * that Socialite works, but the three rules wrapped around it:
 *
 *  - only whitelisted, *configured* providers are reachable at all;
 *  - an existing account is joined only on an email the provider vouches for,
 *    which is the account-takeover boundary;
 *  - a social signup is a buyer, never a seller.
 */
beforeEach(function () {
    config([
        'services.google.client_id' => 'test-google-id',
        'services.google.client_secret' => 'test-google-secret',
        'services.facebook.client_id' => 'test-facebook-id',
        'services.facebook.client_secret' => 'test-facebook-secret',
    ]);
});

/**
 * A provider's answer. `$raw` carries the claims Socialite exposes on
 * `->user`, which is where Google's `email_verified` lives.
 */
function socialiteUser(
    string $id = 'provider-id-1',
    ?string $email = 'social@example.com',
    array $raw = ['email_verified' => true, 'given_name' => 'Nadia', 'family_name' => 'Amrani'],
): SocialiteUser {
    $user = new SocialiteUser;
    $user->id = $id;
    $user->email = $email;
    $user->name = trim(($raw['given_name'] ?? '').' '.($raw['family_name'] ?? ''));
    $user->user = $raw;

    return $user;
}

/** Point Socialite's driver at a canned answer. */
function fakeProvider(string $provider, SocialiteUser $user): void
{
    $driver = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
    $driver->shouldReceive('user')->andReturn($user);

    Socialite::shouldReceive('driver')->with($provider)->andReturn($driver);
}

// ── Which providers are reachable ──────────────────────────────────────

test('an unknown provider is not routable', function () {
    // Refused by the route constraint, before Socialite is ever asked to
    // resolve a driver this app never configured.
    $this->get('/auth/github/redirect')->assertNotFound();
    $this->get('/auth/github/callback')->assertNotFound();
});

test('a supported provider with no credentials is not reachable', function () {
    config(['services.facebook.client_id' => null, 'services.facebook.client_secret' => null]);

    $this->get(route('social.redirect', ['provider' => 'facebook']))->assertNotFound();
    $this->get(route('social.callback', ['provider' => 'facebook']))->assertNotFound();
});

test('the login screen only offers configured providers', function () {
    config(['services.facebook.client_id' => null, 'services.facebook.client_secret' => null]);

    $this->get(route('login'))
        ->assertInertia(fn ($page) => $page->where('socialProviders', ['google']));
});

// ── Signing up ─────────────────────────────────────────────────────────

test('a first-time social sign-in creates a verified buyer', function () {
    fakeProvider('google', socialiteUser());

    $this->get(route('social.callback', ['provider' => 'google']))
        ->assertRedirect(route('products.index'));

    $user = User::firstWhere('email', 'social@example.com');

    expect($user)->not->toBeNull()
        ->and($user->first_name)->toBe('Nadia')
        ->and($user->last_name)->toBe('Amrani')
        // The provider vouched for the address, which is the same proof the
        // emailed-code flow asks for — so no second trip to the inbox.
        ->and($user->email_verified_at)->not->toBeNull()
        // Selling is gated behind /sell and an admin review; a button must not
        // be a shortcut around that.
        ->and($user->role)->toBe('buyer')
        ->and($user->store)->toBeNull();

    $this->assertAuthenticatedAs($user);

    expect(SocialAccount::where('user_id', $user->id)->where('provider', 'google')->exists())->toBeTrue();
});

test('a returning social user is matched on provider id, not email', function () {
    $user = User::factory()->create(['email' => 'old@example.com']);
    SocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'provider-id-1',
    ]);

    // Same subject id, different address — they changed it at Google since.
    fakeProvider('google', socialiteUser(email: 'brand-new@example.com'));

    $this->get(route('social.callback', ['provider' => 'google']));

    $this->assertAuthenticatedAs($user);

    // No second account, and the address on file is left alone.
    expect(User::count())->toBe(1)
        ->and($user->fresh()->email)->toBe('old@example.com');
});

// ── Linking, and the line it must not cross ────────────────────────────

test('a verified email joins the existing password account', function () {
    $user = User::factory()->create(['email' => 'social@example.com', 'role' => 'buyer']);

    fakeProvider('google', socialiteUser());

    $this->get(route('social.callback', ['provider' => 'google']));

    $this->assertAuthenticatedAs($user);

    expect(User::count())->toBe(1)
        ->and(SocialAccount::where('user_id', $user->id)->count())->toBe(1);
});

/*
 * The takeover case. Google will issue a profile for an address the account
 * holder never confirmed, so without this check anyone could put a stranger's
 * address on a throwaway Google account and walk into their Hirfaty account.
 */
test('an unverified email neither links nor signs anyone in', function () {
    // Explicitly unverified, so the assertion below is about this flow rather
    // than about what the factory happened to stamp.
    $user = User::factory()->create([
        'email' => 'social@example.com',
        'email_verified_at' => null,
    ]);

    fakeProvider('google', socialiteUser(raw: ['email_verified' => false, 'given_name' => 'Mal', 'family_name' => 'Ory']));

    $this->get(route('social.callback', ['provider' => 'google']))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');

    $this->assertGuest();

    expect(SocialAccount::count())->toBe(0)
        ->and($user->fresh()->email_verified_at)->toBeNull();
});

test('a provider that returns no email is refused', function () {
    fakeProvider('facebook', socialiteUser(email: null, raw: []));

    $this->get(route('social.callback', ['provider' => 'facebook']))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

// ── Guards that must hold on every door ────────────────────────────────

test('a banned account cannot sign in through a provider', function () {
    User::factory()->create(['email' => 'social@example.com', 'is_banned' => true]);

    fakeProvider('google', socialiteUser());

    $this->get(route('social.callback', ['provider' => 'google']))
        ->assertRedirect(route('account.blocked'));

    $this->assertGuest();
});

test('declining consent at the provider is not an error', function () {
    // Arrives as ?error=access_denied with no code. They chose to come back;
    // shouting about it would be wrong.
    $this->get(route('social.callback', ['provider' => 'google']).'?error=access_denied')
        ->assertRedirect(route('login'))
        ->assertSessionMissing('error');

    $this->assertGuest();
});

test('an existing seller lands in their shop, not the marketplace', function () {
    $seller = User::factory()->create(['email' => 'social@example.com', 'role' => 'seller']);
    Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Atelier Social',
        'slug' => 'atelier-social',
        'type' => 'artist',
    ]);

    fakeProvider('google', socialiteUser());

    // Reuses Fortify's LoginResponse, so every door lands in the same place.
    $this->get(route('social.callback', ['provider' => 'google']))
        ->assertRedirect(route('seller.products.index'));

    $this->assertAuthenticatedAs($seller);
});
