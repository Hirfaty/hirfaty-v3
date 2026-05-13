<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Auth;
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
}
