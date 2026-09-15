<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * The mechanics of a short-lived 6-digit code emailed to prove someone can
 * read an inbox.
 *
 * Shared by the two flows that need one — `RegistrationCodeService` (no
 * account exists yet) and `LoginCodeService` (an account does) — because the
 * *storage* differs but none of the arithmetic does. Keeping one copy is the
 * point: a TTL or attempt ceiling that drifted between the signup code and the
 * login code would be a security difference nobody chose.
 *
 * The consuming model is expected to carry `code_hash`, `expires_at`,
 * `attempts` and `last_sent_at`.
 */
trait GeneratesEmailCodes
{
    /** How long an emailed code stays good for. */
    public const CODE_TTL_MINUTES = 15;

    /**
     * Wrong guesses allowed against one code before it is burned.
     *
     * A 6-digit code is a 1-in-a-million guess, so this is generous for a
     * human typo and still far too tight to search the space — and the routes
     * that accept one are rate-limited on top.
     */
    public const MAX_ATTEMPTS = 6;

    /** Seconds between sends, surfaced to the UI as a countdown. */
    public const RESEND_COOLDOWN_SECONDS = 60;

    /**
     * A 6-digit code, zero-padded so it is always six characters.
     *
     * `random_int` rather than `rand`/`mt_rand`: this stands in for proof of
     * inbox access, so it has to come from a CSPRNG.
     */
    protected function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * The attributes a freshly issued code writes onto its row — used both
     * when creating one and when resending, so the two cannot disagree about
     * what "a new code" means.
     *
     * `attempts` resets to zero: a new code deserves a clean slate, or someone
     * who fat-fingered the previous one six times could never use its
     * replacement.
     *
     * @return array<string, mixed>
     */
    protected function freshCodeAttributes(string $hashedCode): array
    {
        return [
            'code_hash' => $hashedCode,
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
            'last_sent_at' => now(),
        ];
    }

    /** This code has taken all the wrong guesses it is going to take. */
    public function isLocked(Model $row): bool
    {
        return $row->attempts >= self::MAX_ATTEMPTS;
    }

    /** Seconds still to wait before a resend will do anything. */
    public function secondsUntilResend(Model $row): int
    {
        if ($row->last_sent_at === null) {
            return 0;
        }

        $ready = $row->last_sent_at->addSeconds(self::RESEND_COOLDOWN_SECONDS);

        return max(0, now()->diffInSeconds($ready, false));
    }
}
