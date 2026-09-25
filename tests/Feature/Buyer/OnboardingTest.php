<?php

use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

/**
 * The short intro a new buyer gets after verifying their email, and the trade
 * request it ends on.
 */
beforeEach(function () {
    Notification::fake();

    $this->buyer = User::factory()->notOnboarded()->create(['role' => 'buyer']);

    $this->pottery = Category::create([
        'slug' => 'poterie',
        'name' => ['fr' => 'Poterie', 'ar' => 'فخار', 'en' => 'Pottery'],
    ]);
    $this->rugs = Category::create([
        'slug' => 'tapis',
        'name' => ['fr' => 'Tapis', 'ar' => 'زربية', 'en' => 'Rugs'],
    ]);
});

test('a new buyer is shown the tour', function () {
    $this->actingAs($this->buyer)
        ->get(route('onboarding.show'))
        ->assertSuccessful()
        ->assertInertia(
            fn ($page) => $page->component('buyer/onboarding')
                ->has('categories', 2)
        );
});

/**
 * The tour is a one-time thing. Without this redirect, deep-linking back to
 * the URL would re-run it and re-sync the buyer's interests from an empty
 * form — silently wiping what they picked the first time.
 */
test('someone who has already been through it is sent to the market', function () {
    $this->buyer->forceFill(['onboarded_at' => now()])->save();

    $this->actingAs($this->buyer)
        ->get(route('onboarding.show'))
        ->assertRedirect(route('products.index'));
});

test('completing the tour saves the picked interests', function () {
    $this->actingAs($this->buyer)
        ->post(route('onboarding.store'), [
            'interests' => [$this->pottery->id, $this->rugs->id],
        ])
        ->assertRedirect(route('products.index'));

    $this->buyer->refresh();

    expect($this->buyer->onboarded_at)->not->toBeNull()
        ->and($this->buyer->interests->pluck('id')->all())
        ->toEqualCanonicalizing([$this->pottery->id, $this->rugs->id]);
});

/**
 * Skipping and completing both stamp onboarded_at: the column answers "have
 * we already asked?", not "did they engage?". Anything else and a skipped
 * tour would reappear on the next visit.
 */
test('skipping still counts as done', function () {
    $this->actingAs($this->buyer)
        ->post(route('onboarding.skip'))
        ->assertRedirect(route('products.index'));

    expect($this->buyer->fresh()->onboarded_at)->not->toBeNull();
});

/*
 * A new buyer who signed up to buy something still gets the tour first, but
 * ends it back on that product rather than on the catalogue.
 */
test('the tour ends on the page that made them sign up', function (string $route) {
    $this->withSession(['url.intended' => '/fr/products/vase-bleu'])
        ->actingAs($this->buyer)
        ->post(route($route))
        ->assertRedirect('/fr/products/vase-bleu');
})->with(['onboarding.skip', 'onboarding.store']);

test('finishing without picking anything is fine', function () {
    $this->actingAs($this->buyer)
        ->post(route('onboarding.store'))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->buyer->refresh();

    expect($this->buyer->onboarded_at)->not->toBeNull()
        ->and($this->buyer->interests)->toHaveCount(0)
        ->and($this->buyer->professional_request_status)->toBeNull();
});

/**
 * The tour no longer has a trade-request step — asking a brand-new buyer
 * whether they want wholesale pricing before they have seen the market
 * answered a question nobody had asked yet. Posting the old field is simply
 * ignored rather than validated against, since OnboardingController::store no
 * longer declares it: the request stays available from /professional-account
 * (see ProfessionalRequestTest) whenever a buyer actually needs it.
 */
test('a professional_message on the tour is ignored, not filed as a request', function () {
    $this->actingAs($this->buyer)
        ->post(route('onboarding.store'), [
            'interests' => [$this->pottery->id],
            'professional_message' => 'Je gère un riad de 8 chambres à Marrakech.',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->buyer->refresh();

    expect($this->buyer->professional_request_status)->toBeNull()
        ->and($this->buyer->professional_request_message)->toBeNull()
        ->and($this->buyer->interests->pluck('id')->all())
        ->toEqualCanonicalizing([$this->pottery->id]);
});

/**
 * A seller's first run is the store-setup wizard, which is mandatory rather
 * than skippable — the buyer tour would be a second, conflicting first run.
 */
test('the tour is closed to sellers', function () {
    $seller = User::factory()->notOnboarded()->create(['role' => 'seller']);

    $this->actingAs($seller)->get(route('onboarding.show'))->assertRoleDenied();
});

test('a guest cannot reach the tour', function () {
    $this->get(route('onboarding.show'))->assertRedirect(route('login'));
});
