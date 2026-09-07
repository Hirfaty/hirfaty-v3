<?php

namespace App\Services;

use App\Models\Address;
use App\Models\User;

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
     * @param  array<string, mixed>  $data  validated CheckoutRequest input
     * @return array{shipping_address: string, shipping_phone: string, city_id: int, notes: string|null}
     */
    public function resolve(User $user, array $data): array
    {
        $saved = $this->savedAddress($user, $data);

        if ($saved !== null) {
            return [
                'shipping_address' => $saved->toShippingLine(),
                // From the form, not the row: the column is nullable, so an
                // address saved before the phone was mandatory has none, and
                // the buyer may want a different number for this delivery.
                'shipping_phone' => (string) $data['shipping_phone'],
                'city_id' => (int) $saved->city_id,
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
                'recipient_phone' => $data['shipping_phone'],
                'address_line' => $data['shipping_address'],
                'city_id' => $data['city_id'],
            ]);
        }

        return [
            'shipping_address' => (string) $data['shipping_address'],
            'shipping_phone' => (string) $data['shipping_phone'],
            'city_id' => (int) $data['city_id'],
            'notes' => $data['notes'] ?? null,
        ];
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
