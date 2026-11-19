<?php

namespace App\Services;

use App\Models\User;
use App\Support\Contact;

/**
 * Which account an email address or phone number belongs to.
 *
 * An address matches case-insensitively, as it always has. A number matches
 * only once it has been *verified* — proven by an SMS code. `phone_number` was
 * free text long before anyone could sign in with it (typed at signup, at
 * checkout, by an admin), and matching on it unverified would let whoever owns
 * a mistyped number receive a code that opens someone else's account.
 */
class ContactAccountService
{
    public function find(Contact $contact): ?User
    {
        if ($contact->isEmail()) {
            return User::whereRaw('LOWER(email) = ?', [$contact->value])->first();
        }

        return User::where('phone_number', $contact->value)
            ->whereNotNull('phone_verified_at')
            ->first();
    }

    /**
     * Whether an account carries this number without having proven it.
     *
     * Such a number cannot open a new account either: `phone_number` is
     * unique, and handing it to a newcomer would mean taking it off an
     * existing customer on the strength of a code. The visitor is told to
     * sign in with their address instead.
     */
    public function phoneHeldUnverified(Contact $contact): bool
    {
        return $contact->isPhone()
            && User::where('phone_number', $contact->value)->whereNull('phone_verified_at')->exists();
    }
}
