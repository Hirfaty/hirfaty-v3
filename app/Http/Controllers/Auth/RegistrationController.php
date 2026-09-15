<?php

namespace App\Http\Controllers\Auth;

use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Services\RegistrationCodeService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Finishing a signup: emailed code → name/phone/password → account.
 *
 * The *start* of it is not here. `GET /login` is Fortify's route and the email
 * field on it posts to LoginIdentifyController, which decides between this
 * flow and the password screen. By the time anything below runs, a
 * `pending_registrations` row already exists.
 *
 * Thin by design — every decision lives in RegistrationCodeService. What this
 * class owns is the *guard* logic: reconciling the resume cookie against the
 * pending row so a visitor who closed the tab always lands on the furthest
 * step they have actually earned, and never on one they haven't.
 */
class RegistrationController extends Controller
{
    use PasswordValidationRules;

    public function __construct(
        private readonly RegistrationCodeService $codes,
    ) {}

    /**
     * Step 2 — the code screen.
     */
    public function showVerify(Request $request): Response|RedirectResponse
    {
        $pending = $this->codes->resolve($request);

        if (! $pending) {
            return $this->restart();
        }

        // Already past this step: never re-ask for a code that has been
        // accepted. This is the branch that makes closing the tab on step 3
        // and coming back land in the right place.
        if ($pending->isVerified()) {
            return to_route('login.details');
        }

        return Inertia::render('auth/login/verify', $this->verifyProps($pending));
    }

    /**
     * Step 2 submit — check the typed code.
     */
    public function verify(Request $request): RedirectResponse
    {
        $pending = $this->codes->resolve($request);

        if (! $pending) {
            return $this->restart();
        }

        if ($pending->isVerified()) {
            return to_route('login.details');
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ]);

        if ($this->codes->verify($pending, $validated['code'])) {
            return to_route('login.details');
        }

        // Refreshed from the database: `verify()` incremented `attempts`, and
        // the message depends on how many are left.
        $pending->refresh();

        return back()->withErrors([
            'code' => $this->codes->isLocked($pending)
                ? __('common.auth.login.code_locked')
                : __('common.auth.login.code_invalid'),
        ]);
    }

    /**
     * Step 2 — send a fresh code.
     */
    public function resend(Request $request): RedirectResponse
    {
        $pending = $this->codes->resolve($request);

        if (! $pending) {
            return $this->restart();
        }

        if (! $this->codes->resend($pending)) {
            return back()->withErrors([
                'code' => __('common.auth.login.resend_wait', [
                    'seconds' => $this->codes->secondsUntilResend($pending),
                ]),
            ]);
        }

        return back()->with('success', __('common.auth.login.code_resent'));
    }

    /**
     * Step 3 — the details screen.
     */
    public function showDetails(Request $request): Response|RedirectResponse
    {
        $pending = $this->codes->resolve($request);

        if (! $pending) {
            return $this->restart();
        }

        // The code is the gate on this screen. Without it there is nothing
        // tying this browser to that inbox, so there is no account to create.
        if (! $pending->isVerified()) {
            return to_route('login.verify');
        }

        // No `email` prop: the screen no longer displays the confirmed
        // address (see pages/auth/login/details.tsx), and the server already
        // knows it from `$pending` when the form is submitted — nothing on
        // this screen needs it passed down.
        return Inertia::render('auth/login/details');
    }

    /**
     * Step 3 submit — create the account and sign in.
     */
    public function store(Request $request): RedirectResponse
    {
        $pending = $this->codes->resolve($request);

        if (! $pending) {
            return $this->restart();
        }

        if (! $pending->isVerified()) {
            return to_route('login.verify');
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            /*
             * `unique` is load-bearing: users.phone_number carries a UNIQUE
             * index, so without it a duplicate number sails past validation
             * and blows up on the insert — a 500 on the last step of signup
             * instead of "this number is already in use". Same reasoning as
             * Actions\Fortify\CreateNewUser.
             */
            'phone_number' => [
                'required',
                'string',
                'max:20',
                Rule::unique(User::class, 'phone_number'),
            ],
            'password' => $this->passwordRules(),
            'terms_accepted' => ['required', 'accepted'],
        ]);

        $user = $this->codes->complete($pending, $validated);

        /*
         * Fired for the benefit of anything else listening on signup. Safe
         * even though the user is already verified: the framework's
         * SendEmailVerificationNotification listener checks
         * `hasVerifiedEmail()` first, so no stray verification mail goes out.
         */
        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return $this->landing($user)
            ->withCookie($this->codes->forgetCookie());
    }

    /**
     * Props for the code screen, shared by the GET and the failed-POST render.
     */
    private function verifyProps(PendingRegistration $pending): array
    {
        return [
            // Masked, not the full address: this page can be resumed a week
            // later on a shared machine. Enough to confirm they typed the
            // address they meant, no more.
            'maskedEmail' => $pending->maskedEmail(),
            'resendIn' => $this->codes->secondsUntilResend($pending),
            'locked' => $this->codes->isLocked($pending),
        ];
    }

    /**
     * Nothing resumable — back to the start, cookie cleared so a stale one
     * cannot keep bouncing them through these guards.
     */
    private function restart(): RedirectResponse
    {
        return to_route('login')
            ->with('error', __('common.auth.login.session_expired'))
            ->withCookie($this->codes->forgetCookie());
    }

    /**
     * Where a freshly created buyer lands.
     *
     * Mirrors Http\Responses\VerifyEmailResponse: the code flow replaces the
     * emailed link, so this is now the moment that used to be "came back from
     * the inbox and clicked" — the one place the intro tour makes sense.
     */
    private function landing(User $user): RedirectResponse
    {
        return $user->needsOnboarding()
            ? to_route('onboarding.show')
            : to_route('products.index');
    }
}
