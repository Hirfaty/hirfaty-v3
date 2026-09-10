<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'super_admin']);
});

test('admin generates a copyable reset link instead of emailing one', function () {
    Notification::fake();

    $buyer = User::factory()->create(['role' => 'buyer']);

    $this->actingAs($this->admin)
        ->post(route('admin.users.reset-link', $buyer))
        ->assertRedirect()
        ->assertSessionHas('reset_link');

    // The whole point of the change: nothing is mailed to the user.
    Notification::assertNothingSent();

    $link = session('reset_link');

    expect($link['email'])->toBe($buyer->email)
        ->and($link['expires_in_minutes'])->toBe(60)
        ->and($link['url'])->toContain('/reset-password/')
        // The email rides along as a query param, exactly as Fortify's own
        // reset mail does — the reset screen needs it beside the token.
        ->and($link['url'])->toContain(urlencode($buyer->email));
});

test('the generated link carries a token that actually resets the password', function () {
    $buyer = User::factory()->create(['role' => 'buyer']);

    $this->actingAs($this->admin)
        ->post(route('admin.users.reset-link', $buyer));

    // Pull the raw token straight out of the generated URL and drive the real
    // Fortify reset endpoint with it — proves the link is genuinely usable
    // end to end, not merely well-formed.
    preg_match('#/reset-password/([^?]+)#', session('reset_link')['url'], $matches);

    // Fortify's reset endpoint is behind `guest` middleware, so the admin
    // session from actingAs() has to be dropped first — otherwise the POST
    // is redirected away and silently does nothing.
    auth()->logout();

    $this->post(route('password.update'), [
        'token' => $matches[1],
        'email' => $buyer->email,
        // Must satisfy Password::defaults() (min 12, mixed case, a number, a
        // symbol) — the reset endpoint validates it exactly like registration
        // does.
        'password' => 'NewPass!2345',
        'password_confirmation' => 'NewPass!2345',
    ])->assertSessionHasNoErrors();

    expect(auth()->validate([
        'email' => $buyer->email,
        'password' => 'NewPass!2345',
    ]))->toBeTrue();
});

test('regenerating replaces the previous token', function () {
    $buyer = User::factory()->create(['role' => 'buyer']);

    $this->actingAs($this->admin)->post(route('admin.users.reset-link', $buyer));
    $firstUrl = session('reset_link')['url'];

    $this->actingAs($this->admin)->post(route('admin.users.reset-link', $buyer));
    $secondUrl = session('reset_link')['url'];

    expect($firstUrl)->not->toBe($secondUrl)
        // Only ever one row per user — the old token is replaced, not stacked.
        ->and(DB::table('password_reset_tokens')->where('email', $buyer->email)->count())
        ->toBe(1);

    // And the superseded link no longer works. (Logged out first — the reset
    // endpoint is `guest`-only, see the note in the test above.)
    preg_match('#/reset-password/([^?]+)#', $firstUrl, $matches);

    auth()->logout();

    $this->post(route('password.update'), [
        'token' => $matches[1],
        'email' => $buyer->email,
        'password' => 'should-not-work',
        'password_confirmation' => 'should-not-work',
    ])->assertSessionHasErrors('email');
});

test('admin generates a reset link for a seller', function () {
    Notification::fake();

    $seller = User::factory()->create(['role' => 'seller']);

    $this->actingAs($this->admin)
        ->post(route('admin.users.reset-link', $seller))
        ->assertRedirect()
        ->assertSessionHas('reset_link');

    Notification::assertNotSentTo($seller, ResetPasswordNotification::class);
});

test('admin cannot generate a reset link for another super admin', function () {
    $otherAdmin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($this->admin)
        ->post(route('admin.users.reset-link', $otherAdmin))
        ->assertForbidden()
        ->assertSessionMissing('reset_link');

    expect(DB::table('password_reset_tokens')->where('email', $otherAdmin->email)->count())
        ->toBe(0);
});

test('a non-admin cannot generate a reset link', function () {
    $buyer = User::factory()->create(['role' => 'buyer']);
    $otherBuyer = User::factory()->create(['role' => 'buyer']);

    $this->actingAs($buyer)
        ->post(route('admin.users.reset-link', $otherBuyer))
        ->assertForbidden();

    expect(DB::table('password_reset_tokens')->where('email', $otherBuyer->email)->count())
        ->toBe(0);
});

/**
 * The token repository is keyed by email and the reset screen identifies the
 * account by it, so there is nothing to mint against on a contactless account
 * (see the users.email nullable migration). Fail with a readable message
 * rather than a 500 from a null primary key.
 */
test('no reset link is generated for a user without an email', function () {
    $contactless = User::factory()->create(['role' => 'buyer', 'email' => null]);

    $this->actingAs($this->admin)
        ->post(route('admin.users.reset-link', $contactless))
        ->assertRedirect()
        ->assertSessionMissing('reset_link')
        ->assertSessionHas('error');

    expect(DB::table('password_reset_tokens')->count())->toBe(0);
});
