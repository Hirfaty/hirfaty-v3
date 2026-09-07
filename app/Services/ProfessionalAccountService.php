<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\ProfessionalRequestApproved;
use App\Notifications\ProfessionalRequestDeclined;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The buyer → professional_buyer path, start to finish.
 *
 * Trade pricing is worth real money, so it is never self-serve: a buyer asks,
 * an admin reviews, and only approve() writes `role` and
 * `professional_verified_at`. Registration refuses professional_buyer
 * outright (Actions\Fortify\CreateNewUser) and the request columns are
 * non-fillable, so this class is the only way an account can become a trade
 * account — which is exactly why the transition lives here rather than being
 * spread across two controllers.
 */
class ProfessionalAccountService
{
    /**
     * Record a buyer's request for trade access.
     *
     * @throws ValidationException when the account may not ask right now
     */
    public function request(User $user, ?string $message = null): void
    {
        if (! $user->canRequestProfessional()) {
            throw ValidationException::withMessages([
                'professional_request_message' => $user->hasPendingProfessionalRequest()
                    ? 'Votre demande est déjà en cours d’examen.'
                    : 'Ce compte ne peut pas demander un accès professionnel.',
            ]);
        }

        /*
         * forceFill rather than update(): these columns are deliberately kept
         * out of User's Fillable list so no controller can ever mass-assign
         * its way to a trade account. Note this writes the *request* only —
         * role and professional_verified_at are untouched until approve().
         */
        $user->forceFill([
            'professional_request_status' => User::PRO_REQUEST_PENDING,
            'professional_request_message' => $message,
            'professional_requested_at' => now(),
            // A fresh request clears the previous verdict, so a buyer who was
            // turned down once isn't still showing an old refusal reason.
            'professional_reviewed_at' => null,
            'professional_reviewed_by' => null,
            'professional_decline_reason' => null,
        ])->save();
    }

    /**
     * Grant trade access. This is the only place `role` becomes
     * professional_buyer outside the admin's own user editor.
     *
     * `professional_verified_at` is stamped together with the role, in one
     * transaction: Product::audienceFor() gates wholesale pricing on
     * isVerifiedProfessionalBuyer(), which needs both, and an account left
     * half-promoted would read as a professional who never qualifies for the
     * prices — confusing to everyone looking at it.
     */
    public function approve(User $user, User $admin): void
    {
        if (! $user->hasPendingProfessionalRequest()) {
            throw ValidationException::withMessages([
                'professional_request' => 'Cette demande n’est plus en attente.',
            ]);
        }

        DB::transaction(function () use ($user, $admin) {
            $user->forceFill([
                'role' => 'professional_buyer',
                'professional_verified_at' => now(),
                'professional_request_status' => User::PRO_REQUEST_APPROVED,
                'professional_reviewed_at' => now(),
                'professional_reviewed_by' => $admin->id,
                'professional_decline_reason' => null,
            ])->save();
        });

        $user->notify(new ProfessionalRequestApproved);
    }

    /**
     * Refuse the request, with a reason the buyer will be shown. The account
     * stays an ordinary buyer and may ask again later — see
     * User::canRequestProfessional(), which does not block on a refusal.
     */
    public function decline(User $user, User $admin, string $reason): void
    {
        if (! $user->hasPendingProfessionalRequest()) {
            throw ValidationException::withMessages([
                'professional_request' => 'Cette demande n’est plus en attente.',
            ]);
        }

        $user->forceFill([
            'professional_request_status' => User::PRO_REQUEST_DECLINED,
            'professional_reviewed_at' => now(),
            'professional_reviewed_by' => $admin->id,
            'professional_decline_reason' => $reason,
        ])->save();

        $user->notify(new ProfessionalRequestDeclined($reason));
    }
}
