<?php

namespace App\Services;

use App\Concerns\GeneratesEmailCodes;
use App\Models\LoginCode;
use App\Models\User;
use App\Notifications\LoginCodeNotification;
use Illuminate\Support\Facades\Hash;

/**
 * Passwordless sign-in for an account that already exists: mail a 6-digit
 * code, accept it, log them in.
 *
 * The signup equivalent is RegistrationCodeService; the code arithmetic they
 * share lives in Concerns\GeneratesEmailCodes.
 *
 * Two accounts are refused this path outright, both via `isEligible()`:
 *
 *  - **Two-factor is on.** A code mailed to the address *is* a single factor.
 *    If it alone could authenticate, the second factor would be optional in
 *    practice for anyone who knows the email — which is the whole population
 *    2FA exists to protect against. Those accounts get the password field and
 *    Fortify's challenge, nothing else.
 *  - **The account is banned.** EnsureAccountNotBanned would sign them
 *    straight back out anyway, but mailing a code to someone who can never
 *    use it is pointless — so the option is not offered at all.
 */
class LoginCodeService
{
    use GeneratesEmailCodes;

    /** Session key holding the address the visitor identified as. */
    public const SESSION_KEY = 'login.identified_email';

    /**
     * Whether this account may use a code instead of its password.
     *
     * Callers must consult this *before* offering the option, not only before
     * honouring it — a button that always fails is worse than no button.
     */
    public function isEligible(User $user): bool
    {
        return $user->two_factor_confirmed_at === null
            && ! $user->is_banned;
    }

    /**
     * Mail a code, refreshing any outstanding one for this account.
     *
     * Returns false without sending while the cooldown is running, so a
     * double-click cannot burn the code the visitor is already reading.
     */
    public function send(User $user): bool
    {
        $existing = LoginCode::firstWhere('user_id', $user->id);

        if ($existing && $this->secondsUntilResend($existing) > 0) {
            return false;
        }

        $code = $this->generateCode();

        LoginCode::updateOrCreate(
            ['user_id' => $user->id],
            $this->freshCodeAttributes(Hash::make($code)),
        );

        $user->notify(new LoginCodeNotification($code, self::CODE_TTL_MINUTES));

        return true;
    }

    /**
     * Check a typed code, counting the attempt either way.
     *
     * Returns false for an expired or exhausted code as well as a wrong one;
     * the caller distinguishes those with `isLocked()`/`hasExpired()` on the
     * row from `outstandingFor()` to pick the right message.
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

        return true;
    }

    /** The live code row for this account, if any, for rendering state. */
    public function outstandingFor(User $user): ?LoginCode
    {
        return LoginCode::firstWhere('user_id', $user->id);
    }
}
