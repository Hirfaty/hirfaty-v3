<?php

namespace App\Services;

use App\Concerns\GeneratesEmailCodes;
use App\Models\LoginCode;
use App\Models\User;
use App\Notifications\LoginCodeNotification;
use App\Support\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * Passwordless sign-in for an account that already exists: send a 6-digit
 * code to the address or number the visitor typed, accept it, log them in.
 *
 * The usual way in now — shoppers and artisans have no password to remember.
 * The signup equivalent is RegistrationCodeService; the code arithmetic they
 * share lives in Concerns\GeneratesEmailCodes.
 *
 * Three accounts are refused this path outright, all via `isEligible()`:
 *
 *  - **An admin.** A code to an inbox is one factor; the admin console
 *    reaches every buyer's address and every refund, so it keeps a password
 *    (and two-factor) — see LoginIdentifyController.
 *  - **Two-factor is on.** If a code alone could authenticate, the second
 *    factor would be optional in practice for anyone who reads the inbox.
 *  - **The account is banned.** EnsureAccountNotBanned would sign them
 *    straight back out anyway, so a code is not sent at all.
 */
class LoginCodeService
{
    use GeneratesEmailCodes;

    /**
     * Session key holding the address or number (normalised, see Contact)
     * the visitor identified as.
     */
    public const SESSION_KEY = 'login.identified_contact';

    public function __construct(
        private readonly ContactAccountService $accounts,
        private readonly SmsService $sms,
    ) {}

    /**
     * Whether this account may sign in with a code.
     *
     * Callers must consult this *before* offering the option, not only before
     * honouring it — a button that always fails is worse than no button.
     */
    public function isEligible(User $user): bool
    {
        return $user->two_factor_confirmed_at === null
            && ! $user->is_banned
            && ! $user->isSuperAdmin();
    }

    /**
     * Send a code to `$contact`, refreshing any outstanding one.
     *
     * Returns false without sending while the cooldown is running, so a
     * double-click cannot burn the code the visitor is already reading.
     *
     * @throws \RuntimeException when the SMS provider refuses the message
     */
    public function send(User $user, Contact $contact): bool
    {
        $existing = LoginCode::firstWhere('user_id', $user->id);

        if ($existing && $this->secondsUntilResend($existing) > 0) {
            return false;
        }

        $code = $this->generateCode();

        $row = LoginCode::updateOrCreate(
            ['user_id' => $user->id],
            [
                ...$this->freshCodeAttributes(Hash::make($code)),
                'channel' => $contact->channel,
                // A new code voids a confirmation the old link gave.
                'verified_at' => null,
            ],
        );

        if ($contact->isPhone()) {
            $this->sms->send($contact->value, $this->smsText($code, $user->locale));
        } else {
            $user->notify(new LoginCodeNotification($code, self::CODE_TTL_MINUTES, $this->linkFor($row)));
        }

        return true;
    }

    /**
     * Check a typed code, counting the attempt either way.
     *
     * Returns false for an expired or exhausted code as well as a wrong one;
     * the caller distinguishes those with `isLocked()` on the row from
     * `outstandingFor()` to pick the right message.
     */
    public function verify(User $user, string $code): bool
    {
        $row = LoginCode::firstWhere('user_id', $user->id);

        if (! $row || $row->hasExpired() || $this->isLocked($row)) {
            return false;
        }

        if (! Hash::check($code, $row->code_hash)) {
            $row->increment('attempts');

            return false;
        }

        // Spent — a code that logged someone in must not log anyone in twice.
        $row->delete();

        // Typing back a code sent there proves the number or address.
        $this->markVerified($user, $row->channel);

        return true;
    }

    /**
     * The "sign me in" button in the email — the code's one-click twin.
     *
     * Signed, tied to this send (`sent`: a resend retires older links as it
     * retires older codes) and dead when the code is. Built here, while the
     * request is live, so it carries the host the visitor is on.
     */
    public function linkFor(LoginCode $row): string
    {
        return URL::temporarySignedRoute('login.code.link', $row->expires_at, [
            'loginCode' => $row->id,
            'sent' => $row->last_sent_at->getTimestamp(),
        ]);
    }

    /**
     * The code a sign-in link points at, or null when the link is no good:
     * tampered, superseded by a resend, expired, or for an account that may
     * not sign in this way any more.
     *
     * A code locked by wrong guesses does not block the link — the lock is
     * against guessing six digits, and a signed URL cannot be guessed.
     */
    public function resolveLink(Request $request): ?LoginCode
    {
        if (! $request->hasValidSignature()) {
            return null;
        }

        $row = LoginCode::with('user')->find($request->route('loginCode'));

        if (! $row || $row->hasExpired() || ! $row->user || ! $this->isEligible($row->user)) {
            return null;
        }

        if ($row->last_sent_at?->getTimestamp() !== (int) $request->query('sent')) {
            return null;
        }

        return $row;
    }

    /** Whether this browser is the one that asked for the code. */
    public function isRequestedHere(Request $request, LoginCode $row): bool
    {
        return $this->identifiedUser($request)?->id === $row->user_id;
    }

    /**
     * The link, confirmed on another device: the window that asked for the
     * code sees it (it polls) and signs in with consumeVerified().
     */
    public function verifyByLink(LoginCode $row): void
    {
        if ($row->verified_at === null) {
            $row->update(['verified_at' => now()]);
        }
    }

    /**
     * Sign-in by the link: spend the code. True when the link — here or on
     * another device — has confirmed it; then it is gone, like a typed one.
     */
    public function consumeVerified(User $user): bool
    {
        $row = LoginCode::firstWhere('user_id', $user->id);

        if (! $row || $row->verified_at === null || $row->hasExpired()) {
            return false;
        }

        $this->spend($row, $user);

        return true;
    }

    /** Spend a code the link opened in this very browser. */
    public function consumeLink(LoginCode $row): void
    {
        $this->spend($row, $row->user);
    }

    private function spend(LoginCode $row, User $user): void
    {
        $row->delete();
        $this->markVerified($user, $row->channel);
    }

    /** The live code row for this account, if any, for rendering state. */
    public function outstandingFor(User $user): ?LoginCode
    {
        return LoginCode::firstWhere('user_id', $user->id);
    }

    /** Remember who the visitor said they are, for the next screen. */
    public function identify(Request $request, Contact $contact): void
    {
        $request->session()->put(self::SESSION_KEY, $contact->value);
    }

    /** What the visitor identified as, or null. */
    public function identifiedContact(Request $request): ?Contact
    {
        $value = $request->session()->get(self::SESSION_KEY);

        return is_string($value) ? Contact::parse($value) : null;
    }

    /**
     * The account the session names, re-read every request so a ban or a
     * two-factor change between screens is noticed rather than cached.
     */
    public function identifiedUser(Request $request): ?User
    {
        $contact = $this->identifiedContact($request);

        return $contact ? $this->accounts->find($contact) : null;
    }

    public function forget(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    /**
     * The SMS body. The last line is the WebOTP format (`@host #code`), which
     * lets Android offer to fill the code in with one tap.
     */
    public function smsText(string $code, ?string $locale = null): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'hirfaty.com';

        return __('common.auth.sms.code', [
            'code' => $code,
            'minutes' => self::CODE_TTL_MINUTES,
        ], $locale ?? app()->getLocale())."\n\n@{$host} #{$code}";
    }

    private function markVerified(User $user, ?string $channel): void
    {
        try {
            if ($channel === Contact::PHONE && $user->phone_verified_at === null) {
                $user->forceFill(['phone_verified_at' => now()])->save();
            } elseif ($channel !== Contact::PHONE && $user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
        } catch (\Throwable $e) {
            // Never worth failing a sign-in over.
            Log::warning('Could not mark contact verified: '.$e->getMessage());
        }
    }
}
