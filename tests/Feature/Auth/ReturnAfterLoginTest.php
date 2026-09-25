<?php

use App\Models\User;

/**
 * A guest who taps "add to cart" or a heart is sent to /login and, once
 * signed in, must land back on the page they left — not on the catalogue.
 *
 * The other half of the job is refusing to be an open redirect: the target
 * arrives in a query string anyone can craft.
 */
test('signing in returns a buyer to the page that sent them to log in', function () {
    $user = User::factory()->create(['role' => 'buyer']);

    $this->get(route('login', ['redirect' => '/fr/products/vase-bleu']))->assertOk();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/fr/products/vase-bleu');
});

test('the query string of the page they left is kept', function () {
    $user = User::factory()->create(['role' => 'buyer']);

    $this->get(route('login', ['redirect' => '/ar/products?category_id=3']));

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/ar/products?category_id=3');
});

test('a protected page a guest opens brings them back after signing in', function () {
    $user = User::factory()->create(['role' => 'buyer']);

    // Laravel's own guest redirect records the URL; login must now honour it.
    $this->get(route('cart.show'))->assertRedirect();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('cart.show'));
});

test('with nowhere to return to, sign-in still lands where it always did', function () {
    $user = User::factory()->create(['role' => 'buyer']);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('products.index'));
});

test('the destination is used once', function () {
    $user = User::factory()->create(['role' => 'buyer']);

    $this->get(route('login', ['redirect' => '/fr/about']));
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/fr/about');

    $this->post(route('logout'));

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('products.index'));
});

/*
 * ── Not an open redirect ─────────────────────────────────────────────────
 */

test('a redirect to another site is ignored', function (string $target) {
    $user = User::factory()->create(['role' => 'buyer']);

    $this->get(route('login', ['redirect' => $target]));

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('products.index'));
})->with([
    'another host' => ['https://evil.example/phish'],
    'protocol-relative' => ['//evil.example/phish'],
    'backslash trick' => ['/\\evil.example'],
    'javascript scheme' => ['javascript:alert(1)'],
    'a control character' => ["/fr/products\r\nSet-Cookie: x=1"],
    'not a path at all' => ['products'],
]);

test('this site\'s own absolute URL is accepted as a path', function () {
    $user = User::factory()->create(['role' => 'buyer']);

    $this->get(route('login', ['redirect' => url('/fr/products/vase-bleu')]));

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/fr/products/vase-bleu');
});

test('the auth pages are never a destination', function (string $target) {
    $user = User::factory()->create(['role' => 'buyer']);

    $this->get(route('login', ['redirect' => $target]));

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('products.index'));
})->with(['/login', '/login/password', '/logout', '/auth/google/redirect', '/forgot-password']);

/*
 * ── Roles ────────────────────────────────────────────────────────────────
 */

test('a buyer is not sent into the admin area', function () {
    $user = User::factory()->create(['role' => 'buyer']);

    $this->get(route('login', ['redirect' => '/admin/products']));

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('products.index'));
});

test('an admin is returned to the admin page they were after', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);

    $this->get(route('login', ['redirect' => '/admin/orders']));

    $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password'])
        ->assertRedirect('/admin/orders');
});

test('a seller signing in from a product page returns to it', function () {
    $seller = User::factory()->create(['role' => 'seller']);

    $this->get(route('login', ['redirect' => '/fr/products/vase-bleu']));

    $this->post(route('login.store'), ['email' => $seller->email, 'password' => 'password'])
        ->assertRedirect('/fr/products/vase-bleu');
});
