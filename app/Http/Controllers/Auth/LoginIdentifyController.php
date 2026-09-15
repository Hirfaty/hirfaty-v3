<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LoginCodeService;
use App\Services\RegistrationCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The front door: one email field, three possible next screens.
 *
 * Identifier-first auth — ask who you are, *then* decide what to ask for.
 * Replaced the old pair of screens where /login wanted a password and
 * /register wanted an email, and a visitor had to know which of the two they
 * belonged at (and /register had to bounce existing addresses over to /login
 * anyway).
 *
 * | Typed email                    | Goes to                                |
 * | ------------------------------ | -------------------------------------- |
 * | Has a real account             | `login.password` — password, code as a fallback |
 * | Signup started but unfinished  | `login.verify` — a *fresh* code        |
 * | Unknown                        | `login.verify` — a fresh code, then profile completion |
 *
 * Note this discloses which addresses have accounts, which is inherent to
 * identifier-first and is a deliberate trade, not an oversight: the old
 * `unique:users` rule on the signup form leaked exactly the same fact. The
 * mitigation is the throttle on this route, not pretending otherwise — so
 * please don't "fix" it by making the branches indistinguishable, which would
 * break the entire premise of the screen.
 */
class LoginIdentifyController extends Controller
{
    public function __construct(
        private readonly RegistrationCodeService $codes,
        private readonly LoginCodeService $loginCodes,
    ) {}

    /**
     * Decide which screen this address belongs on.
     */
    public function identify(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        /*
         * Lowercased to match `fortify.lowercase_usernames`, which is on: the
         * account was stored lowercased, so comparing the raw input would send
         * someone who capitalised their address down the signup branch and
         * then collide on the unique index at the end of it.
         */
        $email = mb_strtolower(trim($validated['email']));

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user) {
            /*
             * Held in the session rather than a cookie. This is a short hop
             * between two screens, so it should die with the browser session —
             * a persistent cookie naming a real account on a shared machine
             * buys nothing and discloses something.
             */
            $request->session()->put(LoginCodeService::SESSION_KEY, $user->email);

            return to_route('login.password');
        }

        /*
         * No account: signup. `start()` is used even when a pending row
         * already exists, because it reissues the code *and* clears
         * `verified_at` — so typing your address in again always costs you a
         * fresh trip to the inbox and voids the previous proof. That is the
         * agreed behaviour; the only thing that skips the code is an
         * unexpired resume cookie, handled in RegistrationController.
         */
        $token = $this->codes->start($email, app()->getLocale());

        return to_route('login.verify')
            ->withCookie($this->codes->resumeCookie($token));
    }

    /**
     * The password screen for an account we have already identified.
     *
     * Submits to Fortify's `POST /login`, not to anything here — that is
     * where the login throttle, session regeneration, the two-factor handoff
     * and LoginResponse's per-role landing all live.
     */
    public function showPassword(Request $request): Response|RedirectResponse
    {
        $user = $this->identified($request);

        if (! $user) {
            return $this->restart();
        }

        return Inertia::render('auth/login/password', [
            'email' => $user->email,
            // Drives whether the "email me a code instead" link is offered at
            // all. Rendering a button that is guaranteed to fail — for a
            // two-factor or banned account — is worse than not offering it.
            'canUseCode' => $this->loginCodes->isEligible($user),
        ]);
    }

    /**
     * The account named by the session, or null if there isn't one.
     *
     * Re-read from the database on every request rather than trusted from the
     * session: the address is the only thing stored, so a ban or a 2FA change
     * between screens is picked up rather than cached.
     */
    private function identified(Request $request): ?User
    {
        $email = $request->session()->get(LoginCodeService::SESSION_KEY);

        if (! is_string($email) || $email === '') {
            return null;
        }

        return User::whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first();
    }

    /** Nothing identified — back to the email field. */
    private function restart(): RedirectResponse
    {
        return to_route('login')
            ->with('error', __('common.auth.login.identify_again'));
    }
}
