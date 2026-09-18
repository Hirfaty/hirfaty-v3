<?php

use App\Models\LoginCode;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Notifications\LoginCodeNotification;
use App\Notifications\RegistrationCode;
use App\Services\LoginCodeService;
use App\Services\RegistrationCodeService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;

/**
 * The unified /login flow — one email field that branches three ways.
 *
 * There is no /register any more; signing in and signing up share a screen and
 * the server decides which is happening (see Auth\LoginIdentifyController). So
 * the branch itself is the first thing worth pinning, followed by two families
 * of guard:
 *
 *  - **Resume.** The premise is that someone can close the tab mid-signup and
 *    come back. "You cannot resume on an email alone" is the only thing
 *    standing between this feature and letting a stranger finish somebody
 *    else's signup with their own password.
 *  - **Passwordless eligibility.** An emailed code opens a session, so it must
 *    not become a way around two-factor or a ban.
 */
beforeEach(function () {
    Notification::fake();
});

/**
 * Walks steps 1 and 2 and hands back the resume cookie plus the code that was
 * actually mailed, so a test can start from "code accepted" without asserting
 * its way through the earlier steps again.
 *
 * @return array{cookie: string, code: string, pending: PendingRegistration}
 */
function startSignup(string $email = 'buyer@example.com'): array
{
    $response = test()->post(route('login.identify'), ['email' => $email]);

    /*
     * Decrypted on the way out and handed back as plaintext, because
     * `withCookie()` encrypts (and prefixes) whatever it is given — feeding it
     * the raw response value double-encrypts, and `resolve()` then quietly
     * finds nothing, which looks exactly like a broken guard.
     */
    $cookie = $response->getCookie(RegistrationCodeService::COOKIE)->getValue();

    // The plaintext code only ever exists in the notification — it is hashed
    // in the database, which is the point.
    $code = null;
    Notification::assertSentOnDemand(
        RegistrationCode::class,
        function (RegistrationCode $notification) use (&$code) {
            $code = $notification->code;

            return true;
        },
    );

    return [
        'cookie' => $cookie,
        'code' => $code,
        'pending' => PendingRegistration::firstWhere('email', $email),
    ];
}

// ── Identify: one field, three outcomes ────────────────────────────────

test('the front door renders', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/login/identify'));
});

test('/register is gone', function () {
    /*
     * Signup is not a separate screen any more, and Fortify's registration
     * feature is switched off — so both its routes must be absent, not merely
     * unlinked. A lingering `POST /register` would be a second way in that
     * skips the emailed code entirely.
     *
     * The POST answers 405 rather than 404 because `Route::fallback` at the
     * foot of routes/web.php is a GET-only catch-all: the URI therefore
     * resolves, but the method does not. Either way there is no endpoint —
     * what matters is that nothing handles it.
     */
    $this->get('/register')->assertNotFound();
    $this->post('/register', [])->assertStatus(405);

    expect(Route::has('register'))->toBeFalse()
        ->and(Route::has('register.store'))->toBeFalse();
});

test('a known address is routed to the password screen', function () {
    $user = User::factory()->create(['email' => 'known@example.com']);

    $this->post(route('login.identify'), ['email' => 'known@example.com'])
        ->assertRedirect(route('login.password'))
        ->assertSessionHas('login.identified_email', $user->email);

    // No signup started, no code sent — this is the sign-in branch.
    expect(PendingRegistration::count())->toBe(0);
    Notification::assertNothingSent();
});

test('a known address is matched regardless of the case it is typed in', function () {
    User::factory()->create(['email' => 'known@example.com']);

    /*
     * `fortify.lowercase_usernames` is on, so accounts are stored lowercased.
     * Comparing raw input would send someone who capitalised their address
     * down the signup branch and then collide on the unique index at the end
     * of it.
     */
    $this->post(route('login.identify'), ['email' => 'Known@Example.COM'])
        ->assertRedirect(route('login.password'));

    expect(PendingRegistration::count())->toBe(0);
});

test('submitting an email stores a pending registration and mails a code', function () {
    $this->post(route('login.identify'), ['email' => 'buyer@example.com'])
        ->assertRedirect(route('login.verify'))
        ->assertCookie(RegistrationCodeService::COOKIE);

    $pending = PendingRegistration::firstWhere('email', 'buyer@example.com');

    expect($pending)->not->toBeNull()
        ->and($pending->verified_at)->toBeNull()
        ->and($pending->attempts)->toBe(0);

    Notification::assertSentOnDemand(RegistrationCode::class);

    // No account yet — that is the entire difference from the old flow.
    expect(User::where('email', 'buyer@example.com')->exists())->toBeFalse();
});

test('the code is stored hashed, never in the clear', function () {
    ['code' => $code, 'pending' => $pending] = startSignup();

    expect($pending->code_hash)->not->toBe($code)
        ->and(Hash::check($code, $pending->code_hash))->toBeTrue();
});

test('submitting the same email twice refreshes the one row rather than colliding', function () {
    $this->post(route('login.identify'), ['email' => 'buyer@example.com']);
    $this->post(route('login.identify'), ['email' => 'buyer@example.com']);

    expect(PendingRegistration::where('email', 'buyer@example.com')->count())->toBe(1);
});

test('re-entering an email voids the previous code and issues a fresh one', function () {
    ['code' => $first, 'cookie' => $cookie] = startSignup();

    // Pass the first code, so there is a verified step to lose.
    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->post(route('login.verify.store'), ['code' => $first]);

    expect(PendingRegistration::first()->verified_at)->not->toBeNull();

    /*
     * Typing the address in again always costs a fresh trip to the inbox and
     * voids the previous proof — the agreed behaviour. Only an unexpired
     * resume *cookie* skips the code, never the email path.
     */
    $this->post(route('login.identify'), ['email' => 'buyer@example.com']);

    $pending = PendingRegistration::first();

    expect($pending->verified_at)->toBeNull()
        ->and(Hash::check($first, $pending->code_hash))->toBeFalse();

    Notification::assertSentOnDemandTimes(RegistrationCode::class, 2);
});

// ── Step 2: the code ────────────────────────────────────────────────────

test('the right code advances to the details step', function () {
    ['cookie' => $cookie, 'code' => $code] = startSignup();

    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->post(route('login.verify.store'), ['code' => $code])
        ->assertRedirect(route('login.details'));

    expect(PendingRegistration::first()->verified_at)->not->toBeNull();
});

test('a wrong code is rejected and counted', function () {
    ['cookie' => $cookie, 'code' => $code] = startSignup();

    $wrong = str_pad((string) ((((int) $code) + 1) % 1000000), 6, '0', STR_PAD_LEFT);

    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->post(route('login.verify.store'), ['code' => $wrong])
        ->assertSessionHasErrors('code');

    $pending = PendingRegistration::first();

    expect($pending->attempts)->toBe(1)
        ->and($pending->verified_at)->toBeNull();
});

test('a code stops working after too many wrong guesses', function () {
    ['cookie' => $cookie, 'code' => $code] = startSignup();

    PendingRegistration::first()->update([
        'attempts' => RegistrationCodeService::MAX_ATTEMPTS,
    ]);

    // Even the *correct* code is refused once the counter is spent —
    // otherwise the limit would only slow an attacker down, not stop them.
    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->post(route('login.verify.store'), ['code' => $code])
        ->assertSessionHasErrors('code');

    expect(PendingRegistration::first()->verified_at)->toBeNull();
});

test('an expired code sends the visitor back to the start', function () {
    ['cookie' => $cookie, 'code' => $code] = startSignup();

    PendingRegistration::first()->update(['expires_at' => now()->subMinute()]);

    /*
     * Back to step 1, not "wrong code": an expired row is indistinguishable
     * from no row at all as far as resuming goes, and dropping them on the
     * code screen with a dead code would be a dead end.
     */
    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->post(route('login.verify.store'), ['code' => $code])
        ->assertRedirect(route('login'));
});

test('resend is refused while the cooldown is running', function () {
    ['cookie' => $cookie] = startSignup();

    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->post(route('login.resend'))
        ->assertSessionHasErrors('code');

    // One mail from startSignup, and none from the refused resend.
    Notification::assertSentOnDemandTimes(RegistrationCode::class, 1);
});

test('resend mails a fresh code once the cooldown has passed', function () {
    ['cookie' => $cookie, 'code' => $original] = startSignup();

    PendingRegistration::first()->update([
        'last_sent_at' => now()->subSeconds(
            RegistrationCodeService::RESEND_COOLDOWN_SECONDS + 1,
        ),
        'attempts' => 3,
    ]);

    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->post(route('login.resend'))
        ->assertSessionHas('success');

    Notification::assertSentOnDemandTimes(RegistrationCode::class, 2);

    $pending = PendingRegistration::first();

    // The old code must stop working, and the attempt counter has to reset or
    // a visitor who mistyped the previous code could never use the new one.
    expect(Hash::check($original, $pending->code_hash))->toBeFalse()
        ->and($pending->attempts)->toBe(0);
});

// ── Resuming ────────────────────────────────────────────────────────────

test('the code screen is skipped for a signup that already passed it', function () {
    ['cookie' => $cookie, 'code' => $code] = startSignup();

    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->post(route('login.verify.store'), ['code' => $code]);

    // Coming back to step 2 after closing the tab: forward, not round again.
    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->get(route('login.verify'))
        ->assertRedirect(route('login.details'));
});

test('the details screen bounces back to the code screen until the code is passed', function () {
    ['cookie' => $cookie] = startSignup();

    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->get(route('login.details'))
        ->assertRedirect(route('login.verify'));
});

test('the details screen renders for a verified signup', function () {
    ['cookie' => $cookie, 'code' => $code] = startSignup();

    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->post(route('login.verify.store'), ['code' => $code]);

    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->get(route('login.details'))
        ->assertOk()
        // No `email` prop any more — the screen no longer displays the
        // confirmed address, and nothing on it needs to.
        ->assertInertia(fn ($page) => $page->component('auth/login/details'));
});

test('step 1 offers to resume a signup this browser already started', function () {
    ['cookie' => $cookie] = startSignup();

    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->get(route('login'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page->component('auth/login/identify')
                ->where('resume.step', 'verify')
                // Masked, because this page can be reopened days later.
                ->where('resume.maskedEmail', 'bu•••@example.com'),
        );
});

test('without the resume cookie there is nothing to resume', function () {
    startSignup();

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('resume', null));

    $this->get(route('login.verify'))->assertRedirect(route('login'));
    $this->get(route('login.details'))->assertRedirect(route('login'));
});

/**
 * The reason resumption is keyed on the cookie's secret and not on the email.
 *
 * Someone who knows a pending address must not be able to walk up to step 3
 * and finish that signup with a password of their choosing — that would be
 * taking over an inbox they never proved they can read.
 */
test('a stranger cannot reach the details step for somebody else\'s pending signup', function () {
    startSignup('victim@example.com');

    /*
     * Stamped directly rather than by posting the code, for two reasons: the
     * guard is what's under test, not the code check — and `withCookie()`
     * persists on the test client for the rest of the test, so walking the
     * victim through step 2 over HTTP would leave their resume cookie behind
     * and the "stranger" below would not be one.
     */
    PendingRegistration::firstWhere('email', 'victim@example.com')
        ->update(['verified_at' => now()]);

    // A different browser: no cookie, and knowing the address buys nothing.
    $this->get(route('login.details'))->assertRedirect(route('login'));

    $this->post(route('login.details.store'), detailsPayload([
        'first_name' => 'Mallory',
        'phone_number' => '0600000009',
    ]))->assertRedirect(route('login'));

    expect(User::where('email', 'victim@example.com')->exists())->toBeFalse();
});

test('a forged resume cookie is ignored', function () {
    startSignup();

    $this->withCookie(RegistrationCodeService::COOKIE, 'not-a-real-token')
        ->get(route('login.details'))
        ->assertRedirect(route('login'));
});

// ── Step 3: the account ─────────────────────────────────────────────────

/** Walks steps 1 and 2 so a test can post straight at step 3. */
function verifiedSignup(string $email = 'buyer@example.com'): string
{
    ['cookie' => $cookie, 'code' => $code] = startSignup($email);

    test()->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->post(route('login.verify.store'), ['code' => $code]);

    return $cookie;
}

/**
 * The whole point of the fast-signup flow: nothing here but a password and
 * consent. `first_name`/`last_name`/`phone_number` are deliberately absent —
 * that is what the real /login/details form sends, and RegistrationController
 * no longer requires any of them for a buyer.
 */
function detailsPayload(array $overrides = []): array
{
    return array_merge([
        'password' => 'Password!234',
        'password_confirmation' => 'Password!234',
        'terms_accepted' => true,
    ], $overrides);
}

test('the final step creates an already-verified buyer with no profile yet, and signs them in', function () {
    $cookie = verifiedSignup();

    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->post(route('login.details.store'), detailsPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('onboarding.show'));

    $this->assertAuthenticated();

    $user = User::firstWhere('email', 'buyer@example.com');

    expect($user)->not->toBeNull()
        ->and($user->role)->toBe('buyer')
        // Nothing was asked for beyond the password, so nothing is set yet —
        // Buyer\ProfileController is where these get filled in later.
        ->and($user->first_name)->toBeNull()
        ->and($user->last_name)->toBeNull()
        ->and($user->phone_number)->toBeNull()
        ->and($user->terms_accepted)->toBeTruthy()
        // The code *was* the proof of inbox access — asking for a second
        // round-trip through an emailed link would be the same check twice.
        ->and($user->email_verified_at)->not->toBeNull();

    // The pending row is spent.
    expect(PendingRegistration::count())->toBe(0);
});

/**
 * The endpoint still accepts a name and a phone number when they are sent —
 * the artisan branch of this same controller action requires them — a buyer
 * client is simply not obliged to send them any more.
 */
test('a buyer may still supply a name and phone if they want to', function () {
    $cookie = verifiedSignup();

    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->post(route('login.details.store'), detailsPayload([
            'first_name' => 'Achraf',
            'last_name' => 'Ess',
            'phone_number' => '0600000001',
        ]))
        ->assertSessionHasNoErrors();

    $user = User::firstWhere('email', 'buyer@example.com');

    expect($user->first_name)->toBe('Achraf')
        ->and($user->last_name)->toBe('Ess')
        ->and($user->phone_number)->toBe('0600000001');
});

test('the terms must be accepted', function () {
    $cookie = verifiedSignup();

    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->post(route('login.details.store'), detailsPayload([
            'terms_accepted' => false,
        ]))
        ->assertSessionHasErrors('terms_accepted');

    $this->assertGuest();
});

test('a duplicate phone number is rejected instead of hitting the database', function () {
    User::factory()->create(['phone_number' => '0600000000']);

    $cookie = verifiedSignup();

    /*
     * users.phone_number carries a UNIQUE index. Without a matching `unique`
     * validation rule the insert reaches the database and throws a
     * QueryException — a 500 on the last step of signup rather than a field
     * error next to the phone input.
     */
    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->post(route('login.details.store'), detailsPayload([
            'phone_number' => '0600000000',
        ]))
        ->assertSessionHasErrors('phone_number');

    $this->assertGuest();
    expect(User::where('email', 'buyer@example.com')->exists())->toBeFalse();
});

/**
 * The role is fixed in the service, not taken from the request. Posting one
 * anyway must not promote the account — the old form's `role` field is not
 * part of this endpoint's contract.
 */
test('a role cannot be smuggled into the final step', function () {
    $cookie = verifiedSignup();

    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->post(route('login.details.store'), detailsPayload([
            'role' => 'super_admin',
        ]));

    expect(User::firstWhere('email', 'buyer@example.com')->role)->toBe('buyer');
});

// ── Signing in: the password screen ────────────────────────────────────

/** Identifies as an existing account, leaving the session key behind. */
function identifyAs(User $user): void
{
    test()->post(route('login.identify'), ['email' => $user->email]);
}

test('the password screen renders for an identified account', function () {
    $user = User::factory()->create(['email' => 'known@example.com']);
    identifyAs($user);

    $this->get(route('login.password'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page->component('auth/login/password')
                ->where('email', 'known@example.com')
                ->where('canUseCode', true),
        );
});

test('the password screen and code screen need an identified account', function () {
    // No session key — nothing has said who this is, so there is nothing to
    // ask a password for.
    $this->get(route('login.password'))->assertRedirect(route('login'));
    $this->get(route('login.code'))->assertRedirect(route('login'));
    $this->post(route('login.code'))->assertRedirect(route('login'));
    $this->post(route('login.code.verify'), ['code' => '123456'])
        ->assertRedirect(route('login'));
});

// ── Signing in: the emailed code ───────────────────────────────────────

/**
 * Identifies as `$user`, asks for a login code and returns the one that was
 * actually mailed.
 */
function requestLoginCode(User $user): string
{
    identifyAs($user);
    test()->post(route('login.code.send'));

    $code = null;
    Notification::assertSentTo(
        $user,
        LoginCodeNotification::class,
        function (LoginCodeNotification $notification) use (&$code) {
            $code = $notification->code;

            return true;
        },
    );

    return $code;
}

test('a login code signs an existing account in', function () {
    $user = User::factory()->create(['email' => 'known@example.com', 'role' => 'buyer']);

    $code = requestLoginCode($user);

    $this->post(route('login.code.verify'), ['code' => $code])
        // Lands where a password sign-in would — LoginResponse, reused rather
        // than reimplemented.
        ->assertRedirect(route('products.index'));

    $this->assertAuthenticatedAs($user);

    // Spent: a code that opened a session must not open a second one.
    expect(LoginCode::count())->toBe(0);
});

test('a login code is stored hashed and a wrong one is counted', function () {
    $user = User::factory()->create();
    $code = requestLoginCode($user);

    $row = LoginCode::firstWhere('user_id', $user->id);
    expect($row->code_hash)->not->toBe($code)
        ->and(Hash::check($code, $row->code_hash))->toBeTrue();

    $wrong = str_pad((string) ((((int) $code) + 1) % 1000000), 6, '0', STR_PAD_LEFT);

    $this->post(route('login.code.verify'), ['code' => $wrong])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
    expect(LoginCode::firstWhere('user_id', $user->id)->attempts)->toBe(1);
});

test('an exhausted login code stops working even when correct', function () {
    $user = User::factory()->create();
    $code = requestLoginCode($user);

    LoginCode::firstWhere('user_id', $user->id)
        ->update(['attempts' => LoginCodeService::MAX_ATTEMPTS]);

    $this->post(route('login.code.verify'), ['code' => $code])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('an expired login code is refused', function () {
    $user = User::factory()->create();
    $code = requestLoginCode($user);

    LoginCode::firstWhere('user_id', $user->id)
        ->update(['expires_at' => now()->subMinute()]);

    $this->post(route('login.code.verify'), ['code' => $code])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('asking for a second login code is a no-op while the cooldown runs', function () {
    $user = User::factory()->create();
    requestLoginCode($user);

    $this->post(route('login.code.send'))->assertRedirect(route('login.code'));

    Notification::assertSentToTimes($user, LoginCodeNotification::class, 1);
});

/**
 * The reason the code path re-checks eligibility on every action instead of
 * trusting the password screen not to have offered it.
 *
 * A code mailed to the address *is* a single factor. If it alone could
 * authenticate, the second factor would be optional in practice for anyone who
 * knows the email — which is the entire population 2FA exists to protect
 * against.
 */
test('two-factor accounts cannot sign in with a code', function () {
    $user = User::factory()->create([
        'email' => 'secure@example.com',
        'two_factor_confirmed_at' => now(),
    ]);

    identifyAs($user);

    $this->get(route('login.password'))
        ->assertOk()
        // Not even offered — a button guaranteed to fail is worse than none.
        ->assertInertia(fn ($page) => $page->where('canUseCode', false));

    $this->post(route('login.code.send'))
        ->assertRedirect(route('login.password'))
        ->assertSessionHas('error');

    $this->get(route('login.code'))->assertRedirect(route('login.password'));

    $this->assertGuest();
    expect(LoginCode::count())->toBe(0);
    Notification::assertNothingSent();
});

/**
 * `is_banned` is written by Admin\UserController::ban and read nowhere else —
 * a banned account can still sign in with its password today. This pins that
 * the *new* path at least declines to be a second way through.
 */
test('banned accounts cannot sign in with a code', function () {
    $user = User::factory()->create([
        'email' => 'banned@example.com',
        'is_banned' => true,
    ]);

    identifyAs($user);

    $this->get(route('login.password'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canUseCode', false));

    $this->post(route('login.code.send'))
        ->assertRedirect(route('login.password'));

    $this->assertGuest();
    Notification::assertNothingSent();
});

// ── The two non-shopper doors ───────────────────────────────────────────

/*
 * The routes and names here are what the cards on /login link to, so a rename
 * breaks the front door silently. Pinned.
 */
test('the artisan door renders', function () {
    $this->get(route('sell'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/seller/identify'));
});

test('the trade door renders', function () {
    $this->get(route('business'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('join/business'));
});
