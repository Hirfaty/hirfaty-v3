<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A code emailed to an existing account for passwordless sign-in. See the
 * migration for why this is its own table rather than a pending registration.
 *
 * @property int $id
 * @property int $user_id
 * @property string $code_hash
 * @property int $attempts
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $last_sent_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
// The hash is a credential: hiding it keeps it out of any array/JSON cast, so
// a careless `->toArray()` into an Inertia prop can't ship it to the browser.
#[Hidden(['code_hash'])]
class LoginCode extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Past its validity window — a new one must be sent. */
    public function hasExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
