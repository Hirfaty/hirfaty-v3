<?php

namespace App\Services;

use App\Models\Address;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * A buyer's address book, and the "exactly one default" rule that goes with it.
 *
 * That rule is enforced here rather than by a database constraint: the only
 * portable way to express it is a partial unique index (`WHERE is_default`),
 * which Postgres supports and SQLite — what the test suite runs on — does not.
 * Keeping it in one service means every write goes through the same clearing
 * step instead of each controller remembering to unset the previous default.
 */
class AddressService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): Address
    {
        return DB::transaction(function () use ($user, $data) {
            /*
             * The first address a buyer saves becomes their default whether
             * they ticked the box or not — an address book with entries but
             * no default would leave checkout with nothing to pre-fill, which
             * is the entire point of saving one.
             */
            $isFirst = ! $user->addresses()->exists();
            $makeDefault = $isFirst || ! empty($data['is_default']);

            if ($makeDefault) {
                $this->clearDefault($user);
            }

            return $user->addresses()->create([
                'label' => $data['label'] ?? null,
                'recipient_name' => $data['recipient_name'] ?? null,
                'recipient_phone' => $data['recipient_phone'] ?? null,
                'address_line' => $data['address_line'],
                'city_id' => $data['city_id'],
                'postal_code' => $data['postal_code'] ?? null,
                // Coalesced rather than passed straight through: the column
                // has a default, but handing Eloquent an explicit null would
                // override it and store nothing.
                'country' => $data['country'] ?? Address::DEFAULT_COUNTRY,
                'is_default' => $makeDefault,
            ]);
        });
    }

    /**
     * Edit an address in place.
     *
     * `is_default` is deliberately not touched: promoting an address is
     * makeDefault()'s job, and letting a plain edit clear the flag would be a
     * way to end up with an address book that has entries but no default —
     * the one state this service exists to prevent.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Address $address, array $data): Address
    {
        $address->update([
            'label' => $data['label'] ?? null,
            'recipient_name' => $data['recipient_name'] ?? null,
            'recipient_phone' => $data['recipient_phone'] ?? null,
            'address_line' => $data['address_line'],
            'city_id' => $data['city_id'],
            'postal_code' => $data['postal_code'] ?? null,
            'country' => $data['country'] ?? Address::DEFAULT_COUNTRY,
        ]);

        return $address;
    }

    public function makeDefault(User $user, Address $address): void
    {
        DB::transaction(function () use ($user, $address) {
            $this->clearDefault($user);
            $address->update(['is_default' => true]);
        });
    }

    /**
     * Remove an address, promoting another to default if this was it — a
     * buyer who deletes their only default shouldn't silently end up with
     * an address book that has entries but no default.
     */
    public function delete(User $user, Address $address): void
    {
        DB::transaction(function () use ($user, $address) {
            $wasDefault = $address->is_default;
            $address->delete();

            if ($wasDefault) {
                $user->addresses()->oldest()->first()?->update(['is_default' => true]);
            }
        });
    }

    private function clearDefault(User $user): void
    {
        $user->addresses()->where('is_default', true)->update(['is_default' => false]);
    }
}
