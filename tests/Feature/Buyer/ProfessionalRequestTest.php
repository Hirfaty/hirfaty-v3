<?php

use App\Models\Product;
use App\Models\User;
use App\Notifications\ProfessionalRequestApproved;
use App\Notifications\ProfessionalRequestDeclined;
use Illuminate\Support\Facades\Notification;

/**
 * buyer asks → admin decides → account becomes professional.
 *
 * The through-line of every test here is that the *buyer* never writes their
 * own role. Trade pricing is worth real money (Product::audienceFor gates the
 * wholesale price list on it), so a self-declared professional would simply be
 * a discount anyone could help themselves to.
 */
beforeEach(function () {
    Notification::fake();

    $this->admin = User::factory()->create(['role' => 'super_admin']);
    $this->buyer = User::factory()->create([
        'role' => 'buyer',
        'email_verified_at' => now(),
        'onboarded_at' => now(),
    ]);
});

function submitRequest(User $buyer, string $message = 'Je gère un riad de 8 chambres à Marrakech et je commande en quantité.')
{
    return test()->actingAs($buyer)->post(route('professional.request'), [
        'message' => $message,
    ]);
}

test('a buyer can request a professional account', function () {
    submitRequest($this->buyer)->assertSessionHasNoErrors();

    $this->buyer->refresh();

    expect($this->buyer->professional_request_status)->toBe('pending')
        ->and($this->buyer->professional_requested_at)->not->toBeNull()
        // The whole point of the gate: still an ordinary buyer.
        ->and($this->buyer->role)->toBe('buyer')
        ->and($this->buyer->professional_verified_at)->toBeNull();
});

test('a pending request cannot be submitted twice', function () {
    submitRequest($this->buyer)->assertSessionHasNoErrors();
    submitRequest($this->buyer, 'Deuxième tentative, même établissement.')
        ->assertSessionHasErrors('professional_request_message');

    // The first message stands — a duplicate must not overwrite it.
    expect($this->buyer->fresh()->professional_request_message)
        ->toContain('riad de 8 chambres');
});

test('a very short message is rejected', function () {
    submitRequest($this->buyer, 'riad')->assertSessionHasErrors('message');

    expect($this->buyer->fresh()->professional_request_status)->toBeNull();
});

test('an admin approving promotes the buyer and stamps the verification', function () {
    submitRequest($this->buyer);

    $this->actingAs($this->admin)
        ->patch(route('admin.users.professional.approve', $this->buyer))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->buyer->refresh();

    expect($this->buyer->role)->toBe('professional_buyer')
        ->and($this->buyer->professional_verified_at)->not->toBeNull()
        ->and($this->buyer->professional_request_status)->toBe('approved')
        ->and($this->buyer->professional_reviewed_by)->toBe($this->admin->id)
        // Role and stamp are written together, so the account never sits in a
        // half-promoted state that reads as a pro who never gets pro prices.
        ->and($this->buyer->isVerifiedProfessionalBuyer())->toBeTrue();

    Notification::assertSentTo($this->buyer, ProfessionalRequestApproved::class);
});

test('approval switches the buyer onto the wholesale price list', function () {
    submitRequest($this->buyer);

    expect(Product::audienceFor($this->buyer->fresh()))
        ->toBe(Product::AUDIENCE_INDIVIDUAL);

    $this->actingAs($this->admin)
        ->patch(route('admin.users.professional.approve', $this->buyer));

    expect(Product::audienceFor($this->buyer->fresh()))
        ->toBe(Product::AUDIENCE_PROFESSIONAL);
});

test('an admin declining keeps the buyer a buyer and records the reason', function () {
    submitRequest($this->buyer);

    $this->actingAs($this->admin)
        ->patch(route('admin.users.professional.decline', $this->buyer), [
            'reason' => 'Nous n’avons pas pu confirmer l’existence de l’établissement.',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->buyer->refresh();

    expect($this->buyer->role)->toBe('buyer')
        ->and($this->buyer->professional_request_status)->toBe('declined')
        ->and($this->buyer->professional_decline_reason)->toContain('confirmer')
        ->and($this->buyer->professional_verified_at)->toBeNull();

    Notification::assertSentTo($this->buyer, ProfessionalRequestDeclined::class);
});

test('a refusal must carry a reason', function () {
    submitRequest($this->buyer);

    $this->actingAs($this->admin)
        ->patch(route('admin.users.professional.decline', $this->buyer), [])
        ->assertSessionHasErrors('reason');

    expect($this->buyer->fresh()->professional_request_status)->toBe('pending');
});

/**
 * A refusal is not a life sentence — circumstances change, and the reason
 * tells them what to fix. Asking again clears the old verdict so the account
 * page doesn't still show a stale refusal next to a live request.
 */
test('a declined buyer can ask again, and the old verdict is cleared', function () {
    submitRequest($this->buyer);
    $this->actingAs($this->admin)
        ->patch(route('admin.users.professional.decline', $this->buyer), [
            'reason' => 'Documents manquants.',
        ]);

    // refresh(), not fresh(): actingAs() authenticates *this* instance, so a
    // stale copy would still report the pre-decline status to the service.
    // A real request always resolves the user from the session.
    $this->buyer->refresh();

    expect($this->buyer->canRequestProfessional())->toBeTrue();

    submitRequest($this->buyer, 'Voici les documents demandés pour mon riad.')
        ->assertSessionHasNoErrors();

    $this->buyer->refresh();

    expect($this->buyer->professional_request_status)->toBe('pending')
        ->and($this->buyer->professional_decline_reason)->toBeNull()
        ->and($this->buyer->professional_reviewed_at)->toBeNull();
});

test('a decision cannot be applied twice', function () {
    submitRequest($this->buyer);

    $this->actingAs($this->admin)
        ->patch(route('admin.users.professional.approve', $this->buyer));

    // Second approval finds nothing pending and is refused, rather than
    // re-stamping a fresh verification date on an already-approved account.
    $this->actingAs($this->admin)
        ->patch(route('admin.users.professional.approve', $this->buyer))
        ->assertSessionHasErrors('professional_request');
});

test('an ordinary buyer cannot approve their own request', function () {
    submitRequest($this->buyer);

    $this->actingAs($this->buyer)
        ->patch(route('admin.users.professional.approve', $this->buyer))
        ->assertRoleDenied();

    expect($this->buyer->fresh()->role)->toBe('buyer');
});

test('an already-professional account is not offered the form again', function () {
    $pro = User::factory()->create([
        'role' => 'professional_buyer',
        'email_verified_at' => now(),
    ]);

    expect($pro->canRequestProfessional())->toBeFalse();

    $this->actingAs($pro)
        ->post(route('professional.request'), ['message' => str_repeat('a', 40)])
        ->assertSessionHasErrors('professional_request_message');
});

test('the account page reports the pending state back to the buyer', function () {
    submitRequest($this->buyer);

    $this->actingAs($this->buyer)
        ->get(route('professional.show'))
        ->assertSuccessful()
        ->assertInertia(
            fn ($page) => $page->component('buyer/professional-account')
                ->where('status', 'pending')
                ->where('canRequest', false)
        );
});

test('a verified professional is shown as such and is not offered the form', function () {
    $pro = User::factory()->create([
        'role' => 'professional_buyer',
        'email_verified_at' => now(),
        'professional_verified_at' => now(),
    ]);

    $this->actingAs($pro)
        ->get(route('professional.show'))
        ->assertInertia(
            fn ($page) => $page->where('isProfessional', true)
                ->where('isVerified', true)
                ->where('canRequest', false)
        );
});

/**
 * isProfessional and isVerified can legitimately disagree — an admin can set
 * the role by hand without stamping the verification, and only the verified
 * pair actually earns wholesale prices. The page shows a different message
 * for each, so the two flags are sent separately.
 */
test('an unverified professional is reported as such', function () {
    $pro = User::factory()->create([
        'role' => 'professional_buyer',
        'email_verified_at' => now(),
        'professional_verified_at' => null,
    ]);

    $this->actingAs($pro)
        ->get(route('professional.show'))
        ->assertInertia(
            fn ($page) => $page->where('isProfessional', true)
                ->where('isVerified', false)
        );
});
