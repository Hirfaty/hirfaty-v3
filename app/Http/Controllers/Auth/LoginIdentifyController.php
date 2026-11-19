<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\ContactSignInService;
use App\Services\LoginCodeService;
use App\Services\RegistrationCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The front door: one "email or phone" field, for signing in *and* up.
 *
 * Identifier-first and passwordless — ask who you are, then send a code:
 *
 * | Typed                            | Goes to                                  |
 * | -------------------------------- | ---------------------------------------- |
 * | An account (shopper, artisan)    | `login.code` — a code, then signed in    |
 * | An admin or two-factor account   | `login.password` — password (+ 2FA)      |
 * | Unknown                          | `login.verify` — a code, then the account is made |
 *
 * The decision itself lives in ContactSignInService, which the checkout's
 * contact box shares.
 *
 * Note this discloses which addresses have accounts, which is inherent to
 * identifier-first and is a deliberate trade, not an oversight. The
 * mitigation is the throttle on this route, not pretending otherwise.
 */
class LoginIdentifyController extends Controller
{
    public function __construct(
        private readonly ContactSignInService $signIn,
        private readonly RegistrationCodeService $codes,
        private readonly LoginCodeService $loginCodes,
    ) {}

    /**
     * Decide which screen this address or number belongs on.
     */
    public function identify(Request $request): RedirectResponse
    {
        $contact = $this->signIn->parse($request);

        $result = $this->signIn->begin($request, $contact);

        return match ($result['outcome']) {
            ContactSignInService::CODE => to_route('login.code'),
            ContactSignInService::PASSWORD => to_route('login.password'),
            default => to_route('login.verify')
                ->withCookie($this->codes->resumeCookie($result['token'])),
        };
    }

    /**
     * The password screen — admins, two-factor accounts, and anyone who
     * chose "use my password instead" on the code screen.
     *
     * Submits to Fortify's `POST /login`, not to anything here — that is
     * where the login throttle, session regeneration, the two-factor handoff
     * and LoginResponse's per-role landing all live.
     */
    public function showPassword(Request $request): Response|RedirectResponse
    {
        $user = $this->loginCodes->identifiedUser($request);

        // Fortify signs in by address: an account without one, or without a
        // password, has nothing to type here.
        if (! $user || ! $user->email || ! $user->hasPassword()) {
            return $this->restart();
        }

        return Inertia::render('auth/login/password', [
            'email' => $user->email,
            // Drives whether the "send me a code instead" link is offered at
            // all. Rendering a button that is guaranteed to fail — for an
            // admin, a two-factor or banned account — is worse than not
            // offering it.
            'canUseCode' => $this->loginCodes->isEligible($user),
        ]);
    }

    /** Nothing identified — back to the field. */
    private function restart(): RedirectResponse
    {
        return to_route('login')
            ->with('error', __('common.auth.login.identify_again'));
    }
}
