<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Error responses are rendered through Inertia (bootstrap/app.php) so a wrong
 * URL lands on a styled page with the header, the locale switcher and a way
 * back — rather than Laravel's unstyled English dead end on a trilingual
 * storefront.
 *
 * The status code itself must survive that: an error page that answers 200
 * would tell search engines and monitoring the URL was fine.
 */
test('an unknown url renders the error page and keeps its 404', function () {
    $this->get('/no-such-page-anywhere')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('error')
            ->where('status', 404)
        );
});

test('a forbidden page renders the error page and keeps its 403', function () {
    /*
     * A genuine 403, not a role-middleware redirect: Admin\UserController
     * aborts outright rather than let one super_admin edit another.
     */
    $admin = User::factory()->create(['role' => 'super_admin']);
    $other = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($admin)
        ->get(route('admin.users.edit', $other))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('error')
            ->where('status', 403)
        );
});

/**
 * A JSON caller asked for data, not a document. Handing it an HTML page it
 * cannot parse would be worse than the plain response it expected.
 */
test('a json request still receives a plain response', function () {
    $response = $this->getJson('/no-such-page-anywhere');

    $response->assertNotFound();
    expect($response->headers->get('content-type'))->toContain('application/json');
});

/**
 * The shared Inertia props have to reach this page, which is the whole reason
 * the fallback route exists: an unmatched URL never enters the `web` group,
 * so HandleInertiaRequests would never run and the page would render with no
 * translations at all — printing `common.errors.404.title` onto the screen
 * instead of a sentence.
 */
test('the error page carries the shared translations, in the active locale', function () {
    foreach (['fr', 'en', 'ar'] as $locale) {
        $this->withSession(['locale' => $locale])
            ->get('/no-such-page-anywhere')
            ->assertNotFound()
            ->assertInertia(function (Assert $page) use ($locale) {
                $props = $page->toArray()['props'];

                expect($props)->toHaveKeys(['locale', 'translations', 'auth'])
                    ->and($props['locale'])->toBe($locale);

                // Dotted keys are part of the key, not a nested path — see
                // the header comment in lang/<locale>/common.php.
                $common = $props['translations']['common'];

                expect($common['errors.404.title'] ?? null)->not->toBeEmpty()
                    ->and($common['errors.404.body'] ?? null)->not->toBeEmpty()
                    ->and($common['errors.back_home'] ?? null)->not->toBeEmpty();
            });
    }
});

test('a signed-in user still gets the error page', function () {
    // The shared Inertia props (cart, translations) are evaluated while
    // rendering this page too, so an authenticated 404 must not blow up on
    // the way out.
    $this->actingAs(User::factory()->create(['role' => 'buyer']))
        ->get('/no-such-page-anywhere')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('error'));
});
