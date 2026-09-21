<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * What the shop has sold, in order money.
 *
 * Replaces the old earnings screen, which broke each order down into gross /
 * platform fee / net payout. That breakdown is not shown to anyone for now —
 * the `earnings` ledger is still written on every checkout (CheckoutService),
 * it simply has no UI. Everything here is order value: what the buyer paid,
 * never what the artisan is owed after commission.
 */
class SalesController extends Controller
{
    public function index(Request $request)
    {
        $store = $request->user()->store;

        $orders = $store->orders()
            ->with(['buyer', 'items.product'])
            ->latest()
            ->paginate(20);

        $summary = [
            // Money actually banked: only a delivered order has completed.
            'delivered_total' => (float) $store->orders()
                ->where('status', 'delivered')
                ->sum('total_amount'),
            // Still moving through fulfilment — neither delivered nor written off.
            'in_progress_total' => (float) $store->orders()
                ->whereNotIn('status', ['delivered', 'cancelled'])
                ->sum('total_amount'),
            'orders_count' => $store->orders()->where('status', '!=', 'cancelled')->count(),
        ];

        return Inertia::render('seller/sales/index', [
            'orders' => $orders,
            'summary' => $summary,
        ]);
    }
}
