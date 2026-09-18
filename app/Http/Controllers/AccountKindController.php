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
     * The one with a real design question behind it: `professional_buyer`
     * decides which price list an account is billed on, so it is deliberately
     * impossible to self-assign — the role is granted only by an admin review
     * (Admin\UserController::approveProfessional), and
     * RegistrationCodeService::complete hardcodes `buyer`. Trade signup needs
     * that vetting step designed with it rather than a form bolted on, which
     * is why this is a page and not a form.
     */
    public function business(): Response
    {
        return Inertia::render('join/business');
    }
}
