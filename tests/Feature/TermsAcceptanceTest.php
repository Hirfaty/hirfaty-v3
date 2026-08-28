<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a buyer who has not accepted the terms is redirected to the terms gate', function () {
    $buyer = User::factory()->withoutAcceptedTerms()->create(['role' => 'buyer']);

    $this->actingAs($buyer)
        ->get(route('dashboard'))
        ->assertRedirect(route('terms.accept'));
});

test('a seller who has not accepted the terms is redirected to the terms gate', function () {
    $seller = User::factory()->withoutAcceptedTerms()->create(['role' => 'seller']);

    $this->actingAs($seller)
        ->get(route('seller.setup.store'))
        ->assertRedirect(route('terms.accept'));
});

test('an admin who has not accepted the terms is redirected to the terms gate', function () {
    $admin = User::factory()->withoutAcceptedTerms()->create(['role' => 'super_admin']);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('terms.accept'));
});

test('a user who has already accepted the terms is not blocked', function () {
    $buyer = User::factory()->create(['role' => 'buyer']);

    $this->actingAs($buyer)
        ->get(route('dashboard'))
        ->assertOk();
});

test('accepting the terms marks the user and redirects back to the page they wanted', function () {
    $buyer = User::factory()->withoutAcceptedTerms()->create(['role' => 'buyer']);

    // Visiting the dashboard first is what records the "intended" URL.
    $this->actingAs($buyer)->get(route('dashboard'));

    $response = $this->actingAs($buyer)->post(route('terms.accept.store'), [
        'terms_accepted' => true,
    ]);

    $response->assertRedirect(route('dashboard'));

    $buyer->refresh();
    expect($buyer->terms_accepted)->toBeTrue();
    expect($buyer->terms_accepted_at)->not->toBeNull();
});

test('accepting the terms requires the checkbox to be checked', function () {
    $buyer = User::factory()->withoutAcceptedTerms()->create(['role' => 'buyer']);

    $this->actingAs($buyer)
        ->post(route('terms.accept.store'), [])
        ->assertSessionHasErrors('terms_accepted');

    expect($buyer->fresh()->terms_accepted)->toBeFalse();
});

test('the terms and privacy pages are publicly accessible', function () {
    $this->get(route('terms.show'))->assertOk();
    $this->get(route('privacy.show'))->assertOk();
});
