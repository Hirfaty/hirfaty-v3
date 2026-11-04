<?php

namespace App\Http\Controllers\Auth;

use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\PendingRegistration;
use App\Models\Store;
use App\Models\User;
use App\Services\CityService;
use App\Services\PostLoginRedirectService;
use App\Services\RegistrationCodeService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Finishing a signup: emailed code → password → account.
 *
 * The *start* of it is not here. `GET /login` is Fortify's route and the email
 * field on it posts to LoginIdentifyController, which decides between this
 * flow and the password screen; `GET /sell` is the artisan equivalent, posting
 * to SellerRegistrationController. By the time anything below runs, a
 * `pending_registrations` row already exists.
 *
 * Both doors land here, and the row's `intent` is what tells them apart: it
 * picks the page each step renders, the rules the last step validates, and the
 * screen the finished account is handed to. It is read off the database rather
 * than the request precisely because the seller role hangs off it.
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

        // Same code, same guards, different chrome — the artisan flow keeps
        // its own artwork and copy so the screen does not change character
        // half-way through signing up.
        return Inertia::render(
            $pending->isSeller() ? 'auth/seller/verify' : 'auth/login/verify',
            $this->verifyProps($pending),
        );
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
     * Step 2, the other way — the "confirm my email" button in the email.
     *
     * Opened in the browser that started the signup, it does the whole job:
     * the address is confirmed and the visitor lands on the details step.
     *
     * Opened anywhere else it only asks. Mail scanners open every link in an
     * incoming email; if this GET moved the signup onto whatever browser
     * opened it, a scanner would quietly take it away from the person it was
     * meant for. The page's button POSTs to confirmLink(), which a scanner
     * does not do.
     */
    public function showLink(Request $request): Response|RedirectResponse
    {
        $pending = $this->codes->resolveLink($request);

        if (! $pending) {
            return $this->invalidLink();
        }

        if ($this->codes->isResumableHere($request, $pending)) {
            $this->codes->verifyByLink($pending);

            return to_route('login.details');
        }

        return Inertia::render('auth/login/link', [
            'maskedEmail' => $pending->maskedEmail(),
            // The signed URL itself: the POST is checked against the same
            // signature, so it has to go back with the same query string.
            'action' => $request->fullUrl(),
        ]);
    }

    /**
     * The "continue here" button: confirm the address and move the signup
     * onto this browser.
     */
    public function confirmLink(Request $request): RedirectResponse
    {
        $pending = $this->codes->resolveLink($request);

        if (! $pending) {
            return $this->invalidLink();
        }

        $this->codes->verifyByLink($pending);

        $response = to_route('login.details');

        if ($this->codes->isResumableHere($request, $pending)) {
            return $response;
        }

        return $response->withCookie(
            $this->codes->resumeCookie($this->codes->moveToThisBrowser($pending)),
        );
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
    public function showDetails(Request $request, CityService $cities): Response|RedirectResponse
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

        /*
         * The artisan version of this screen also names the workshop, so it
         * needs the two lists to pick from. `translated_name`, never the raw
         * Category: `categories.name` is a per-locale map, and handing an
         * object to an <option> takes the whole page down with "Objects are
         * not valid as a React child".
         */
        if ($pending->isSeller()) {
            return Inertia::render('auth/seller/details', [
                'categories' => Category::roots()
                    ->ordered()
                    ->get()
                    ->map(fn (Category $category) => [
                        'id' => $category->id,
                        'name' => $category->translated_name,
                    ]),
                'cities' => $cities->options(),
                'types' => Store::TYPES,
            ]);
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

        $isSeller = $pending->isSeller();

        $validated = $request->validate([
            /*
             * Required for a seller — the workshop needs a name to put on
             * invoices and a number an admin can reach. Optional for a buyer:
             * the whole point of this step is to be fast, and a shopper's name
             * and phone are only needed at checkout, where CheckoutController
             * already asks for a delivery contact. Left blank here, they can
             * be filled in any time from Buyer\ProfileController on /account.
             */
            'first_name' => [Rule::requiredIf($isSeller), 'nullable', 'string', 'max:255'],
            'last_name' => [Rule::requiredIf($isSeller), 'nullable', 'string', 'max:255'],
            /*
             * `unique` is load-bearing whenever the field is present:
             * users.phone_number carries a UNIQUE index, so without it a
             * duplicate number sails past validation and blows up on the
             * insert — a 500 on the last step of signup instead of "this
             * number is already in use". Same reasoning as
             * Actions\Fortify\CreateNewUser.
             */
            'phone_number' => [
                Rule::requiredIf($isSeller),
                'nullable',
                'string',
                'max:20',
                Rule::unique(User::class, 'phone_number'),
            ],
            'password' => $this->passwordRules(),
            'terms_accepted' => ['required', 'accepted'],

            /*
             * Workshop fields, present only on the artisan branch. All of them
             * are required rather than optional, unlike the old setup wizard
             * that let a seller skip craft, city and description: those three
             * are exactly what Store::REQUIRED_PROFILE_FIELDS checks, and a
             * shop missing any of them is silently *not* filed for review by
             * StoreVerificationService::submit. Letting them be skipped here
             * would land the seller on a page promising a review that was
             * never actually requested.
             */
            'store_name' => [Rule::requiredIf($isSeller), 'string', 'max:255', Rule::unique(Store::class, 'store_name')],
            'type' => [Rule::requiredIf($isSeller), Rule::in(Store::TYPES)],
            'category_id' => [Rule::requiredIf($isSeller), 'integer', 'exists:categories,id'],
            'city_id' => [Rule::requiredIf($isSeller), 'integer', 'exists:cities,id'],
            'description' => [Rule::requiredIf($isSeller), 'string', 'max:2500'],
        ]);

        // The role comes off the pending row, never off `$validated` — see
        // RegistrationCodeService::completeSeller.
        $user = $isSeller
            ? $this->codes->completeSeller($pending, $validated)
            : $this->codes->complete($pending, $validated);

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
     * A link that is expired, superseded by a resend, tampered with, or whose
     * signup is already finished.
     *
     * Unlike restart(), the resume cookie is left alone: an old link opened
     * in the browser that holds a live signup must not end that signup — the
     * login page offers to resume it.
     */
    private function invalidLink(): RedirectResponse
    {
        return to_route('login')->with('error', __('common.auth.login.link_invalid'));
    }

    /**
     * Where a freshly created account lands.
     *
     * Mirrors Http\Responses\VerifyEmailResponse: the code flow replaces the
     * emailed link, so this is now the moment that used to be "came back from
     * the inbox and clicked" — the one place the intro tour makes sense.
     *
     * A seller does not get the tour. Their shop is sitting in the review
     * queue and cannot publish anything yet, so the honest next screen is the
     * one that says so — see Seller\WelcomeController.
     */
    private function landing(User $user): RedirectResponse
    {
        if ($user->isSeller()) {
            return to_route('seller.welcome');
        }

        if ($user->needsOnboarding()) {
            // The remembered page waits in the session until the tour ends.
            return to_route('onboarding.show');
        }

        $intended = app(PostLoginRedirectService::class)->pull(request(), $user);

        return $intended !== null ? redirect($intended) : to_route('products.index');
    }
}
