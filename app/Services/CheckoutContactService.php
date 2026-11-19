<?php

namespace App\Services;

use App\Models\LoginCode;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Support\Contact;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * The "where do we send your order details?" box at the top of checkout, for
 * a visitor who has not signed in.
 *
 * They type an email or a phone number, a code is sent there, they type it
 * back — and only then can they pay. The code is how a wrong address or
 * number is caught: a typo never receives it, so it is fixed *before* the
 * order exists instead of after the confirmation has gone nowhere.
 *
 * Typing the code back also signs them in: to the account that contact
 * already has (it was proven, so this is safe), or to a new one made from it
 * on the spot. Either way the order belongs to an account they can come back
 * to with the same contact, and the basket follows (CartService::adoptGuestCart).
 *
 * Holds which of the two it is in the session under SESSION_KEY: `login` (the
 * code is a LoginCodeService one) or `signup` (a pending registration, found
 * through its resume cookie as on /login).
 */
class CheckoutContactService
{
    public const SESSION_KEY = 'checkout.contact';

    public function __construct(
        private readonly ContactSignInService $signIn,
        private readonly LoginCodeService $loginCodes,
        private readonly RegistrationCodeService $registrations,
        private readonly PostLoginRedirectService $redirects,
    ) {}

    /**
     * What the contact box shows: the field, or the code sent to what was
     * typed (in full — it is the visitor's own, in their own browser, and
     * seeing `+212 6 12 34 56 87` is how they notice it should end in 78).
     *
     * @return array{step: string, value?: string, channel?: string, region?: ?string, resendIn?: int, locked?: bool}
     *
     * The box polls this while it waits, so a click on the email's link is
     * noticed without the visitor doing anything.
     */
    public function state(Request $request): array
    {
        $held = $this->held($request);

        if (! $held) {
            return ['step' => 'enter'];
        }

        [$kind, $contact] = $held;

        $row = $kind === 'login'
            ? $this->loginRow($request, $contact)
            : $this->pending($request, $contact);

        if (! $row) {
            return ['step' => 'enter'];
        }

        return [
            /*
             * `confirmed`: the link in the email was opened — on the phone,
             * say — and proved the address. The box finishes the signup here
             * by itself (verify() without a code), where the basket is.
             */
            'step' => ($kind === 'signup' ? $row->isVerified() : $row->verified_at !== null) ? 'confirmed' : 'code',
            'value' => $contact->display(),
            'channel' => $contact->channel,
            'region' => $contact->region(),
            'resendIn' => $kind === 'login'
                ? $this->loginCodes->secondsUntilResend($row)
                : $this->registrations->secondsUntilResend($row),
            'locked' => $kind === 'login'
                ? $this->loginCodes->isLocked($row)
                : $this->registrations->isLocked($row),
        ];
    }

    /**
     * Send a code to the contact typed in `contact`. Returns the resume token
     * to set as a cookie when this opened a signup.
     *
     * @throws ValidationException
     */
    public function send(Request $request): ?string
    {
        $contact = $this->signIn->parse($request, 'contact');

        $result = $this->signIn->begin(
            $request,
            $contact,
            PendingRegistration::INTENT_BUYER,
            shoppersOnly: true,
            field: 'contact',
        );

        $kind = $result['outcome'] === ContactSignInService::SIGNUP ? 'signup' : 'login';

        $request->session()->put(self::SESSION_KEY, ['kind' => $kind, 'value' => $contact->value]);

        /*
         * A signup email also carries a "confirm my email" link. Opened in
         * this browser it finishes the signup in a tab of its own; this is
         * where that tab should land — the checkout, not the intro tour.
         */
        $this->redirects->remember($request, $request->headers->get('referer'));

        return $result['token'] ?? null;
    }

    /**
     * Send the code again, after the cooldown.
     *
     * @throws ValidationException
     */
    public function resend(Request $request): void
    {
        $held = $this->held($request);

        if (! $held) {
            return;
        }

        [$kind, $contact] = $held;

        try {
            if ($kind === 'login') {
                $user = $this->loginCodes->identifiedUser($request);

                if ($user) {
                    $this->loginCodes->send($user, $contact);
                }

                return;
            }

            if ($pending = $this->pending($request, $contact)) {
                $this->registrations->resend($pending);
            }
        } catch (\RuntimeException $e) {
            report($e);

            throw ValidationException::withMessages(['code' => __('common.auth.contact.send_failed')]);
        }
    }

    /**
     * Check the code; on success sign the visitor in (making their account
     * if the contact had none) and return them.
     *
     * No code is needed for a signup the email's link has already confirmed.
     *
     * @throws ValidationException
     */
    public function verify(Request $request, ?string $code): User
    {
        $held = $this->held($request);

        if (! $held) {
            throw ValidationException::withMessages(['contact' => __('buyer.checkout.contact_expired')]);
        }

        [$kind, $contact] = $held;

        $user = $kind === 'login'
            ? $this->verifyLogin($request, $contact, $code)
            : $this->verifySignup($request, $contact, $code);

        Auth::login($user, remember: true);
        $request->session()->regenerate();
        $this->forget($request);

        // Already on the checkout: the destination remembered for the link's
        // tab is spent.
        $this->redirects->pull($request, $user);

        return $user;
    }

    /** "Change" — back to the field, to type it again. */
    public function forget(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
        $this->loginCodes->forget($request);
    }

    private function verifyLogin(Request $request, Contact $contact, ?string $code): User
    {
        $user = $this->loginCodes->identifiedUser($request);

        if (! $user || ! $user->isBuyer() || ! $this->loginCodes->isEligible($user)) {
            $this->forget($request);

            throw ValidationException::withMessages(['contact' => __('buyer.checkout.contact_expired')]);
        }

        // No code: only good when the email's link confirmed the sign-in.
        $passed = $code === null
            ? $this->loginCodes->consumeVerified($user)
            : $this->loginCodes->verify($user, $code);

        if (! $passed) {
            $row = $this->loginCodes->outstandingFor($user);

            throw ValidationException::withMessages([
                'code' => $row && $this->loginCodes->isLocked($row)
                    ? __('common.auth.login.code_locked')
                    : __('common.auth.login.code_invalid'),
            ]);
        }

        return $user;
    }

    private function verifySignup(Request $request, Contact $contact, ?string $code): User
    {
        $pending = $this->pending($request, $contact);

        if (! $pending) {
            $this->forget($request);

            throw ValidationException::withMessages(['contact' => __('buyer.checkout.contact_expired')]);
        }

        if (! $pending->isVerified() && ($code === null || ! $this->registrations->verify($pending, $code))) {
            $pending->refresh();

            throw ValidationException::withMessages([
                'code' => $this->registrations->isLocked($pending)
                    ? __('common.auth.login.code_locked')
                    : __('common.auth.login.code_invalid'),
            ]);
        }

        $user = $this->registrations->complete($pending);

        event(new Registered($user));

        return $user;
    }

    /**
     * @return array{0: string, 1: Contact}|null
     */
    private function held(Request $request): ?array
    {
        $held = $request->session()->get(self::SESSION_KEY);

        if (! is_array($held) || ! in_array($held['kind'] ?? null, ['login', 'signup'], true)) {
            return null;
        }

        $contact = Contact::parse((string) ($held['value'] ?? ''));

        return $contact ? [$held['kind'], $contact] : null;
    }

    private function loginRow(Request $request, Contact $contact): ?LoginCode
    {
        $identified = $this->loginCodes->identifiedContact($request);

        if (! $identified || $identified->value !== $contact->value) {
            return null;
        }

        $user = $this->loginCodes->identifiedUser($request);

        return $user ? $this->loginCodes->outstandingFor($user) : null;
    }

    /**
     * The pending signup this browser holds for this contact — a signup for a
     * different contact, started on /login in another tab, does not count.
     */
    private function pending(Request $request, Contact $contact): ?PendingRegistration
    {
        $pending = $this->registrations->resolve($request);

        return $pending && $pending->contact()->value === $contact->value && ! $pending->isSeller()
            ? $pending
            : null;
    }
}
