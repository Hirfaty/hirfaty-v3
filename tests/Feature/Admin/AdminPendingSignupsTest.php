<?php

use App\Models\PendingRegistration;
use App\Models\User;
use App\Notifications\RegistrationCode;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    $this->admin = User::factory()->create(['role' => 'super_admin']);
});

function pendingSignup(string $email, array $overrides = []): PendingRegistration
{
    return PendingRegistration::create([
        'email' => $email,
        'code_hash' => bcrypt('123456'),
        'resume_token_hash' => hash('sha256', 'token-'.$email),
        'expires_at' => now()->addMinutes(15),
        'last_sent_at' => now()->subMinutes(5),
        'locale' => 'fr',
        'intent' => PendingRegistration::INTENT_BUYER,
        ...$overrides,
    ]);
}

test('signups that stopped before the password step are listed, with where they stopped', function () {
    pendingSignup('waiting@example.com');
    pendingSignup('confirmed@example.com', [
        'verified_at' => now(),
        'intent' => PendingRegistration::INTENT_SELLER,
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.users.pending'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/users/pending')
            ->has('signups.data', 2)
            ->where('signups.data', fn ($rows) => collect($rows)->contains(
                fn ($row) => $row['email'] === 'confirmed@example.com'
                    && $row['verified'] === true
                    && $row['intent'] === 'seller'
            ))
            // Nothing secret about the row reaches the page.
            ->where('signups.data.0', fn ($row) => ! isset($row['code_hash']) && ! isset($row['resume_token_hash'])));
});

test('an address that has since become an account is not pending', function () {
    pendingSignup('now-a-user@example.com');
    User::factory()->create(['email' => 'now-a-user@example.com']);

    $this->actingAs($this->admin)
        ->get(route('admin.users.pending'))
        ->assertInertia(fn ($page) => $page->has('signups.data', 0));
});

test('the users table links to them with a count', function () {
    pendingSignup('a@example.com');
    pendingSignup('b@example.com');

    $this->actingAs($this->admin)
        ->get(route('admin.users.index'))
        ->assertInertia(fn ($page) => $page->where('pendingSignupCount', 2));
});

test('search narrows by email', function () {
    pendingSignup('fatima@example.com');
    pendingSignup('karim@example.com');

    $this->actingAs($this->admin)
        ->get(route('admin.users.pending', ['search' => 'fatima']))
        ->assertInertia(fn ($page) => $page
            ->has('signups.data', 1)
            ->where('signups.data.0.email', 'fatima@example.com'));
});

test('an admin can mail a fresh code and link, even once the code has expired', function () {
    $pending = pendingSignup('late@example.com', ['expires_at' => now()->subHour()]);

    $this->actingAs($this->admin)
        ->post(route('admin.users.pending.resend', $pending))
        ->assertSessionHas('success');

    Notification::assertSentOnDemand(RegistrationCode::class, fn (RegistrationCode $n) => $n->url !== null);
    expect($pending->fresh()->hasExpired())->toBeFalse();
});

test('the resend cooldown applies to admins too', function () {
    $pending = pendingSignup('fresh@example.com', ['last_sent_at' => now()]);

    $this->actingAs($this->admin)
        ->post(route('admin.users.pending.resend', $pending))
        ->assertSessionHas('error');

    Notification::assertNothingSent();
});

test('non admins cannot see pending signups', function () {
    foreach (['seller', 'buyer'] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]))
            ->get(route('admin.users.pending'))
            ->assertRoleDenied();
    }
});
