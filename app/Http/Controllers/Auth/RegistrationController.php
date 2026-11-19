<?php

namespace App\Http\Controllers\Auth;

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
 * Finishing a signup: the code sent to the address or number → account.
 *
 * A shopper's account is made the moment the code is accepted, and they are
 * signed straight in — no password, no form. An artisan (/sell) still has a
 * details step, for the workshop.
 *
 * The *start* of it is not here. `GET /login` is Fortify's route and the field
 * on it posts to LoginIdentifyController; `GET /sell` is the artisan
 * equivalent. By the time anything below runs, a `pending_registrations` row
 * already exists. The row's `intent` is what tells the two doors apart: it is
 * read off the database rather than the request precisely because the seller
 * role hangs off it.
 *
 * Thin by design — every decision lives in RegistrationCodeService. What this
 * class owns is the *guard* logic: reconciling the resume cookie against the
 * pending row so a visitor who closed the tab always lands on the furthest
 * step they have actually earned, and never on one they haven't.
 */
class RegistrationController extends Controller
{
    public function __construct(
        private readonly RegistrationCodeService $codes,
    ) {}

    /**
     * The code screen.
     */
    public function showVerify(Request $request): Response|RedirectResponse
    {
        $pending = $this->codes->resolve($request);

        if (! $pending) {
            return $this->restart();
        }

        // Already past this step: never re-ask for a code that has been
        // accepted.
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
     * Check the typed code — and for a shopper, that is the whole signup.
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
            return $pending->isSeller()
                ? to_route('login.details')
                : $this->finishShopper($request, $pending);
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
     * The other way past the code — the "confirm my email" button in the
     * email (an SMS carries the code alone).
     *
     * Opened in the browser that started the signup, it does the whole job.
     * Opened anywhere else it only asks: mail scanners open every link in an
     * incoming email, and if this GET moved the signup onto whatever browser
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
            'maskedEmail' => $pending->maskedContact(),
            // The signed URL itself: the POST is checked against the same
            // signature, so it has to go back with the same query string.
            'action' => $request->fullUrl(),
            /*
             * Already confirmed from here: the window the signup started in
             * notices (its code screen and the checkout's code box poll) and
             * finishes on its own — with its basket. This page then says to
             * go back, and offers to finish here instead.
             */
            'confirmed' => $pending->isVerified(),
        ]);
    }

    /**
     * The button on the link page, in a browser other than the one the
     * signup started in.
     *
     * By default it only confirms the address: the original window, still on
     * its code screen (or the checkout's code box), sees the confirmation and
     * finishes there. That is usually where the person is — and at checkout
     * it is where their basket is. `here` moves the signup onto this browser
     * instead, for someone who has closed the other window or would rather
     * carry on on this device.
     */
    public function confirmLink(Request $request): RedirectResponse
    {
        $pending = $this->codes->resolveLink($request);

        if (! $pending) {
            return $this->invalidLink();
        }

        $this->codes->verifyByLink($pending);

        if ($this->codes->isResumableHere($request, $pending)) {
            return to_route('login.details');
        }

        if (! $request->boolean('here')) {
            // Back to the link page, which now says "go back to the other window".
            return redirect()->to($request->fullUrl());
        }

        return to_route('login.details')->withCookie(
            $this->codes->resumeCookie($this->codes->moveToThisBrowser($pending)),
        );
    }

    /**
     * Send a fresh code.
     */
    public function resend(Request $request): RedirectResponse
    {
        $pending = $this->codes->resolve($request);

        if (! $pending) {
            return $this->restart();
        }

        try {
            $sent = $this->codes->resend($pending);
        } catch (\RuntimeException $e) {
            report($e);

            return back()->withErrors(['code' => __('common.auth.contact.send_failed')]);
        }

        if (! $sent) {
            return back()->withErrors([
                'code' => __('common.auth.login.resend_wait', [
                    'seconds' => $this->codes->secondsUntilResend($pending),
                ]),
            ]);
        }

        return back()->with('success', __('common.auth.login.code_resent'));
    }

    /**
     * After the code: a shopper's account is made here and now; an artisan
     * gets the workshop form.
     */
    public function showDetails(Request $request, CityService $cities): Response|RedirectResponse
    {
        $pending = $this->codes->resolve($request);

        if (! $pending) {
            return $this->restart();
        }

        // The code is the gate on this screen. Without it there is nothing
        // tying this browser to that inbox or phone.
        if (! $pending->isVerified()) {
            return to_route('login.verify');
        }

        // Nothing for a shopper to fill in (reached by a resumed signup or
        // the emailed link): the proven address or number is the account.
        if (! $pending->isSeller()) {
            return $this->finishShopper($request, $pending);
        }

        /*
         * `translated_name`, never the raw Category: `categories.name` is a
         * per-locale map, and handing an object to an <option> takes the
         * whole page down with "Objects are not valid as a React child".
         */
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
            // Signed up by phone: that number is proven and is the one on the
            // account — the form shows it rather than asking for another.
            'verifiedPhone' => $pending->contact()->isPhone() ? $pending->contact()->display() : null,
        ]);
    }

    /**
     * The artisan's details submit — create the account and the workshop.
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

        if (! $pending->isSeller()) {
            return $this->finishShopper($request, $pending);
        }

        $byPhone = $pending->contact()->isPhone();

        $validated = $request->validate([
            // The workshop needs a name to put on invoices and a number an
            // admin can reach.
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            /*
             * Asked only when the signup was by email — by phone, the proven
             * number is used. `unique` is load-bearing: users.phone_number
             * carries a UNIQUE index, so without it a duplicate sails past
             * validation and blows up on the insert.
             */
            'phone_number' => $byPhone
                ? ['exclude']
                : ['required', 'string', 'max:20', Rule::unique(User::class, 'phone_number')],
            'terms_accepted' => ['required', 'accepted'],

            /*
             * All required: craft, city and description are exactly what
             * Store::REQUIRED_PROFILE_FIELDS checks, and a shop missing any of
             * them is silently *not* filed for review by
             * StoreVerificationService::submit.
             */
            'store_name' => ['required', 'string', 'max:255', Rule::unique(Store::class, 'store_name')],
            'type' => ['required', Rule::in(Store::TYPES)],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'description' => ['required', 'string', 'max:2500'],
        ]);

        // The role comes off the pending row, never off `$validated` — see
        // RegistrationCodeService::completeSeller.
        $user = $this->codes->completeSeller($pending, $validated);

        return $this->signIn($request, $user);
    }

    /**
     * Make the shopper's account from the proven contact and sign them in.
     */
    private function finishShopper(Request $request, PendingRegistration $pending): RedirectResponse
    {
        return $this->signIn($request, $this->codes->complete($pending));
    }

    private function signIn(Request $request, User $user): RedirectResponse
    {
        /*
         * Fired for the benefit of anything else listening on signup. Safe
         * even though the user is already verified: the framework's
         * SendEmailVerificationNotification listener checks
         * `hasVerifiedEmail()` first, so no stray verification mail goes out.
         */
        event(new Registered($user));

        // Remembered: with no password, being signed out means another code.
        Auth::login($user, remember: true);
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
            // Masked: this page can be resumed a week later on a shared
            // machine. Enough to confirm they typed what they meant, no more.
            'maskedContact' => $pending->maskedContact(),
            'channel' => $pending->contact()->channel,
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
        /*
         * Already signed in: the signup was finished in another tab of this
         * browser — the link in the email, opened beside the code screen.
         * Follow it rather than call the session expired.
         */
        if ($user = request()->user()) {
            return $this->landing($user)->withCookie($this->codes->forgetCookie());
        }

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
     * A seller does not get the tour. Their shop is sitting in the review
     * queue and cannot publish anything yet, so the honest next screen is the
     * one that says so — see Seller\WelcomeController.
     */
    private function landing(User $user): RedirectResponse
    {
        if ($user->isSeller()) {
            return to_route('seller.welcome');
        }

        $redirects = app(PostLoginRedirectService::class);

        // Not mid-purchase, though: someone who signed up from the checkout's
        // contact box goes straight back to pay, tour or not.
        if ($user->needsOnboarding() && ! $redirects->pendingCheckout(request())) {
            // The remembered page waits in the session until the tour ends.
            return to_route('onboarding.show');
        }

        $intended = $redirects->pull(request(), $user);

        return $intended !== null ? redirect($intended) : to_route('products.index');
    }
}
