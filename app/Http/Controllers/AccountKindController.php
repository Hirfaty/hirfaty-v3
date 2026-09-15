<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * The two account kinds that are not "I am a shopper": Hirfaty Seller and
 * Hirfaty Business.
 *
 * Holding pages for now — the entry points on /login are settled, the flows
 * behind them are not.
 *
 * Deliberately *outside* the auth namespace and off the /login prefix.
 * `/login/seller` would read as "sign in as a seller", which is the opposite
 * of what these pages are: they are where someone goes to find out about, and
 * eventually apply for, an account they do not have. `/sell` and `/business`
 * say that, and are what a marketplace conventionally uses.
 */
class AccountKindController extends Controller
{
    /** Hirfaty Seller — the artisan door. */
    public function seller(): Response
    {
        return Inertia::render('join/seller');
    }

    /**
     * Hirfaty Business — the trade door.
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
