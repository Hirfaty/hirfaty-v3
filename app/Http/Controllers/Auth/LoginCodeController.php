<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Responses\LoginResponse;
use App\Models\User;
use App\Services\LoginCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Email me a code instead" — passwordless sign-in for an account that
 * already exists.
 *
 * The secondary path off the password screen. It doubles as a gentler
 * recovery route than a full password reset: someone who cannot remember
 * their password can still get in, without changing it.
 *
 * Every action re-checks eligibility through `LoginCodeService::isEligible`
 * rather than trusting that the screen only offered the option to eligible
 * accounts — the screen is not the security boundary, and a hand-rolled POST
 * skips it entirely. That check is what keeps this from becoming a two-factor
 * bypass; see the service for why.
 */
class LoginCodeController extends Controller
{
    public function __construct(
        private readonly LoginCodeService $codes,
    ) {}

    /**
     * Mail a code to the identified account.
     */
    public function send(Request $request): RedirectResponse
    {
        $user = $this->identified($request);

        if (! $user) {
            return $this->restart();
        }

        if (! $this->codes->isEligible($user)) {
            return $this->refuse();
        }

        if (! $this->codes->send($user)) {
            // Cooldown still running. Not an error worth a red banner — the
            // code they already have is still good.
            return to_route('login.code');
        }

        return to_route('login.code')
            ->with('success', __('common.auth.login.code_sent'));
    }

    /**
     * The code screen.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $this->identified($request);

        if (! $user) {
            return $this->restart();
        }

        if (! $this->codes->isEligible($user)) {
            return $this->refuse();
        }

        $outstanding = $this->codes->outstandingFor($user);

        // Nothing sent yet (someone deep-linked here) — send one rather than
        // showing a code screen with no code on its way.
        if (! $outstanding) {
            return to_route('login.code.send');
        }

        return Inertia::render('auth/login/code', [
            'maskedEmail' => $this->mask($user->email),
            'resendIn' => $this->codes->secondsUntilResend($outstanding),
            'locked' => $this->codes->isLocked($outstanding),
        ]);
    }

    /**
     * Check the code and, if it is right, sign them in.
     */
    public function verify(Request $request): RedirectResponse
    {
        $user = $this->identified($request);

        if (! $user) {
            return $this->restart();
        }

        if (! $this->codes->isEligible($user)) {
            return $this->refuse();
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ]);

        if (! $this->codes->verify($user, $validated['code'])) {
            $outstanding = $this->codes->outstandingFor($user);

            return back()->withErrors([
                'code' => $outstanding && $this->codes->isLocked($outstanding)
                    ? __('common.auth.login.code_locked')
                    : __('common.auth.login.code_invalid'),
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        // The session key has done its job and named a real account; it should
        // not outlive the sign-in it authorised.
        $request->session()->forget(LoginCodeService::SESSION_KEY);

        /*
         * Reuses Fortify's response contract so a code sign-in lands exactly
         * where a password sign-in would — admins on the moderation queue,
         * sellers in their shop, buyers in the market. Two "where do I end
         * up" implementations would drift.
         */
        return app(LoginResponse::class)->toResponse($request);
    }

    /**
     * The account named by the session, re-read each request so a ban or a
     * two-factor change between screens is noticed rather than cached.
     */
    private function identified(Request $request): ?User
    {
        $email = $request->session()->get(LoginCodeService::SESSION_KEY);

        if (! is_string($email) || $email === '') {
            return null;
        }

        return User::whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first();
    }

    /**
     * `ac•••@example.com` — enough to confirm they typed what they meant,
     * without printing a full address onto a screen that may be read over a
     * shoulder. Mirrors PendingRegistration::maskedEmail.
     */
    private function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        $visible = mb_substr($local, 0, min(2, max(1, mb_strlen($local) - 1)));

        return $visible.str_repeat('•', 3).'@'.$domain;
    }

    /** Ineligible for a code — say so on the password screen, not here. */
    private function refuse(): RedirectResponse
    {
        return to_route('login.password')
            ->with('error', __('common.auth.login.code_unavailable'));
    }

    /** Nothing identified — back to the email field. */
    private function restart(): RedirectResponse
    {
        return to_route('login')
            ->with('error', __('common.auth.login.identify_again'));
    }
}
