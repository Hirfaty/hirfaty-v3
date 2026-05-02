<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Order;
use App\Notifications\OrderShipped;
use App\Notifications\OrderConfirmed;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $orders = auth()->user()
                    ->store
                    ->orders()
                    ->with(['buyer', 'items.product', 'city'])
                    ->latest()
                    ->paginate(20);
       
        return Inertia::render('seller/orders/index', [
            'pageProps' => $orders,
        ]);
    }

  

    /**
     * Display the specified resource.
     */
    public function show(Order $order)
    {
        $this->authorizeOrder($order);

        $order->load(['buyer', 'items.product.images', 'city', 'earning']);

        return Inertia::render('seller/orders/show', [
            'order' => $order,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function updateStatus(Request $request, Order $order)
    {
        $this->authorizeOrder($order);

        $data = $request->validate([
            'status'          => ['required', 'in:confirmed,shipped,cancelled'],
            'tracking_number' => ['required_if:status,shipped', 'nullable', 'string'],
        ]);

        // Guard against going backwards
        $flow = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];
        $currentIndex = array_search($order->status, $flow);
        $newIndex     = array_search($data['status'], $flow);

        if ($newIndex <= $currentIndex) {
            return back()->withErrors(['status' => 'Cannot move order backwards.']);
        }

        $order->update([
            'status'          => $data['status'],
            'tracking_number' => $data['tracking_number'] ?? $order->tracking_number,
            'delivered_at'    => $data['status'] === 'delivered' ? now() : $order->delivered_at,
        ]);

        $order->load('store');

        match($data['status']) {
            'confirmed' => $order->buyer->notify(new OrderConfirmed($order)),
            'shipped' => $order->buyer->notify(new OrderShipped($order)),
            default => null,
        };

        return back()->with('success', 'Order status updated.');
    }

    // ─── Private ─────────────────────────────────────────────
    private function authorizeOrder(Order $order): void
    {
        if ($order->store_id !== auth()->user()->store->id) {
            abort(403);
        }
    }
}
