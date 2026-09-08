<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Services\ProfessionalAccountService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Asking for trade access outside the onboarding tour.
 *
 * Onboarding offers this on its last step, but it is skippable and a riad
 * often only realises later that it buys in bulk — without a standalone page
 * anyone who skipped could never ask at all.
 */
class ProfessionalRequestController extends Controller
{
    public function __construct(private ProfessionalAccountService $professional) {}

    public function show(Request $request)
    {
        /*
         * Null for a guest. This page is public so that the people it exists
         * to convince — those who have not signed up yet — can actually read
         * it, so every prop below has a signed-out answer rather than
         * reaching through $user. The explanation is identical for everyone;
         * only the call to action differs.
         */
        $user = $request->user();

        return Inertia::render('buyer/professional-account', [
            'status' => $user?->professional_request_status,
            // False for a guest: there is no account to upgrade yet, which is
            // exactly what the page's CTA offers them instead.
            'canRequest' => (bool) $user?->canRequestProfessional(),
            'isProfessional' => (bool) $user?->isProfessionalBuyer(),
            /*
             * Separate from isProfessional because the two can legitimately
             * disagree — an admin can set the role by hand without stamping
             * the verification, and only the verified pair actually earns
             * wholesale prices (Product::audienceFor). The page shows a
             * different message for each.
             */
            'isVerified' => (bool) $user?->isVerifiedProfessionalBuyer(),
            'submittedMessage' => $user?->professional_request_message,
            'requestedAt' => $user?->professional_requested_at,
            'declineReason' => $user?->professional_decline_reason,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'min:20', 'max:1500'],
        ], [
            'message.required' => 'Expliquez-nous en quelques mots votre activité.',
            'message.min' => 'Donnez-nous un peu plus de détails (20 caractères minimum).',
        ]);

        /*
         * The service re-checks eligibility and throws on a duplicate: this
         * endpoint is reachable directly, so "can't ask twice" cannot live in
         * the page's conditional rendering alone.
         */
        $this->professional->request($request->user(), $data['message']);

        return back()->with('success', 'Votre demande a bien été envoyée. Nous revenons vers vous rapidement.');
    }
}
