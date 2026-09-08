<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * /professional-account — the public trade-account page.
 *
 * It used to sit behind `auth` + `role:buyer,professional_buyer`, which meant
 * the one audience it exists to convince — people who have not signed up —
 * were the only ones who could never read it. The GET side is now public; the
 * POST that actually submits a request stays gated, since there is no account
 * to upgrade without one.
 */
test('a guest can read the trade page', function () {
    $this->get(route('professional.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('buyer/professional-account')
            // Every state prop has a signed-out answer rather than reaching
            // through a null user.
            ->where('status', null)
            ->where('canRequest', false)
            ->where('isVerified', false)
            ->where('isProfessional', false)
        );
});

test('the trade page is open to every role', function () {
    foreach (['buyer', 'professional_buyer', 'seller', 'super_admin'] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]))
            ->get(route('professional.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('buyer/professional-account'));
    }
});

test('an ordinary buyer is offered the request form', function () {
    $this->actingAs(User::factory()->create(['role' => 'buyer']))
        ->get(route('professional.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canRequest', true)
            ->where('status', null)
        );
});

test('a verified trade account sees itself as verified', function () {
    $this->actingAs(User::factory()->verifiedProfessional()->create())
        ->get(route('professional.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('isVerified', true)
            ->where('isProfessional', true)
            // Nothing left to ask for.
            ->where('canRequest', false)
        );
});

/**
 * Only the GET side opened up. Submitting still needs an account — otherwise
 * there is nothing for an administrator to approve.
 */
test('a guest still cannot submit a request', function () {
    $this->post(route('professional.request'), [
        'message' => 'I run an eight room riad in Marrakech and buy linen in quantity.',
    ])->assertRedirect(route('login'));
});

test('a signed-in buyer can still submit a request', function () {
    $buyer = User::factory()->create(['role' => 'buyer']);

    $this->actingAs($buyer)
        ->post(route('professional.request'), [
            'message' => 'I run an eight room riad in Marrakech and buy linen in quantity.',
        ])
        ->assertSessionHasNoErrors();

    expect($buyer->fresh()->professional_request_status)->toBe('pending');
});

/**
 * The copy is the whole page, so a missing key renders the raw key string.
 * Also pins `pro` into HandleInertiaRequests' hardcoded `$files` list — a new
 * lang file is ignored until it is added there, and the failure is silent.
 */
test('the trade copy resolves in every locale', function () {
    foreach (['fr', 'en', 'ar'] as $locale) {
        $this->withSession(['locale' => $locale])
            ->get(route('professional.show'))
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($locale) {
                $props = $page->toArray()['props'];

                expect($props['locale'])->toBe($locale)
                    ->and($props['translations'])->toHaveKey('pro');

                // Dotted keys are part of the key, not a nested path.
                $pro = $props['translations']['pro'];

                foreach (['hero.lead', 'demo.heading', 'perks.pricing.body', 'steps.1.title'] as $key) {
                    expect($pro[$key] ?? null)->not->toBeEmpty();
                }
            });
    }
});
