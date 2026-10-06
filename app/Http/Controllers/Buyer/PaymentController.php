<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The buyer's side of paying for a purchase by card: the redirect to Payzone,
 * the page they come back to, and cancelling when the card does not go
 * through and they would rather not try again.
 */
class PaymentController extends Controller
{
    /**
     * Send the buyer to Payzone's payment page.
     *
     * A plain HTML page that posts itself, as Payzone's kit does it — reached
     * by a full page visit (Inertia::location from checkout), never by XHR.
     * Every visit is a new attempt with its own chargeId, as Payzone requires.
     */
    public function launch(Request $request, string $purchase, PaymentService $payments)
    {
        $form = $payments->launch($request->user(), $purchase, app()->getLocale());

        if ($form === null) {
            return redirect()->route('checkout.payment', $purchase);
        }

        return response()
            ->view('payments.payzone-launch', $form)
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Where the buyer lands back from Payzone, and waits while its
     * notification arrives. Once the purchase is placed, the confirmation page
     * takes over.
     */
    public function show(Request $request, string $purchase, PaymentService $payments)
    {
        $status = $payments->status($request->user(), $purchase) ?? abort(404);

        if ($status['state'] === 'placed') {
            return redirect()->route('checkout.confirmation', $purchase);
        }

        $outcome = $request->query('outcome');

        return Inertia::render('buyer/checkout-payment', [
            'payment' => $status + [
                'outcome' => in_array($outcome, ['success', 'failure', 'cancel'], true) ? $outcome : null,
            ],
        ]);
    }

    public function cancel(Request $request, string $purchase, PaymentService $payments)
    {
        $payments->cancel($request->user(), $purchase);

        // The cart was kept through the card attempt, so it is all still there.
        return redirect()->route('cart.show');
    }
}
