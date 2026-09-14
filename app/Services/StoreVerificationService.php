<?php

namespace App\Services;

use App\Models\Store;
use App\Models\User;
use App\Notifications\StoreApproved;
use App\Notifications\StoreRejected;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The review a shop goes through before it may sell, mirroring the one
 * products already have (see Admin\ProductController).
 *
 * Kept out of the controllers because three places move a shop between states —
 * the seller submitting, the admin deciding, and the settings screen
 * resubmitting after a rejection — and they have to agree on what each
 * transition clears.
 */
class StoreVerificationService
{
    /**
     * Put a shop into the queue, once it actually has something to review.
     *
     * No-ops on an incomplete profile rather than throwing: this runs on every
     * settings save, and a seller half-way through filling the form has not
     * done anything wrong. It also no-ops on an already-approved shop, so
     * editing a live shop's description does not knock it back into review.
     */
    public function submit(Store $store): Store
    {
        if ($store->isApproved() || ! $store->hasCompleteProfile()) {
            return $store;
        }

        $store->update([
            'verification_status' => Store::VERIFICATION_PENDING,
            'submitted_at' => now(),
            // A resubmission after a rejection starts clean; leaving the old
            // reason would keep showing the seller a complaint they just fixed.
            'rejection_reason' => null,
        ]);

        return $store->refresh();
    }

    public function approve(Store $store, User $admin): Store
    {
        DB::transaction(function () use ($store, $admin) {
            $store->update([
                'verification_status' => Store::VERIFICATION_APPROVED,
                'approved_at' => now(),
                'approved_by' => $admin->id,
                'rejection_reason' => null,
                // Approval is also what makes the shop publicly listable —
                // Buyer\StoreController filters on this.
                'status' => 'active',
            ]);
        });

        $this->notify($store, new StoreApproved($store->refresh()));

        return $store;
    }

    public function reject(Store $store, User $admin, string $reason): Store
    {
        DB::transaction(function () use ($store, $admin, $reason) {
            $store->update([
                'verification_status' => Store::VERIFICATION_REJECTED,
                'rejection_reason' => $reason,
                'approved_by' => $admin->id,
                'approved_at' => null,
                // Pulled from the public listing for as long as it is refused.
                'status' => 'inactive',
            ]);
        });

        $this->notify($store, new StoreRejected($store->refresh(), $reason));

        return $store;
    }

    /**
     * Mail the seller, but never let a mail failure undo the decision — the
     * same guard Admin\ProductController uses around its own notifications.
     */
    private function notify(Store $store, object $notification): void
    {
        try {
            $store->user?->notify($notification);
        } catch (\Throwable $e) {
            Log::warning('Failed to notify seller of store verification: '.$e->getMessage());
        }
    }
}
