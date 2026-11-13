<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * /professional-account — the public page explaining pro prices.
 *
 * Nothing to apply for any more: anyone shops on the pro list by switching to
 * "Pro" (PriceModeService), so the page carries no per-account state, only
 * the explanation and the switch.
 */
test('a guest can read the pro page', function () {
    $this->get(route('professional.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('buyer/professional-account')
            // No request form, so no request state to report.
            ->missing('status')
            ->missing('canRequest')
        );
});

test('the pro page is open to every role', function () {
    foreach (['buyer', 'professional_buyer', 'seller', 'super_admin'] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]))
            ->get(route('professional.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('buyer/professional-account'));
    }
});

test('there is no request to submit', function () {
    $this->actingAs(User::factory()->create(['role' => 'buyer']))
        ->post('/professional-account', [
            'message' => 'I run an eight room riad in Marrakech and buy linen in quantity.',
        ])
        ->assertClientError();
});

/**
 * The copy is the whole page, so a missing key renders the raw key string.
 * Also pins `pro` into HandleInertiaRequests' hardcoded `$files` list — a new
 * lang file is ignored until it is added there, and the failure is silent.
 */
test('the pro copy resolves in every locale', function () {
    $keys = [
        'hero.lead', 'hero.cta', 'demo.heading', 'perks.pricing.body',
        'steps.1.title', 'notes.open.body', 'notes.minimum.body', 'cta.heading',
        'mode.individual', 'mode.professional', 'chooser.title', 'notice.body',
        'price.min_qty', 'price.fewer', 'price.buy_retail',
    ];

    $sets = [];

    foreach (['fr', 'en', 'ar'] as $locale) {
        // A public page: the URL carries the language, not the session.
        $this->get(route('professional.show', ['locale' => $locale]))
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($locale, $keys, &$sets) {
                $props = $page->toArray()['props'];

                expect($props['locale'])->toBe($locale)
                    ->and($props['translations'])->toHaveKey('pro');

                // Dotted keys are part of the key, not a nested path.
                $pro = $props['translations']['pro'];

                foreach ($keys as $key) {
                    expect($pro[$key] ?? null)->not->toBeEmpty();
                }

                $sets[$locale] = array_keys($pro);
            });
    }

    // All three locales define the same keys.
    sort($sets['fr']);
    sort($sets['en']);
    sort($sets['ar']);

    expect($sets['en'])->toBe($sets['fr'])
        ->and($sets['ar'])->toBe($sets['fr']);
});
