<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Notifications\OrderCancelledByBuyer;
use App\Services\BuyerAccountService;
use App\Services\OrderStatusService;
use App\Services\PurchaseInvoiceService;
use Auth;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function __construct(private BuyerAccountService $account) {}

    /**
     * The order history, at /orders — one of the four pages sharing the
     * account sidebar.
     *
     * One card per *purchase*, not per Order row: a basket spanning three
     * workshops is three orders in the database but one thing the buyer
     * bought. See BuyerAccountService::purchases().
     */
    public function index(Request $request)
    {
        return Inertia::render('buyer/orders/index', [
            'purchases' => $this->account->purchases($request->user()),
        ]);
    }

    /**
     * "Download invoice" on an order card — one PDF for the whole purchase,
     * keyed by the same reference the card shows. Another buyer's reference
     * 404s rather than 403s, so it does not confirm the purchase exists.
     */
    public function invoice(Request $request, string $purchase, PurchaseInvoiceService $invoices)
    {
        return $invoices->download($request->user(), $purchase) ?? abort(404);
    }

    /**
     * "Track my order" — the purchase's history, step by step, with the
     * desk's notes on where the parcel is.
     */
    public function tracking(Request $request, string $purchase)
    {
        $tracking = $this->account->tracking($request->user(), $purchase) ?? abort(404);

        return Inertia::render('buyer/orders/tracking', $tracking);
    }

    /**
     * "Rate my items" — every piece in the purchase, with a rating and a
     * comment for each. Pieces from a workshop that hasn't delivered yet are
     * listed but closed; ReviewService decides what is open.
     */
    public function review(Request $request, string $purchase)
    {
        return Inertia::render('buyer/orders/review', [
            'purchase' => $this->account->purchase($request->user(), $purchase) ?? abort(404),
        ]);
    }

    public function show(Order $order)
    {
        if ($order->buyer_id !== Auth::id()) {
            abort(403);
        }

        $order->load(['store', 'items.product.images', 'city', 'earning']);

        return Inertia::render('/buyer/orders/show', [
            'order' => $order,
        ]);
    }

    public function cancel(Request $request, Order $order, OrderStatusService $orderStatus)
    {
        if ($order->buyer_id !== Auth::id()) {
            abort(403);
        }

        if ($order->status !== 'pending') {
            return back()->withErrors(['message' => 'Only pending orders can be cancelled.']);
        }

        $orderStatus->transition($order, 'cancelled', [
            'cancellation_reason' => $request->input('cancellation_reason'),
        ]);

        // Notify seller
        try {
            $order->store->user->notify(new OrderCancelledByBuyer($order));
        } catch (\Exception $e) {
            \Log::warning('Failed to notify seller of cancellation: '.$e->getMessage());
        }

        return back()->with('success', 'Order cancelled successfully.');
    }
}
