<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Saved payment methods, at /payment_method.
 *
 * Nothing is stored yet — there is no payment provider wired up and checkout
 * does not take card details (CheckoutService bills nothing at capture time).
 * The page exists so the account sidebar has its fourth destination; it stays
 * a placeholder until a provider is chosen.
 */
class PaymentMethodController extends Controller
{
    public function __invoke(Request $request)
    {
        return Inertia::render('buyer/payment-methods/index');
    }
}
