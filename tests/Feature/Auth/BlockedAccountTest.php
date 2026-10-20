<?php

use App\Models\User;
use App\Services\BlockedAccountService;

/*
 * Blocking an account from the admin (Admin\UserController::ban) has to take
 * the person off the platform: signed out wherever they are, unable to sign
 * back in, and told why on /account-blocked.
 */

test('an already signed-in account is signed out on its next request once blocked', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('cart.show'))->assertOk();

    $user->forceFill(['is_banned' => true])->save();

    $this->get(route('cart.show'))
        ->assertRedirect(route('account.blocked'))
        ->assertSessionHas(BlockedAccountService::SESSION_KEY, true);

    $this->assertGuest();
});

test('a form submitted from an open tab is answered with a 303', function () {
    $user = User::factory()->create(['is_banned' => true]);

    $this->actingAs($user)
        ->post(route('cart.add'))
        ->assertStatus(303)
        ->assertRedirect(route('account.blocked'));

    $this->assertGuest();
});

test('a blocked account is turned away by the password sign-in itself', function () {
    $user = User::factory()->create(['is_banned' => true]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('account.blocked'));

    $this->assertGuest();
});

test('the notice is shown to the person who was turned away', function () {
    $user = User::factory()->create(['is_banned' => true]);

    $this->actingAs($user)->get(route('cart.show'));

    $this->get(route('account.blocked'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/account-blocked'));
});

test('nobody else lands on the notice', function () {
    $this->get(route('account.blocked'))->assertRedirect(route('products.index'));

    $this->actingAs(User::factory()->create())
        ->get(route('account.blocked'))
        ->assertRedirect(route('products.index'));
});

test('admins blocking an account do not lock themselves out', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);
    $buyer = User::factory()->create();

    $this->actingAs($admin)
        ->patch(route('admin.users.ban', $buyer))
        ->assertRedirect();

    expect($buyer->fresh()->is_banned)->toBeTruthy();
    $this->assertAuthenticatedAs($admin);
});
