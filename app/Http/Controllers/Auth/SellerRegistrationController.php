<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PendingRegistration;
use App\Services\ContactSignInService;
use App\Services\RegistrationCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The artisan door: /sell.
 *
 * Identifier-first, exactly like /login — one email field that decides what to
 * ask for next. What it does *not* do is offer a password: someone who already
 * has an account is handed to the normal sign-in screen rather than being
 * asked to prove themselves twice in two different places.
 *
 * Only the first step lives here. Once a code is on its way the signup is an
 * ordinary `pending_registrations` row and RegistrationController owns it —
 * same guards, same resume cookie, same attempt ceiling. The one thing that
 * makes it a seller signup is the intent stamped on that row, which is why
 * this controller is thin: it picks a door, and the door is all it picks.
 */
class SellerRegistrationController extends Controller
{
    public function __construct(
        private readonly RegistrationCodeService $codes,
        private readonly ContactSignInService $signIn,
    ) {}

    /**
     * The pitch, and the email field under it.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        /*
         * A signed-in seller has no business on a signup page — send them
         * where they were going. Anyone else signed in (a shopper curious
         * about selling) is left alone: they are reading, not signing in, and
         * bouncing them would make the page unreadable to exactly the
         * audience it is written for.
         */
        if ($request->user()?->role === 'seller') {
            return to_route('seller.dashboard');
        }

        return Inertia::render('auth/seller/identify');
    }

    /**
     * Decide which screen this address or number belongs on.
     *
     * Someone who already has an account — a shopper included — is sent to
     * sign in, not to sign up: an existing shopper cannot become a seller by
     * typing their address here, because that would hand out the role on the
     * strength of a form post. Promotion is an admin decision
     * (Admin\UserController).
     */
    public function identify(Request $request): RedirectResponse
    {
        $contact = $this->signIn->parse($request);

        $result = $this->signIn->begin($request, $contact, PendingRegistration::INTENT_SELLER);

        return match ($result['outcome']) {
            ContactSignInService::CODE => to_route('login.code'),
            ContactSignInService::PASSWORD => to_route('login.password'),
            default => to_route('login.verify')
                ->withCookie($this->codes->resumeCookie($result['token'])),
        };
    }
}
