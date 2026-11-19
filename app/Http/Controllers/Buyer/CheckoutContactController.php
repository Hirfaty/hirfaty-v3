<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Services\CheckoutContactService;
use App\Services\RegistrationCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The contact box at the top of checkout, for a visitor who has not signed
 * in: send a code to their email or phone, check it, carry on to pay.
 *
 * Thin — see CheckoutContactService for what each step does and why the code
 * is what catches a mistyped address or number.
 */
class CheckoutContactController extends Controller
{
    public function __construct(
        private readonly CheckoutContactService $contacts,
        private readonly RegistrationCodeService $registrations,
    ) {}

    public function send(Request $request): RedirectResponse
    {
        $token = $this->contacts->send($request);

        $response = back(fallback: route('checkout.show'))
            ->with('success', __('buyer.checkout.contact_code_sent'));

        return $token !== null
            ? $response->withCookie($this->registrations->resumeCookie($token))
            : $response;
    }

    public function resend(Request $request): RedirectResponse
    {
        $this->contacts->resend($request);

        return back(fallback: route('checkout.show'))
            ->with('success', __('common.auth.login.code_resent'));
    }

    /**
     * The right code signs them in, so the page they come back to is the
     * same checkout, now with somewhere to pay from — and whatever they had
     * already typed in the form kept (the page preserves its state).
     */
    public function verify(Request $request): RedirectResponse
    {
        // Absent when the box finishes a signup the email's link confirmed.
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'digits:6'],
        ]);

        $this->contacts->verify($request, $validated['code'] ?? null);

        return back(fallback: route('checkout.show'))
            ->with('success', __('buyer.checkout.contact_confirmed'))
            ->withCookie($this->registrations->forgetCookie());
    }

    /** "Change" — type the address or number again. */
    public function reset(Request $request): RedirectResponse
    {
        $this->contacts->forget($request);

        return back(fallback: route('checkout.show'));
    }
}
