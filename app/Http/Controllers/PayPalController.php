<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\PayPal\PayPalClient;
use App\Services\PayPalPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Where PayPal talks to the app: its webhooks, and the buyer's browser coming
 * back from approving (or abandoning) a payment.
 *
 * Neither route sits behind auth, and the webhook is exempt from CSRF (see
 * bootstrap/app.php): PayPal's servers have no session, and the return is
 * keyed by the attempt's unguessable charge id rather than by who is signed
 * in — the money is taken even if the buyer's session lapsed while they were
 * on PayPal.
 */
class PayPalController extends Controller
{
    /**
     * One of PayPal's webhook events.
     *
     * A verified event always gets a 200, acted on or not: PayPal resends
     * anything else for three days, and resending an event about a payment we
     * do not know cannot make us know it. An unverifiable one gets a 400.
     */
    public function webhook(Request $request, PayPalClient $paypal, PayPalPaymentService $payments): JsonResponse
    {
        $body = $request->getContent();

        if (! $paypal->verifyWebhook($body, $request->headers)) {
            Log::warning('PayPal webhook that could not be verified', ['ip' => $request->ip()]);

            return response()->json(['status' => 'invalid'], 400);
        }

        $event = json_decode($body, true);

        if (! $payments->handleWebhook($event)) {
            Log::info('PayPal webhook ignored', [
                'id' => $event['id'] ?? null,
                'type' => $event['event_type'] ?? null,
            ]);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * The buyer's browser back from PayPal. Approved: take the money now and
     * hand them to their payment page, which goes straight on to the
     * confirmation once the purchase is placed. Cancelled: back to the
     * payment page to try again or give up.
     */
    public function return(Request $request, string $charge, PayPalPaymentService $payments): RedirectResponse
    {
        $payment = Payment::where('provider', Payment::PROVIDER_PAYPAL)
            ->where('charge_id', $charge)
            ->firstOrFail();

        $page = fn (?string $outcome) => redirect()->route('checkout.payment', array_filter([
            'purchase' => $payment->group_reference,
            'outcome' => $outcome,
        ]));

        if ($request->query('outcome') === 'cancel') {
            $payments->abandon($payment);

            return $page('cancel');
        }

        // PayPal names the order the buyer approved; it must be this attempt's.
        if ($request->query('token') !== $payment->provider_order_id) {
            return $page(null);
        }

        return $page($payments->capture($payment));
    }
}
