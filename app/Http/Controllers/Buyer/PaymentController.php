<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\BuyNowService;
use App\Services\PaymentService;
use App\Services\PayPalPaymentService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The buyer's side of paying for a purchase online: the redirect to Payzone
 * or PayPal, the page they come back to, and cancelling when the payment does
 * not go through and they would rather not try again.
 */
class PaymentController extends Controller
{
    /**
     * Send the buyer to pay: PayPal's approval page, or Payzone's.
     *
     * Reached by a full page visit (Inertia::location from checkout), never
     * by XHR. Every visit is a new attempt with its own chargeId, as Payzone
     * requires — and, for PayPal, its own PayPal order. Payzone's is a plain
     * HTML page that posts itself, as Payzone's kit does it.
     */
    public function launch(Request $request, string $purchase, PaymentService $payments, PayPalPaymentService $paypal)
    {
        if ($payments->methodOf($request->user(), $purchase) === Order::PAYMENT_PAYPAL) {
            $approveUrl = $paypal->launch($request->user(), $purchase, app()->getLocale());

            return $approveUrl !== null
                ? redirect()->away($approveUrl)
                : redirect()->route('checkout.payment', $purchase);
        }

        $form = $payments->launch($request->user(), $purchase, app()->getLocale());

        if ($form === null) {
            return redirect()->route('checkout.payment', $purchase);
        }

        return response()
            ->view('payments.payzone-launch', $form)
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Where the buyer lands back from Payzone or PayPal, and waits while the
     * payment is confirmed. Once the purchase is placed, the confirmation page
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

    public function cancel(Request $request, string $purchase, PaymentService $payments, BuyNowService $buyNow)
    {
        $payments->cancel($request->user(), $purchase);

        // Bought with "Buy now": the piece was never in the cart, so back to its page.
        $slug = $buyNow->productSlugFor($request->session(), $purchase);

        if ($slug !== null) {
            return redirect()->route('products.show', $slug);
        }

        // The cart was kept through the payment attempt, so it is all still there.
        return redirect()->route('cart.show');
    }
}
