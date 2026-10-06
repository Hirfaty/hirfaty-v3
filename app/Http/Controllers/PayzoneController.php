<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\PaymentService;
use App\Services\Payzone\PayzoneClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Where Payzone talks to the app: its server-to-server notification, and the
 * buyer's browser coming back from the payment page.
 *
 * Neither route sits behind auth or CSRF (see bootstrap/app.php): the
 * notification comes from Payzone's servers with no session, and the browser
 * may come back by a cross-site POST that carries no session cookie.
 */
class PayzoneController extends Controller
{
    /**
     * Payzone's notification that a charge changed state.
     *
     * Anything but a 200 makes Payzone queue the notification and resend it
     * for three days, so a 200 goes back for everything we have dealt with —
     * declined payments included. A bad signature or an unknown charge gets
     * an error: resending it can do no harm.
     */
    public function callback(Request $request, PayzoneClient $payzone, PaymentService $payments): JsonResponse
    {
        $body = $request->getContent();

        if (! $payzone->verifyCallback($body, $request->header('X-Callback-Signature'))) {
            Log::warning('Payzone callback with an invalid signature', ['ip' => $request->ip()]);

            return response()->json(['status' => 'KO', 'message' => 'Error signature'], 403);
        }

        $data = json_decode($body, true);

        if (! is_array($data) || ! $payments->handleNotification($data)) {
            Log::warning('Payzone callback for an unknown charge', ['id' => is_array($data) ? ($data['id'] ?? null) : null]);

            return response()->json(['status' => 'KO', 'message' => 'Unknown charge'], 404);
        }

        return response()->json(['status' => 'OK', 'message' => 'Status recorded successfully']);
    }

    /**
     * The buyer's browser back from the payment page.
     *
     * Says nothing about whether the money moved — only the notification does.
     * It just hands the buyer to the purchase's payment page, which waits for
     * that notification. A POST is turned into a GET first, so the session
     * cookie (SameSite=Lax) comes along and the buyer is still signed in.
     */
    public function return(Request $request, string $charge): RedirectResponse
    {
        $outcome = in_array($request->query('outcome'), ['success', 'failure', 'cancel'], true)
            ? $request->query('outcome')
            : null;

        if ($request->isMethod('post')) {
            return redirect()->route('payments.payzone.return', array_filter([$charge, 'outcome' => $outcome]), 303);
        }

        $payment = Payment::where('charge_id', $charge)->firstOrFail();

        return redirect()->route('checkout.payment', array_filter([
            'purchase' => $payment->group_reference,
            'outcome' => $outcome,
        ]));
    }
}
