<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Notifications\OrderCancelledByBuyer;
use App\Services\BuyerAccountService;
use Auth;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function __construct(private BuyerAccountService $account) {}

    /**
     * The order history, at /orders — one of the four pages sharing the
     * account sidebar. The listing is a placeholder for now; the `orders`
     * prop is already the flattened shape the cards will read.
     */
    public function index(Request $request)
    {
        return Inertia::render('buyer/orders/index', [
            'orders' => $this->account->orders($request->user()),
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

    public function cancel(Request $request, Order $order)
    {
        if ($order->buyer_id !== Auth::id()) {
            abort(403);
        }

        if ($order->status !== 'pending') {
            return back()->withErrors(['message' => 'Only pending orders can be cancelled.']);
        }

        $order->update([
            'status' => 'cancelled',
            'cancellation_reason' => $request->input('cancellation_reason'),
        ]);

        // Notify seller
        try {
            $order->store->user->notify(new OrderCancelledByBuyer($order));
        } catch (\Exception $e) {
            \Log::warning('Failed to notify seller of cancellation: '.$e->getMessage());
        }

        foreach ($order->items as $item) {
            $item->product->increment('stock', $item->quantity);
        }

        return back()->with('success', 'Order cancelled successfully.');
    }
}
