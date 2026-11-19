<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Responses\LoginResponse;
use App\Models\User;
use App\Services\LoginCodeService;
use App\Support\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Signing in to an account that exists: a code sent to the address or number
 * the visitor typed, then straight in. No password.
 *
 * Every action re-checks eligibility through `LoginCodeService::isEligible`
 * rather than trusting that the previous screen only sent eligible accounts
 * here — the screen is not the security boundary, and a hand-rolled POST
 * skips it entirely. That check is what keeps this from becoming a two-factor
 * or admin bypass; see the service for why.
 */
class LoginCodeController extends Controller
{
    public function __construct(
        private readonly LoginCodeService $codes,
    ) {}

    /**
     * Send a (fresh) code to the identified address or number.
     */
    public function send(Request $request): RedirectResponse
    {
        [$user, $contact] = $this->identified($request);

        if (! $user) {
            return $this->restart();
        }

        if (! $this->codes->isEligible($user)) {
            return $this->refuse($user);
        }

        try {
            $sent = $this->codes->send($user, $contact);
        } catch (RuntimeException $e) {
            report($e);

            return to_route('login')->with('error', __('common.auth.contact.send_failed'));
        }

        // Cooldown still running: not an error worth a red banner — the code
        // they already have is still good.
        return $sent
            ? to_route('login.code')->with('success', __('common.auth.login.code_sent'))
            : to_route('login.code');
    }

    /**
     * The code screen.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        [$user, $contact] = $this->identified($request);

        if (! $user) {
            return $this->restart();
        }

        if (! $this->codes->isEligible($user)) {
            return $this->refuse($user);
        }

        /*
         * The button in the email was pressed on another device: this screen,
         * polling, signs in here — where the person asked for the code.
         */
        if ($this->codes->consumeVerified($user)) {
            return $this->signIn($request, $user);
        }

        $outstanding = $this->codes->outstandingFor($user);

        // Nothing sent yet (someone deep-linked here) — send one rather than
        // showing a code screen with no code on its way.
        if (! $outstanding) {
            return $this->send($request);
        }

        return Inertia::render('auth/login/code', [
            'maskedContact' => $contact->masked(),
            'channel' => $contact->channel,
            'resendIn' => $this->codes->secondsUntilResend($outstanding),
            'locked' => $this->codes->isLocked($outstanding),
            // "Use my password instead" only for an account that has one and
            // an address to sign in with — most shoppers never set one.
            'canUsePassword' => $user->hasPassword() && filled($user->email),
        ]);
    }

    /**
     * The "sign me in" button in the email.
     *
     * In the browser that asked for the code it signs in at once. Anywhere
     * else it only asks: mail scanners open every link in an email, and one
     * must not sign anybody in. The page's button confirms (confirmLink),
     * and the original window — still on its code screen — signs in by
     * itself; "continue on this device" signs in here instead.
     */
    public function showLink(Request $request): Response|RedirectResponse
    {
        $row = $this->codes->resolveLink($request);

        if (! $row) {
            return $this->invalidLink($request);
        }

        if ($this->codes->isRequestedHere($request, $row)) {
            $this->codes->consumeLink($row);

            return $this->signIn($request, $row->user);
        }

        return Inertia::render('auth/login/link', [
            'maskedEmail' => Contact::email((string) $row->user->email)->masked(),
            // The signed URL itself: the POST is checked against it.
            'action' => $request->fullUrl(),
            'confirmed' => $row->verified_at !== null,
        ]);
    }

    /** The link page's buttons: confirm for the other window, or sign in here. */
    public function confirmLink(Request $request): RedirectResponse
    {
        $row = $this->codes->resolveLink($request);

        if (! $row) {
            return $this->invalidLink($request);
        }

        if ($this->codes->isRequestedHere($request, $row) || $request->boolean('here')) {
            $this->codes->consumeLink($row);

            return $this->signIn($request, $row->user);
        }

        $this->codes->verifyByLink($row);

        // Back to the link page, which now says "go back to the other window".
        return redirect()->to($request->fullUrl());
    }

    /**
     * Check the code and, if it is right, sign them in.
     */
    public function verify(Request $request): RedirectResponse
    {
        [$user] = $this->identified($request);

        if (! $user) {
            return $this->restart();
        }

        if (! $this->codes->isEligible($user)) {
            return $this->refuse($user);
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

        return $this->signIn($request, $user);
    }

    private function signIn(Request $request, User $user): RedirectResponse
    {
        // "Keep me signed in" is the default here: a shopper with no password
        // should not be typing codes every week.
        Auth::login($user, remember: true);
        $request->session()->regenerate();

        // The session key has done its job and named a real account; it should
        // not outlive the sign-in it authorised.
        $this->codes->forget($request);

        /*
         * Reuses Fortify's response contract so a code sign-in lands exactly
         * where a password sign-in would — sellers in their shop, buyers in
         * the market. Two "where do I end up" implementations would drift.
         */
        return app(LoginResponse::class)->toResponse($request);
    }

    /** A link that is expired, superseded, tampered with, or already used. */
    private function invalidLink(Request $request): RedirectResponse
    {
        // Used already — by this browser, which is signed in: just go on.
        if ($request->user()) {
            return app(LoginResponse::class)->toResponse($request);
        }

        return to_route('login')->with('error', __('common.auth.login.link_invalid'));
    }

    /**
     * The account and contact named by the session.
     *
     * @return array{0: ?User, 1: ?Contact}
     */
    private function identified(Request $request): array
    {
        $contact = $this->codes->identifiedContact($request);

        return [$contact ? $this->codes->identifiedUser($request) : null, $contact];
    }

    /**
     * Ineligible for a code: the password screen when it can help, else back
     * to the start with the reason.
     */
    private function refuse(User $user): RedirectResponse
    {
        if ($user->hasPassword() && filled($user->email)) {
            return to_route('login.password')
                ->with('error', __('common.auth.login.code_unavailable'));
        }

        return to_route('login')->with('error', __('common.auth.contact.use_password'));
    }

    /** Nothing identified — back to the field. */
    private function restart(): RedirectResponse
    {
        /*
         * Already signed in: the email's link was opened in another tab of
         * this browser while this one sat on the code screen. Go on, rather
         * than ask them to start again.
         */
        if (request()->user()) {
            return app(LoginResponse::class)->toResponse(request());
        }

        return to_route('login')
            ->with('error', __('common.auth.login.identify_again'));
    }
}
