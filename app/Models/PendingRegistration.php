<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * A buyer signup that has started but not finished — see the migration for why
 * this is its own table rather than a half-filled `users` row.
 *
 * @property int $id
 * @property string $email
 * @property string $code_hash
 * @property string $resume_token_hash
 * @property CarbonImmutable|null $verified_at
 * @property int $attempts
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $last_sent_at
 * @property string $locale
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
// Both hashes are credentials. Hiding them keeps them out of any array/JSON
// cast of this model, so a careless `->toArray()` into an Inertia prop can't
// ship them to the browser.
#[Hidden(['code_hash', 'resume_token_hash'])]
class PendingRegistration extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }

    /** The code is past its validity window — a new one must be sent. */
    public function hasExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /** The code has been typed back in correctly; the details step may show. */
    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * Which step this row should resume at, so the guards in
     * RegistrationController and the step pages agree on one answer.
     */
    public function nextStep(): string
    {
        return $this->isVerified() ? 'details' : 'verify';
    }

    /**
     * The email with its local part largely masked — `ac•••@example.com`.
     *
     * Shown on the code screen so the visitor can tell they typed the address
     * they meant, without printing a full address onto a page that might be
     * read over a shoulder or resumed on a shared machine.
     */
    public function maskedEmail(): string
    {
        [$local, $domain] = array_pad(explode('@', $this->email, 2), 2, '');

        $visible = mb_substr($local, 0, min(2, max(1, mb_strlen($local) - 1)));

        return $visible.str_repeat('•', 3).'@'.$domain;
    }
}
