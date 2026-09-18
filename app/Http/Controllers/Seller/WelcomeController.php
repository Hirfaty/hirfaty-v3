<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Your workshop is with us — hold on a moment."
 *
 * The last screen of seller signup. The account exists and the shop is filed,
 * but `verification_status` is `pending` and `status` is `inactive`, so nothing
 * publishes until an admin clears it (StoreVerificationService). This page is
 * where that expectation gets set, before the seller uploads a catalogue and
 * wonders why the marketplace never shows it.
 *
 * It forwards itself to the dashboard after a few seconds rather than making
 * the seller click: there is nothing to decide here, only something to be
 * told. The dashboard is reachable regardless — a pending shop is a shop that
 * cannot *sell*, not one that cannot be looked at.
 *
 * Written to survive a refresh or a bookmark, so it reads the shop off the
 * user rather than trusting anything in the querystring.
 */
class WelcomeController extends Controller
{
    public function index(Request $request): Response
    {
        $store = $request->user()->store;

        return Inertia::render('seller/welcome', [
            'storeName' => $store?->store_name,
            // Drives the copy: an approved seller who bookmarked this page
            // should not be told they are still waiting on a review.
            'isPending' => (bool) $store?->isPendingVerification(),
        ]);
    }
}
