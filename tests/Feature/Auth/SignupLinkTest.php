<?php

use App\Models\PendingRegistration;
use App\Notifications\RegistrationCode;
use App\Services\RegistrationCodeService;
use Illuminate\Support\Facades\Notification;

/**
 * The "confirm my email" button in the signup email — the one-click
 * alternative to typing the code back.
 *
 * Two guards are worth pinning beyond "it works":
 *
 *  - **It proves no more than the code does.** Signed, tied to the latest
 *    send, dead when the code is. A link that outlived a resend, or could be
 *    re-pointed at another row, would be a way into somebody else's signup.
 *  - **Opening it is not enough elsewhere.** Mail scanners open every link;
 *    only an explicit POST confirms the address — and by default the window
 *    the signup started in finishes it (it polls), so the person is told to
 *    go back there. Moving the signup onto the link's browser is a second,
 *    explicit choice (`here`).
 */
beforeEach(function () {
    Notification::fake();
});

/**
 * Start a signup and hand back the resume cookie plus the link that was
 * actually mailed (the last one, when several were sent).
 *
 * @return array{cookie: string, link: string}
 */
function signupWithLink(string $email = 'buyer@example.com', string $door = 'login.identify'): array
{
    $response = test()->post(route($door), ['identifier' => $email]);

    return [
        'cookie' => $response->getCookie(RegistrationCodeService::COOKIE)->getValue(),
        'link' => lastMailedSignupLink(),
    ];
}

function lastMailedSignupLink(): string
{
    $link = null;

    Notification::assertSentOnDemand(
        RegistrationCode::class,
        function (RegistrationCode $notification) use (&$link) {
            $link = $notification->url;

            return true;
        },
    );

    expect($link)->not->toBeNull();

    return $link;
}

test('the signup email carries a confirm link next to the code', function () {
    signupWithLink();

    Notification::assertSentOnDemand(
        RegistrationCode::class,
        function (RegistrationCode $notification, array $channels, object $notifiable) {
            $html = $notification->toMail($notifiable)->render();

            return str_contains($html, e($notification->url))
                && str_contains($html, $notification->code);
        },
    );
});

test('opened in the browser that started the signup, the link goes straight to the details step', function () {
    ['cookie' => $cookie, 'link' => $link] = signupWithLink();

    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->get($link)
        ->assertRedirect(route('login.details'));

    expect(PendingRegistration::first()->isVerified())->toBeTrue();
});

test('opened in another browser, the link only asks — a mail scanner opening it changes nothing', function () {
    ['link' => $link] = signupWithLink();

    $this->get($link)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/login/link')
            ->where('maskedEmail', 'bu•••@example.com'));

    expect(PendingRegistration::first()->isVerified())->toBeFalse();
});

test('confirming from another browser leaves the signup where it started, which then finishes it', function () {
    ['cookie' => $original, 'link' => $link] = signupWithLink();

    // Back to the link page, which now says "go back to the other window".
    $this->post($link)
        ->assertRedirect($link)
        ->assertCookieMissing(RegistrationCodeService::COOKIE);

    expect(PendingRegistration::first()->isVerified())->toBeTrue();

    $this->get($link)->assertInertia(fn ($page) => $page->where('confirmed', true));

    // The window that started it — polling its code screen — moves on and
    // makes the account there.
    $this->withCookie(RegistrationCodeService::COOKIE, $original)
        ->get(route('login.verify'))
        ->assertRedirect(route('login.details'));

    $this->withCookie(RegistrationCodeService::COOKIE, $original)
        ->get(route('login.details'))
        ->assertRedirect(route('onboarding.show'));

    $this->assertAuthenticated();
});

test('"continue on this device" moves the signup to the link\'s browser', function () {
    ['cookie' => $original, 'link' => $link] = signupWithLink();

    $response = $this->post($link, ['here' => true])->assertRedirect(route('login.details'));
    $moved = $response->getCookie(RegistrationCodeService::COOKIE)->getValue();

    expect(PendingRegistration::first()->isVerified())->toBeTrue()
        ->and($moved)->not->toBe($original);

    // A shopper's signup is finished right there: the account is made.
    $this->withCookie(RegistrationCodeService::COOKIE, $moved)
        ->get(route('login.details'))
        ->assertRedirect(route('onboarding.show'));

    $this->assertAuthenticated();

    // One signup, finished in one place: the browser it started in lets go.
    $this->post(route('logout'));

    $this->withCookie(RegistrationCodeService::COOKIE, $original)
        ->get(route('login.details'))
        ->assertRedirect(route('login'));
});

test('an artisan signup continues to the workshop details', function () {
    ['link' => $link] = signupWithLink('artisan@example.com', 'sell.identify');

    $moved = $this->post($link, ['here' => true])->getCookie(RegistrationCodeService::COOKIE)->getValue();

    $this->withCookie(RegistrationCodeService::COOKIE, $moved)
        ->get(route('login.details'))
        ->assertInertia(fn ($page) => $page->component('auth/seller/details'));
});

test('a tampered link is refused', function () {
    ['link' => $link] = signupWithLink();

    $this->get($link.'x')
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');

    // Re-pointed at another row: the signature no longer matches.
    $id = PendingRegistration::first()->id;
    $this->post(str_replace("/verify/{$id}/", '/verify/'.($id + 1).'/', $link))
        ->assertRedirect(route('login'));

    expect(PendingRegistration::first()->isVerified())->toBeFalse();
});

test('a resend retires the links sent before it', function () {
    ['cookie' => $cookie, 'link' => $first] = signupWithLink();

    $this->travel(RegistrationCodeService::RESEND_COOLDOWN_SECONDS + 1)->seconds();
    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)->post(route('login.resend'));
    $this->defaultCookies = [];

    $latest = lastMailedSignupLink();
    expect($latest)->not->toBe($first);

    $this->post($first)->assertRedirect(route('login'));
    $this->post($latest)->assertRedirect($latest);

    expect(PendingRegistration::first()->isVerified())->toBeTrue();
});

test('the link dies with the code', function () {
    ['link' => $link] = signupWithLink();

    $this->travel(RegistrationCodeService::CODE_TTL_MINUTES + 1)->minutes();

    $this->post($link)->assertRedirect(route('login'));
    expect(PendingRegistration::first()->isVerified())->toBeFalse();
});

test('a link still works when the code was locked by wrong guesses', function () {
    ['link' => $link] = signupWithLink();

    PendingRegistration::first()->update(['attempts' => RegistrationCodeService::MAX_ATTEMPTS]);

    $this->post($link)->assertRedirect($link);

    expect(PendingRegistration::first()->isVerified())->toBeTrue();
});

test('an old link opened where a signup is live does not end it', function () {
    ['cookie' => $cookie, 'link' => $first] = signupWithLink();

    $this->travel(RegistrationCodeService::RESEND_COOLDOWN_SECONDS + 1)->seconds();
    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)->post(route('login.resend'));

    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->get($first)
        ->assertRedirect(route('login'))
        ->assertCookieMissing(RegistrationCodeService::COOKIE);
});

test('a code screen whose signup was finished in another tab follows it in', function () {
    ['cookie' => $cookie, 'link' => $link] = signupWithLink();

    // The link opened in a second tab of the same browser: account made, signed in.
    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)->get($link);
    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->get(route('login.details'))
        ->assertRedirect(route('onboarding.show'));

    // The first tab, still polling its code screen: not "session expired".
    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->get(route('login.verify'))
        ->assertRedirect(route('onboarding.show'))
        ->assertSessionMissing('error');
});
