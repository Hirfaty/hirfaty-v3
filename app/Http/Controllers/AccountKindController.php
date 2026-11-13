<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Hirfaty Business — the trade door, still a holding page.
 *
 * This used to serve a pair. Hirfaty Seller outgrew it once /sell became a
 * real signup and moved to Auth\SellerRegistrationController; what is left
 * here is the one door that genuinely has nothing behind it yet.
 *
 * Deliberately *outside* the auth namespace and off the /login prefix.
 * `/login/business` would read as "sign in as a business", which is the
 * opposite of what this page is: it is where someone goes to find out about,
 * and eventually apply for, an account they do not have.
 */
class AccountKindController extends Controller
{
    /**
     * The trade door.
     *
     * Nothing to sign up for: pro prices are open to anyone who switches to
     * "Pro" (PriceModeService), with no trade account to apply for — see
     * /professional-account. This page stays a placeholder until a separate
     * business signup is actually designed.
     */
    public function business(): Response
    {
        return Inertia::render('join/business');
    }
}
