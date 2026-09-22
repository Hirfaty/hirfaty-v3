<?php

/*
 * The email-preview routes render live rows — the order previews pull the
 * first order in the database and print the buyer's name, address and phone.
 * They used to be gated on bare `auth`, which meant any signed-up shopper
 * could read a stranger's delivery details by guessing a preview URL.
 *
 * Two guards now, and this file covers the one that is testable here:
 * `role:super_admin` on every route. The other — the routes not being
 * registered at all in production (routes/web.php) — cannot be asserted from
 * the `testing` environment, where they are deliberately present so these
 * tests can run at all.
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Every preview route, with arguments that resolve without needing an order. */
dataset('preview routes', [
    'verify email' => '/preview/email/verify/buyer/fr',
    'auth code' => '/preview/email/code/login_code/fr',
    'professional verdict' => '/preview/email/professional/approved/fr',
]);

test('a guest is sent to sign in', function (string $url) {
    $this->get($url)->assertRedirect(route('login'));
})->with('preview routes');

test('a buyer cannot reach an email preview', function (string $url) {
    $buyer = User::factory()->create([
        'role' => 'buyer',
        'email_verified_at' => now(),
    ]);

    // RoleMiddleware bounces a wrong role to the storefront rather than 403ing.
    // What matters is that the preview never renders.
    $this->actingAs($buyer)->get($url)->assertRedirect(route('products.index'));
})->with('preview routes');

test('a seller cannot reach an email preview', function (string $url) {
    $seller = User::factory()->create([
        'role' => 'seller',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($seller)->get($url)->assertRedirect(route('products.index'));
})->with('preview routes');

test('a super admin can still reach an email preview', function (string $url) {
    $admin = User::factory()->create([
        'role' => 'super_admin',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($admin)->get($url)->assertOk();
})->with('preview routes');
