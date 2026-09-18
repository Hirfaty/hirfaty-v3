<?php

namespace App\Services;

use App\Models\Address;
use App\Models\City;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * Turns what the checkout form submitted into the one place an order ships to.
 *
 * Kept apart from CheckoutService, which is the money path: this decides
 * *where* a parcel goes, that one decides what it costs. Both are consumed by
 * the same controller action, but neither needs to know the other's rules.
 *
 * The result is deliberately a flat snapshot rather than a reference to the
 * Address row. An order must keep the address it was actually shipped to, so
 * that editing or deleting a saved address later cannot rewrite where a past
 * parcel went.
 */
class CheckoutDestinationService
{
    public function __construct(private AddressService $addresses) {}

    /**
     * `city_id` is null for an international destination: `cities` holds
     * Moroccan cities only. `country` rides along with it because delivery is
     * priced per destination — see DeliveryFeeService.
     *
     * @param  array<string, mixed>  $data  validated CheckoutRequest input
     * @return array{shipping_address: string, shipping_phone: string, city_id: int|null, country: string, notes: string|null}
     */
    public function resolve(User $user, array $data): array
    {
        $recipient = trim($data['first_name'].' '.$data['last_name']);

        $this->backfillProfile($user, $data);

        $saved = $this->savedAddress($user, $data);

        if ($saved !== null) {
            return [
                /*
                 * Prefixed with the name typed on this order rather than the
                 * one stored on the address: the buyer may be sending this
                 * parcel to someone else without wanting to rewrite their saved
                 * address, and the label has to name whoever signs for it.
                 */
                'shipping_address' => $this->withRecipient($recipient, $saved->toShippingLine()),
                // From the form, not the row: the column is nullable, so an
                // address saved before the phone was mandatory has none, and
                // the buyer may want a different number for this delivery.
                'shipping_phone' => (string) $data['shipping_phone'],
                // Not cast to int: an international address has no Moroccan
                // city, and (int) null is 0 — a city id that does not exist.
                'city_id' => $saved->city_id,
                'country' => $saved->country ?: Address::DEFAULT_COUNTRY,
                'notes' => $data['notes'] ?? null,
            ];
        }

        /*
         * A new address. Saving it to the book is opt-in — a one-off delivery
         * to a friend shouldn't silently join the buyer's saved addresses.
         */
        if (! empty($data['save_address'])) {
            $this->addresses->create($user, [
                'label' => $data['address_label'] ?? null,
                'recipient_name' => $recipient,
                'recipient_phone' => $data['shipping_phone'],
                'address_line' => $data['shipping_address'],
                'city_id' => $data['city_id'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'country' => $data['country'] ?? null,
            ]);
        }

        /*
         * Assembled exactly the way Address::toShippingLine() assembles a saved
         * one, so an order placed down either branch reads identically on the
         * parcel label.
         */
        $cityLine = trim(collect([
            $this->cityName($data['city_id'] ?? null),
            $data['postal_code'] ?? null,
        ])->filter()->implode(' '));

        return [
            'shipping_address' => $this->withRecipient(
                $recipient,
                trim(collect([$data['shipping_address'], $cityLine])->filter()->implode(', ')),
            ),
            'shipping_phone' => (string) $data['shipping_phone'],
            // Left null rather than cast: see the saved-address branch above.
            'city_id' => $data['city_id'] ?? null,
            'country' => $data['country'] ?? Address::DEFAULT_COUNTRY,
            'notes' => $data['notes'] ?? null,
        ];
    }

    /**
     * Put the name Hirfaty just learned onto an account that has none.
     *
     * Signup stopped asking for a name (RegistrationCodeService::complete), so
     * for most buyers checkout is the first screen that collects one. Filling
     * the blanks here means they are not asked again on the next order.
     *
     * Only ever fills blanks. Overwriting would let a one-off delivery to a
     * friend silently rename the account — a buyer edits their own name from
     * /account, and that decision has to win over whatever a delivery form
     * happened to contain.
     */
    private function backfillProfile(User $user, array $data): void
    {
        $fill = array_filter([
            'first_name' => blank($user->first_name) ? $data['first_name'] : null,
            'last_name' => blank($user->last_name) ? $data['last_name'] : null,
            'phone_number' => blank($user->phone_number) ? $data['shipping_phone'] : null,
        ]);

        if ($fill === []) {
            return;
        }

        /*
         * `users.phone_number` carries a UNIQUE index and the number typed here
         * has not been checked against it — two people in one household sharing
         * a landline is ordinary. A collision must not cost the buyer their
         * order, so it is swallowed rather than raised: the number is already
         * on the order itself, which is what the courier actually reads.
         */
        try {
            $user->fill($fill)->save();
        } catch (QueryException $e) {
            Log::info("Skipped checkout profile backfill for user {$user->id}: ".$e->getMessage());
        }
    }

    /** "Name, rest of the address", skipping the prefix when it is blank. */
    private function withRecipient(string $recipient, string $line): string
    {
        return trim(collect([$recipient, $line])->filter()->implode(', '));
    }

    private function cityName(mixed $cityId): ?string
    {
        return $cityId === null ? null : City::find($cityId)?->name;
    }

    /**
     * The chosen address out of the buyer's own book, or null when they typed
     * a new one.
     *
     * Re-checks ownership even though CheckoutRequest already scoped the
     * `exists` rule to this user: this service is the thing that decides where
     * a parcel physically goes, and it should not depend on a caller having
     * validated correctly to keep that safe.
     */
    private function savedAddress(User $user, array $data): ?Address
    {
        if (empty($data['address_id'])) {
            return null;
        }

        return $user->addresses()
            ->with('city')
            ->whereKey($data['address_id'])
            ->first();
    }
}
