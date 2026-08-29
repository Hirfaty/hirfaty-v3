<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Notifications\OrderCancelled;
use App\Notifications\OrderConfirmed;
use App\Notifications\OrderShipped;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->store) {
            abort(403, 'Seller store not found.');
        }

        $orders = $user->store
            ->orders()
            ->with(['buyer', 'items.product', 'city'])
            ->latest()
            ->paginate(40);

        return Inertia::render('seller/orders/index', [
            'orders' => $orders,
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
            'cancellationReasons' => $this->cancellationReasons(),
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
            'status' => ['required', 'in:confirmed,shipped,cancelled'],
            'tracking_number' => ['required_if:status,shipped', 'nullable', 'string'],
            'cancellation_reason' => ['required_if:status,cancelled', 'nullable', 'string', 'max:500'],
        ]);

        // Guard against going backwards
        $flow = ['pending', 'confirmed', 'shipped', 'delivered'];
        $currentIndex = array_search($order->status, $flow);
        $newIndex = array_search($data['status'], $flow);

        if ($newIndex <= $currentIndex) {
            return back()->withErrors(['status' => 'Cannot move order backwards.']);
        }

        $order->update([
            'status' => $data['status'],
            'tracking_number' => $data['tracking_number'] ?? $order->tracking_number,
            'cancellation_reason' => $data['cancellation_reason'] ?? null,
            'delivered_at' => $data['status'] === 'delivered' ? now() : $order->delivered_at,
        ]);

        $order->load('store');

        try {
            match ($data['status']) {
                'confirmed' => $order->buyer->notify(new OrderConfirmed($order)),
                'shipped' => $order->buyer->notify(new OrderShipped($order)),
                'cancelled' => $order->buyer->notify(new OrderCancelled($order)),
                default => null,
            };
        } catch (\Exception $e) {
            // Log the error but don't fail the request
            \Log::error('Failed to send order status notification: '.$e->getMessage());
        }

        return back()->with('success', 'Order status updated.');
    }

    private function cancellationReasons(): array
    {
        return [
            'out_of_stock' => 'Product is no longer available',
            'pricing_error' => 'Pricing error on the listing',
            'unable_to_fulfill' => 'Unable to fulfill this order',
            'buyer_request' => 'Cancelled at buyer\'s request',
            'other' => 'Other reason',
            'wrong_address' => 'Address is incorrect or incomplete',
            'unreachable' => 'Buyer was unreachable after multiple attempts',
            'refused' => 'Buyer refused the package',
        ];
    }

    // ─── Private ─────────────────────────────────────────────
    private function authorizeOrder(Order $order): void
    {
        $user = Auth::user();
        if (! $user || ! $user->store || $order->store_id !== $user->store->id) {
            abort(403);
        }
    }
}
