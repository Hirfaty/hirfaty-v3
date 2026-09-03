<?php

use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

/**
 * The real signup payload. The starter kit's `name`-only shape predates this
 * app's first_name/last_name, role, phone and terms fields, so a test built on
 * it registers nobody and passes for the wrong reasons.
 */
function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Achraf',
        'last_name' => 'Ess',
        'email' => 'achraf@example.com',
        'role' => 'buyer',
        'phone_number' => '0600000001',
        'password' => 'Password!234',
        'password_confirmation' => 'Password!234',
        'terms_accepted' => true,
    ], $overrides);
}

test('registration screen can be rendered', function () {
    $this->get(route('register'))->assertOk();
});

test('new users can register', function () {
    $this->post(route('register.store'), registrationPayload())
        ->assertSessionHasNoErrors();

    $this->assertAuthenticated();

    $user = User::firstWhere('email', 'achraf@example.com');

    expect($user)->not->toBeNull()
        ->and($user->first_name)->toBe('Achraf')
        ->and($user->role)->toBe('buyer')
        ->and($user->phone_number)->toBe('0600000001')
        ->and($user->terms_accepted)->toBeTruthy();
});

test('a duplicate phone number is rejected instead of hitting the database', function () {
    User::factory()->create(['phone_number' => '0600000000']);

    /*
     * users.phone_number carries a UNIQUE index. Without a matching `unique`
     * validation rule the insert reached the database and threw a
     * QueryException — a 500 on the register screen rather than a field
     * error next to the phone input.
     */
    $this->post(route('register.store'), registrationPayload([
        'phone_number' => '0600000000',
    ]))->assertSessionHasErrors('phone_number');

    $this->assertGuest();
    expect(User::where('email', 'achraf@example.com')->exists())->toBeFalse();
});

test('a duplicate email is rejected', function () {
    User::factory()->create(['email' => 'pris@example.com']);

    $this->post(route('register.store'), registrationPayload([
        'email' => 'pris@example.com',
    ]))->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('the terms must be accepted', function () {
    $this->post(route('register.store'), registrationPayload([
        'terms_accepted' => false,
    ]))->assertSessionHasErrors('terms_accepted');

    $this->assertGuest();
});
