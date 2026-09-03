<?php

use App\Models\User;
use App\Notifications\BuyerVerifyEmail;
use App\Notifications\SellerVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Render a user's verification mail to its final HTML. */
function renderVerificationMail(User $user): array
{
    $notification = $user->role === 'seller'
        ? new SellerVerifyEmail
        : new BuyerVerifyEmail;

    $mail = $notification->toMail($user);

    return [
        'subject' => $mail->subject,
        'html' => (string) $mail->render(),
    ];
}

test('the buyer email is sent in the account language', function (string $locale, string $marker) {
    $user = User::factory()->create(['role' => 'buyer', 'locale' => $locale]);

    $mail = renderVerificationMail($user);

    expect($mail['subject'])->toBe(__('emails.verify.buyer.subject', [], $locale))
        ->and($mail['html'])->toContain($marker)
        ->and($mail['html'])->toContain($user->first_name);
})->with([
    ['fr', 'Compte acheteur'],
    ['en', 'Buyer account'],
    ['ar', 'حساب مشترٍ'],
]);

test('the seller email uses the seller copy', function () {
    $user = User::factory()->create(['role' => 'seller', 'locale' => 'fr']);

    $mail = renderVerificationMail($user);

    expect($mail['subject'])->toBe('Activez votre atelier — Hirfaty')
        ->and($mail['html'])->toContain('Compte vendeur')
        // The two audiences must not share copy.
        ->and($mail['html'])->not->toContain('Compte acheteur');
});

test('arabic renders right-to-left', function () {
    $user = User::factory()->create(['role' => 'buyer', 'locale' => 'ar']);

    expect(renderVerificationMail($user)['html'])
        ->toContain('dir="rtl"')
        ->toContain('lang="ar"');
});

test('an unknown stored locale falls back to french instead of failing', function () {
    // The old code interpolated the locale straight into a view name, so a row
    // carrying anything but fr/en/ar threw "view not found" inside the queued
    // job — an email that silently never arrived.
    $user = User::factory()->create(['role' => 'buyer', 'locale' => 'es']);

    $mail = renderVerificationMail($user);

    expect($mail['subject'])->toBe(__('emails.verify.buyer.subject', [], 'fr'))
        ->and($mail['html'])->toContain('dir="ltr"');
});

test('no unresolved translation keys leak into the body', function () {
    foreach (['buyer', 'seller'] as $role) {
        foreach (['fr', 'en', 'ar'] as $locale) {
            $user = User::factory()->create(['role' => $role, 'locale' => $locale]);

            // An unresolved __() returns the key itself, which renders as
            // visible "emails.verify.…" text in the shipped email.
            expect(renderVerificationMail($user)['html'])
                ->not->toMatch('/emails\.verify\./');
        }
    }
});

test('the stated expiry matches the signed link', function () {
    config(['auth.verification.expire' => 45]);

    $user = User::factory()->create(['role' => 'buyer', 'locale' => 'en']);

    // Hardcoding "60 minutes" in the copy, as the old templates did, quietly
    // lies whenever this config is changed.
    expect(renderVerificationMail($user)['html'])->toContain('45 minutes');
});

test('the layout avoids constructs Outlook cannot render', function () {
    $user = User::factory()->create(['role' => 'buyer', 'locale' => 'fr']);
    $html = renderVerificationMail($user)['html'];

    // Outlook renders mail through Word, which supports neither, and the old
    // template built its numbered steps out of both.
    expect($html)->not->toMatch('/display:\s*flex/')
        ->and($html)->not->toMatch('/(^|[;\s])gap:/');
});
