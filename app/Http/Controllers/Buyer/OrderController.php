<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Notifications\OrderCancelledByBuyer;
use Auth;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::where('buyer_id', Auth::id())
            ->with(['store', 'items.product.images', 'city'])
            ->latest()
            ->paginate(10);

        return Inertia::render('buyer/account/orders', [
            'orders' => $orders,
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
