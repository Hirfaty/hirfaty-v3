<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::emailVerification());
});

test('email verification screen can be rendered', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get(route('verification.notice'));

    $response->assertOk();
});

test('a verified buyer is sent to the market, not the dashboard', function () {
    // The factory default role is 'buyer'. App\Http\Responses\VerifyEmailResponse
    // routes by role the same way LoginResponse does — a buyer's first real
    // screen after verifying should be somewhere they can actually shop.
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('products.index', absolute: false));
});

test('a verified seller is sent to their shop', function () {
    $user = User::factory()->unverified()->create(['role' => 'seller']);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)->get($verificationUrl)
        ->assertRedirect(route('seller.products.index', absolute: false));
});

test('a verified professional buyer is also sent to the market', function () {
    $user = User::factory()->unverified()->create(['role' => 'professional_buyer']);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)->get($verificationUrl)
        ->assertRedirect(route('products.index', absolute: false));
});

test('email is not verified with invalid hash', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')],
    );

    $this->actingAs($user)->get($verificationUrl);

    Event::assertNotDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('email is not verified with invalid user id', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => 123, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)->get($verificationUrl);

    Event::assertNotDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('verified user visiting the verification prompt is sent to config(fortify.home)', function () {
    $user = User::factory()->create();

    Event::fake();

    $response = $this->actingAs($user)->get(route('verification.notice'));

    Event::assertNotDispatched(Verified::class);
    /*
     * This one specific redirect — Fortify's EmailVerificationPromptController
     * bouncing an already-verified visitor off the prompt — is not
     * role-based. It calls RedirectAsIntended directly rather than through a
     * swappable contract, so unlike login and the verification *link*
     * (both overridden in App\Http\Responses), it always falls back to
     * config('fortify.home'), currently '/'.
     */
    $response->assertRedirect(config('fortify.home'));
});

test('already verified user visiting verification link is redirected without firing event again', function () {
    $user = User::factory()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)->get($verificationUrl)
        ->assertRedirect(route('products.index', absolute: false));

    Event::assertNotDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

/**
 * A brand-new buyer is sent to the intro tour instead of straight to the
 * market. This hangs off verification rather than login because it's the one
 * moment we know they're both new and actively engaged.
 */
test('a brand-new buyer lands on the onboarding tour', function () {
    $user = User::factory()->unverified()->notOnboarded()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)->get($verificationUrl)
        ->assertRedirect(route('onboarding.show', absolute: false));
});

/**
 * ...but only once. Someone who has already been through the tour goes
 * straight to the market, so re-verifying never replays it.
 */
test('a buyer who already did the tour goes straight to the market', function () {
    $user = User::factory()->unverified()->create(['onboarded_at' => now()]);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)->get($verificationUrl)
        ->assertRedirect(route('products.index', absolute: false));
});

/**
 * A seller's first run is the mandatory store-setup wizard, so the buyer tour
 * must not intercept them even though they've never seen it.
 */
test('a new seller still goes to their shop, not the buyer tour', function () {
    $user = User::factory()->unverified()->notOnboarded()->create(['role' => 'seller']);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)->get($verificationUrl)
        ->assertRedirect(route('seller.products.index', absolute: false));
});
