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
        $user = $request->user();

        return Inertia::render('buyer/professional-account', [
            'status' => $user->professional_request_status,
            'canRequest' => $user->canRequestProfessional(),
            'isProfessional' => $user->isProfessionalBuyer(),
            /*
             * Separate from isProfessional because the two can legitimately
             * disagree — an admin can set the role by hand without stamping
             * the verification, and only the verified pair actually earns
             * wholesale prices (Product::audienceFor). The page shows a
             * different message for each.
             */
            'isVerified' => $user->isVerifiedProfessionalBuyer(),
            'submittedMessage' => $user->professional_request_message,
            'requestedAt' => $user->professional_requested_at,
            'declineReason' => $user->professional_decline_reason,
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
