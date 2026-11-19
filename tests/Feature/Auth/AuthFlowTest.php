<?php

use App\Models\LoginCode;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Notifications\LoginCodeNotification;
use App\Notifications\RegistrationCode;
use App\Services\LoginCodeService;
use App\Services\RegistrationCodeService;
use App\Services\SmsService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;

/**
 * The unified /login flow — one "email or phone" field, and a code.
 *
 * There is no /register and no password for shoppers: whatever is typed gets
 * a 6-digit code, to sign in to the account it belongs to or to make one (see
 * Auth\LoginIdentifyController). Only admins and two-factor accounts keep the
 * password screen. Three families of guard are pinned here:
 *
 *  - **Resume.** "You cannot resume on an email alone" is what stops a
 *    stranger finishing somebody else's signup.
 *  - **Eligibility.** A code opens a session, so it must not become a way
 *    around two-factor, a ban or an admin's password.
 *  - **Phones.** Only a number proven by an SMS code signs anyone in.
 */
beforeEach(function () {
    Notification::fake();
});

/**
 * Starts a signup and hands back the resume cookie plus the code that was
 * actually mailed.
 *
 * @return array{cookie: string, code: string, pending: PendingRegistration}
 */
function startSignup(string $email = 'buyer@example.com'): array
{
    $response = test()->post(route('login.identify'), ['identifier' => $email]);

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

// ── Identify: one field ─────────────────────────────────────────────────

test('the front door renders', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/login/identify'));
});

test('/register is gone', function () {
    /*
     * Fortify's registration feature is switched off — so both its routes
     * must be absent, not merely unlinked. A lingering `POST /register` would
     * be a second way in that skips the code entirely.
     *
     * The POST answers 405 rather than 404 because `Route::fallback` at the
     * foot of routes/web.php is a GET-only catch-all.
     */
    $this->get('/register')->assertNotFound();
    $this->post('/register', [])->assertStatus(405);

    expect(Route::has('register'))->toBeFalse()
        ->and(Route::has('register.store'))->toBeFalse();
});

test('a known address gets a sign-in code, not a password screen', function () {
    $user = User::factory()->create(['email' => 'known@example.com']);

    $this->post(route('login.identify'), ['identifier' => 'known@example.com'])
        ->assertRedirect(route('login.code'))
        ->assertSessionHas(LoginCodeService::SESSION_KEY, 'known@example.com');

    // A sign-in, not a signup.
    expect(PendingRegistration::count())->toBe(0);
    Notification::assertSentTo($user, LoginCodeNotification::class);
});

test('a known address is matched regardless of the case it is typed in', function () {
    User::factory()->create(['email' => 'known@example.com']);

    /*
     * `fortify.lowercase_usernames` is on, so accounts are stored lowercased.
     * Comparing raw input would send someone who capitalised their address
     * down the signup branch and then collide on the unique index.
     */
    $this->post(route('login.identify'), ['identifier' => 'Known@Example.COM'])
        ->assertRedirect(route('login.code'));

    expect(PendingRegistration::count())->toBe(0);
});

test('an admin is sent to the password screen and never gets a code', function () {
    $admin = User::factory()->create(['email' => 'desk@example.com', 'role' => 'super_admin']);

    $this->post(route('login.identify'), ['identifier' => 'desk@example.com'])
        ->assertRedirect(route('login.password'));

    $this->get(route('login.password'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canUseCode', false));

    $this->post(route('login.code.send'))->assertRedirect(route('login.password'));

    expect(LoginCode::count())->toBe(0);
    Notification::assertNotSentTo($admin, LoginCodeNotification::class);
});

test('something that is neither an address nor a number is refused under the field', function () {
    $this->post(route('login.identify'), ['identifier' => 'ahmed.gmail.com'])
        ->assertSessionHasErrors('identifier');

    $this->post(route('login.identify'), ['identifier' => '06 12'])
        ->assertSessionHasErrors('identifier');

    expect(PendingRegistration::count())->toBe(0);
    Notification::assertNothingSent();
});

test('submitting a new email stores a pending registration and mails a code', function () {
    $this->post(route('login.identify'), ['identifier' => 'buyer@example.com'])
        ->assertRedirect(route('login.verify'))
        ->assertCookie(RegistrationCodeService::COOKIE);

    $pending = PendingRegistration::firstWhere('email', 'buyer@example.com');

    expect($pending)->not->toBeNull()
        ->and($pending->verified_at)->toBeNull()
        ->and($pending->attempts)->toBe(0);

    Notification::assertSentOnDemand(RegistrationCode::class);

    // No account until the code comes back.
    expect(User::where('email', 'buyer@example.com')->exists())->toBeFalse();
});

test('the code is stored hashed, never in the clear', function () {
    ['code' => $code, 'pending' => $pending] = startSignup();

    expect($pending->code_hash)->not->toBe($code)
        ->and(Hash::check($code, $pending->code_hash))->toBeTrue();
});

test('submitting the same email twice refreshes the one row rather than colliding', function () {
    $this->post(route('login.identify'), ['identifier' => 'buyer@example.com']);
    $this->post(route('login.identify'), ['identifier' => 'buyer@example.com']);

    expect(PendingRegistration::where('email', 'buyer@example.com')->count())->toBe(1);
});

test('re-entering an email voids the previous code and issues a fresh one', function () {
    ['code' => $first] = startSignup();

    $this->post(route('login.identify'), ['identifier' => 'buyer@example.com']);

    $pending = PendingRegistration::first();

    expect(Hash::check($first, $pending->code_hash))->toBeFalse();

    Notification::assertSentOnDemandTimes(RegistrationCode::class, 2);
});

test('codes to one address are capped per hour', function () {
    foreach (range(1, 6) as $ignored) {
        $this->post(route('login.identify'), ['identifier' => 'buyer@example.com'])
            ->assertSessionHasNoErrors();
    }

    $this->post(route('login.identify'), ['identifier' => 'buyer@example.com'])
        ->assertSessionHasErrors('identifier');

    Notification::assertSentOnDemandTimes(RegistrationCode::class, 6);
});

// ── The code makes the account ─────────────────────────────────────────

test('the right code creates a verified buyer with no password and signs them in', function () {
    ['cookie' => $cookie, 'code' => $code] = startSignup();

    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->post(route('login.verify.store'), ['code' => $code])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('onboarding.show'));

    $this->assertAuthenticated();

    $user = User::firstWhere('email', 'buyer@example.com');

    expect($user)->not->toBeNull()
        ->and($user->role)->toBe('buyer')
        ->and($user->password)->toBeNull()
        ->and($user->hasPassword())->toBeFalse()
        ->and($user->terms_accepted)->toBeTruthy()
        // The code *was* the proof of inbox access.
        ->and($user->email_verified_at)->not->toBeNull();

    // The pending row is spent.
    expect(PendingRegistration::count())->toBe(0);
});

test('a role cannot be smuggled in with the code', function () {
    ['cookie' => $cookie, 'code' => $code] = startSignup();

    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->post(route('login.verify.store'), ['code' => $code, 'role' => 'super_admin']);

    expect(User::firstWhere('email', 'buyer@example.com')->role)->toBe('buyer');
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

    $this->assertGuest();
});

test('a code stops working after too many wrong guesses', function () {
    ['cookie' => $cookie, 'code' => $code] = startSignup();

    PendingRegistration::first()->update([
        'attempts' => RegistrationCodeService::MAX_ATTEMPTS,
    ]);

    // Even the *correct* code is refused once the counter is spent.
    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->post(route('login.verify.store'), ['code' => $code])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('an expired code sends the visitor back to the start', function () {
    ['cookie' => $cookie, 'code' => $code] = startSignup();

    PendingRegistration::first()->update(['expires_at' => now()->subMinute()]);

    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->post(route('login.verify.store'), ['code' => $code])
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('resend is refused while the cooldown is running', function () {
    ['cookie' => $cookie] = startSignup();

    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->post(route('login.resend'))
        ->assertSessionHasErrors('code');

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

    expect(Hash::check($original, $pending->code_hash))->toBeFalse()
        ->and($pending->attempts)->toBe(0);
});

// ── Resuming ────────────────────────────────────────────────────────────

test('a verified shopper signup resumed later is finished, not asked again', function () {
    ['cookie' => $cookie] = startSignup();

    // Verified by the emailed link, say, but the account never made.
    PendingRegistration::first()->update(['verified_at' => now()]);

    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->get(route('login.verify'))
        ->assertRedirect(route('login.details'));

    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->get(route('login.details'))
        ->assertRedirect(route('onboarding.show'));

    $this->assertAuthenticated();
    expect(User::where('email', 'buyer@example.com')->exists())->toBeTrue();
});

test('the details step bounces back to the code screen until the code is passed', function () {
    ['cookie' => $cookie] = startSignup();

    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->get(route('login.details'))
        ->assertRedirect(route('login.verify'));

    $this->assertGuest();
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
                ->where('resume.maskedContact', 'bu•••@example.com'),
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
 * The reason resumption is keyed on the cookie's secret and not on the email:
 * knowing a pending address must not let a stranger finish that signup.
 */
test('a stranger cannot finish somebody else\'s pending signup', function () {
    startSignup('victim@example.com');

    PendingRegistration::firstWhere('email', 'victim@example.com')
        ->update(['verified_at' => now()]);

    // A different browser: no cookie, and knowing the address buys nothing.
    $this->get(route('login.details'))->assertRedirect(route('login'));
    $this->post(route('login.details.store'), [])->assertRedirect(route('login'));

    $this->assertGuest();
    expect(User::where('email', 'victim@example.com')->exists())->toBeFalse();
});

test('a forged resume cookie is ignored', function () {
    startSignup();

    $this->withCookie(RegistrationCodeService::COOKIE, 'not-a-real-token')
        ->get(route('login.details'))
        ->assertRedirect(route('login'));
});

// ── Signing in with a code ─────────────────────────────────────────────

/**
 * Identifies as `$user` — which sends the sign-in code — and returns the code
 * that was actually mailed.
 */
function requestLoginCode(User $user): string
{
    test()->post(route('login.identify'), ['identifier' => $user->email]);

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

test('the code screen renders for an identified account', function () {
    $user = User::factory()->create(['email' => 'known@example.com', 'password' => null]);
    requestLoginCode($user);

    $this->get(route('login.code'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page->component('auth/login/code')
                ->where('channel', 'email')
                ->where('maskedContact', 'kn•••@example.com')
                // Never set one: nothing to fall back on.
                ->where('canUsePassword', false),
        );
});

test('an account that still has a password may use it instead', function () {
    $user = User::factory()->create(['email' => 'known@example.com']);
    requestLoginCode($user);

    $this->get(route('login.code'))
        ->assertInertia(fn ($page) => $page->where('canUsePassword', true));

    $this->get(route('login.password'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page->component('auth/login/password')
                ->where('email', 'known@example.com')
                ->where('canUseCode', true),
        );
});

test('the password screen and code screen need an identified account', function () {
    $this->get(route('login.password'))->assertRedirect(route('login'));
    $this->get(route('login.code'))->assertRedirect(route('login'));
    $this->post(route('login.code'))->assertRedirect(route('login'));
    $this->post(route('login.code.verify'), ['code' => '123456'])
        ->assertRedirect(route('login'));
});

test('a login code signs an existing account in', function () {
    $user = User::factory()->create(['email' => 'known@example.com', 'role' => 'buyer']);

    $code = requestLoginCode($user);

    $this->post(route('login.code.verify'), ['code' => $code])
        // Lands where a password sign-in would — LoginResponse, reused.
        ->assertRedirect(route('products.index'));

    $this->assertAuthenticatedAs($user);

    // Spent: a code that opened a session must not open a second one.
    expect(LoginCode::count())->toBe(0);
});

test('a login code sign-in returns them to the page that sent them to log in', function () {
    $user = User::factory()->create(['email' => 'known@example.com', 'role' => 'buyer']);

    $this->get(route('login', ['redirect' => '/fr/products/vase-bleu']));
    $code = requestLoginCode($user);

    $this->post(route('login.code.verify'), ['code' => $code])
        ->assertRedirect('/fr/products/vase-bleu');
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
 * A code mailed to the address is a single factor. If it alone could
 * authenticate, the second factor would be optional in practice.
 */
test('two-factor accounts cannot sign in with a code', function () {
    $user = User::factory()->create([
        'email' => 'secure@example.com',
        'two_factor_confirmed_at' => now(),
    ]);

    $this->post(route('login.identify'), ['identifier' => $user->email])
        ->assertRedirect(route('login.password'));

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

test('banned accounts cannot sign in with a code', function () {
    $user = User::factory()->create([
        'email' => 'banned@example.com',
        'is_banned' => true,
    ]);

    $this->post(route('login.identify'), ['identifier' => $user->email])
        ->assertRedirect(route('login.password'));

    $this->get(route('login.password'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canUseCode', false));

    $this->post(route('login.code.send'))
        ->assertRedirect(route('login.password'));

    $this->assertGuest();
    Notification::assertNothingSent();
});

// ── Phones ──────────────────────────────────────────────────────────────

test('a Moroccan number typed the national way signs up by SMS', function () {
    $sms = fakeSms();

    $response = $this->post(route('login.identify'), ['identifier' => '06 12 34 56 78'])
        ->assertRedirect(route('login.verify'));

    // Normalised to E.164, whatever way it was typed.
    $pending = PendingRegistration::firstWhere('phone', '+212612345678');
    expect($pending)->not->toBeNull()
        ->and($pending->email)->toBeNull();

    $code = $sms->codeTo('+212612345678');
    expect($code)->not->toBeNull();
    Notification::assertNothingSent();

    $cookie = $response->getCookie(RegistrationCodeService::COOKIE)->getValue();

    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->get(route('login.verify'))
        ->assertInertia(
            fn ($page) => $page->component('auth/login/verify')
                ->where('channel', 'phone')
                ->where('maskedContact', '+212 •••••••78'),
        );

    $this->withCookie(RegistrationCodeService::COOKIE, $cookie)
        ->post(route('login.verify.store'), ['code' => $code])
        ->assertSessionHasNoErrors();

    $user = User::firstWhere('phone_number', '+212612345678');

    expect($user)->not->toBeNull()
        ->and($user->email)->toBeNull()
        ->and($user->phone_verified_at)->not->toBeNull()
        ->and($user->hasVerifiedEmail())->toBeTrue();

    $this->assertAuthenticatedAs($user);
});

test('a verified number signs its account in, however it is written', function () {
    $sms = fakeSms();
    $user = User::factory()->create([
        'email' => null,
        'phone_number' => '+33612345678',
        'phone_verified_at' => now(),
        'role' => 'buyer',
    ]);

    $this->post(route('login.identify'), ['identifier' => '0033 6 12 34 56 78'])
        ->assertRedirect(route('login.code'));

    $code = $sms->codeTo('+33612345678');

    $this->post(route('login.code.verify'), ['code' => $code])
        ->assertRedirect(route('products.index'));

    $this->assertAuthenticatedAs($user);
});

test('an unverified number on an account opens nothing and starts nothing', function () {
    $sms = fakeSms();

    // Typed at checkout years ago, never proven — maybe mistyped.
    User::factory()->create(['phone_number' => '+212612345678', 'phone_verified_at' => null]);

    $this->post(route('login.identify'), ['identifier' => '0612345678'])
        ->assertSessionHasErrors('identifier');

    expect($sms->sent)->toBe([])
        ->and(LoginCode::count())->toBe(0)
        ->and(PendingRegistration::count())->toBe(0);
});

test('a refused text is reported under the field', function () {
    $sms = new class extends SmsService
    {
        public function available(): bool
        {
            return true;
        }

        public function send(string $to, string $text): void
        {
            throw new RuntimeException('provider down');
        }
    };
    app()->instance(SmsService::class, $sms);

    $this->post(route('login.identify'), ['identifier' => '0612345678'])
        ->assertSessionHasErrors('identifier');

    $this->assertGuest();
});

test('phone sign-in is refused in production until a real SMS driver is set', function () {
    config(['services.sms.driver' => 'log']);
    app()['env'] = 'production';

    expect(app(SmsService::class)->available())->toBeFalse();

    config(['services.sms.driver' => 'twilio']);

    expect(app(SmsService::class)->available())->toBeTrue();

    app()['env'] = 'testing';
});

// ── The two non-shopper doors ───────────────────────────────────────────

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

// ── Signing in with the email's link ───────────────────────────────────

/** Asks for a sign-in code for `$user` and returns the link that was mailed. */
function requestLoginLink(User $user): string
{
    test()->post(route('login.identify'), ['identifier' => $user->email]);

    $url = null;
    Notification::assertSentTo($user, LoginCodeNotification::class, function (LoginCodeNotification $n) use (&$url) {
        $url = $n->url;

        return true;
    });

    expect($url)->not->toBeNull();

    return $url;
}

test('the sign-in email carries a one-click link beside the code', function () {
    $user = User::factory()->create(['role' => 'buyer']);
    requestLoginLink($user);

    Notification::assertSentTo($user, LoginCodeNotification::class, function (LoginCodeNotification $n) use ($user) {
        $html = (string) $n->toMail($user)->render();

        return str_contains($html, e($n->url)) && str_contains($html, $n->code);
    });
});

test('opened in the browser that asked, the link signs in at once', function () {
    $user = User::factory()->create(['role' => 'buyer']);
    $link = requestLoginLink($user);

    $this->get($link)->assertRedirect(route('products.index'));

    $this->assertAuthenticatedAs($user);
    expect(LoginCode::count())->toBe(0);

    // The code tab, still polling: already signed in, so it just goes on.
    $this->get(route('login.code'))->assertRedirect(route('products.index'));
});

test('opened elsewhere, the link only asks — and confirming lets the asking window sign in', function () {
    $user = User::factory()->create(['role' => 'buyer']);
    $link = requestLoginLink($user);
    $asking = session()->all();

    // Another device: no session of the asking browser.
    $this->flushSession();

    $this->get($link)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/login/link')->where('confirmed', false));
    $this->assertGuest();

    $this->post($link)->assertRedirect($link);
    $this->assertGuest();
    expect(LoginCode::first()->verified_at)->not->toBeNull();

    // The asking browser's code screen, polling, signs in by itself.
    $this->flushSession();
    $this->withSession($asking)
        ->get(route('login.code'))
        ->assertRedirect(route('products.index'));

    $this->assertAuthenticatedAs($user);
    expect(LoginCode::count())->toBe(0);
});

test('"continue on this device" signs in where the link was opened', function () {
    $user = User::factory()->create(['role' => 'buyer']);
    $link = requestLoginLink($user);
    $this->flushSession();

    $this->post($link, ['here' => true])->assertRedirect(route('products.index'));

    $this->assertAuthenticatedAs($user);
});

test('a sign-in link dies with its code, and a resend retires it', function () {
    $user = User::factory()->create(['role' => 'buyer']);
    $first = requestLoginLink($user);

    $this->travel(LoginCodeService::RESEND_COOLDOWN_SECONDS + 1)->seconds();
    $this->post(route('login.code.send'));

    $this->flushSession();
    $this->get($first)->assertRedirect(route('login'))->assertSessionHas('error');

    $latest = null;
    Notification::assertSentTo($user, LoginCodeNotification::class, function ($n) use (&$latest) {
        $latest = $n->url;

        return true;
    });

    $this->travel(LoginCodeService::CODE_TTL_MINUTES + 1)->minutes();
    $this->post($latest, ['here' => true])->assertRedirect(route('login'));

    $this->assertGuest();
});

test('a tampered sign-in link is refused', function () {
    $user = User::factory()->create(['role' => 'buyer']);
    $link = requestLoginLink($user);
    $this->flushSession();

    $this->post($link.'x', ['here' => true])->assertRedirect(route('login'));
    $this->assertGuest();
});
