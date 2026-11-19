<?php

namespace App\Services;

use App\Models\PendingRegistration;
use App\Models\User;
use App\Support\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * "Email or phone" → a code: the one first step behind every way in — the
 * sign-in page, the artisan door (/sell) and the contact box at checkout.
 *
 * Decides, for what the visitor typed:
 *
 * | Typed                               | Outcome                                    |
 * | ----------------------------------- | ------------------------------------------ |
 * | An account that may use a code      | `code` — a sign-in code is sent there      |
 * | An admin / two-factor account       | `password` — the password screen           |
 * | Nobody yet                          | `signup` — a signup code is sent there     |
 *
 * Every refusal is a ValidationException on the field, so each screen shows
 * it under the input the visitor can correct.
 */
class ContactSignInService
{
    public const CODE = 'code';

    public const PASSWORD = 'password';

    public const SIGNUP = 'signup';

    public function __construct(
        private readonly ContactAccountService $accounts,
        private readonly LoginCodeService $loginCodes,
        private readonly RegistrationCodeService $registrations,
        private readonly SmsService $sms,
    ) {}

    /**
     * The address or number in `$field`, normalised — or a validation error
     * that says which of the two looked wrong.
     *
     * @throws ValidationException
     */
    public function parse(Request $request, string $field = 'identifier'): Contact
    {
        $request->validate([
            $field => ['required', 'string', 'max:255'],
        ], [
            "{$field}.required" => __('common.auth.contact.required'),
        ]);

        $raw = (string) $request->input($field);
        $contact = Contact::parse($raw);

        if (! $contact) {
            throw ValidationException::withMessages([
                $field => Contact::looksLikePhone($raw)
                    ? __('common.auth.contact.invalid_phone')
                    : __('common.auth.contact.invalid_email'),
            ]);
        }

        if ($contact->isPhone() && ! $this->sms->available()) {
            throw ValidationException::withMessages([
                $field => __('common.auth.contact.phone_unavailable'),
            ]);
        }

        return $contact;
    }

    /**
     * Send the right code for this contact.
     *
     * `$shoppersOnly` is checkout's: there, the code signs someone in to buy,
     * so an artisan's or an admin's account is refused rather than opened.
     *
     * @return array{outcome: string, user?: User, token?: string}
     *
     * @throws ValidationException
     */
    public function begin(
        Request $request,
        Contact $contact,
        string $intent = PendingRegistration::INTENT_BUYER,
        bool $shoppersOnly = false,
        string $field = 'identifier',
    ): array {
        $this->throttle($request, $contact, $field);

        $user = $this->accounts->find($contact);

        if ($user) {
            if ($shoppersOnly && ! $user->isBuyer()) {
                throw ValidationException::withMessages([
                    $field => __('common.auth.contact.not_a_shopper'),
                ]);
            }

            $this->loginCodes->identify($request, $contact);

            if (! $this->loginCodes->isEligible($user)) {
                // Only an address can reach the password screen: Fortify's
                // login posts an email.
                if ($contact->isEmail() && $user->hasPassword() && ! $shoppersOnly) {
                    return ['outcome' => self::PASSWORD, 'user' => $user];
                }

                throw ValidationException::withMessages([
                    $field => __('common.auth.contact.use_password'),
                ]);
            }

            $this->guardDelivery($field, fn () => $this->loginCodes->send($user, $contact));

            return ['outcome' => self::CODE, 'user' => $user];
        }

        if ($this->accounts->phoneHeldUnverified($contact)) {
            throw ValidationException::withMessages([
                $field => __('common.auth.contact.phone_taken'),
            ]);
        }

        $token = $this->guardDelivery(
            $field,
            fn () => $this->registrations->start($contact, app()->getLocale(), $intent),
        );

        return ['outcome' => self::SIGNUP, 'token' => $token];
    }

    /**
     * A ceiling on codes, on top of each route's per-minute throttle: so many
     * an hour to one address or number, and — since every SMS is paid for —
     * so many texts an hour from one connection. Bots that type premium-rate
     * numbers into sign-in forms to bill the site for the texts ("SMS
     * pumping") are what the second one is for.
     *
     * @throws ValidationException
     */
    private function throttle(Request $request, Contact $contact, string $field): void
    {
        $limits = ['contact-code:'.hash('sha256', $contact->value) => 6];

        if ($contact->isPhone()) {
            $limits['sms-code-ip:'.$request->ip()] = 10;
        }

        foreach ($limits as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                throw ValidationException::withMessages([
                    $field => __('common.auth.contact.too_many', [
                        'minutes' => (int) ceil(RateLimiter::availableIn($key) / 60),
                    ]),
                ]);
            }
        }

        foreach (array_keys($limits) as $key) {
            RateLimiter::hit($key, 3600);
        }
    }

    /**
     * Run a send, turning a refused SMS into a message under the field —
     * the visitor can then check the number or use their address instead.
     *
     * @template T
     *
     * @param  callable(): T  $send
     * @return T
     *
     * @throws ValidationException
     */
    private function guardDelivery(string $field, callable $send): mixed
    {
        try {
            return $send();
        } catch (RuntimeException $e) {
            report($e);

            throw ValidationException::withMessages([
                $field => __('common.auth.contact.send_failed'),
            ]);
        }
    }
}
