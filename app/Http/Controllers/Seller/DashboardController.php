<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $store = $user->store;

        if (! $store) {
            return redirect()->route('seller.store.create');
        }

        $stats = [
            'total_revenue' => $store->earnings()->where('status', 'paid_out')->sum('net_amount'),
            'pending_payout' => $store->earnings()->where('status', 'available')->sum('net_amount'),
            'total_orders' => $store->orders()->count(),
            'orders_today' => $store->orders()->whereDate('created_at', today())->count(),
            'orders_week' => $store->orders()->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'orders_month' => $store->orders()->whereMonth('created_at', now()->month)->count(),
            'total_products' => $store->products()->count(),
            'published' => $store->products()->where('status', 'published')->count(),
            'pending_review' => $store->products()->where('status', 'pending_review')->count(),
            'total_views' => DB::table('product_views')
                ->whereIn('product_id', $store->products()->pluck('id'))
                ->count(),
        ];

        //  ── Revenue chart — last 6 months ────────────────────
        $revenueChart = $store->earnings()
            ->select(
                DB::raw('MONTH(created_at) as month'),
                DB::raw('YEAR(created_at) as year'),
                DB::raw('SUM(net_amount) as total'),
            )
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => [
                'label' => now()->setMonth($row->month)->format('M'),
                'total' => (float) $row->total,
            ]);

        // ── Order status breakdown ────────────────────────────
        $orderBreakdown = $store->orders()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        // ── Recent orders ─────────────────────────────────────
        $recentOrders = $store->orders()
            ->with(['buyer', 'items.product'])
            ->latest()
            ->limit(5)
            ->get();

        // ── Top products ──────────────────────────────────────
        $topProducts = $store->products()
            ->withCount('orderItems')
            ->with('images')
            ->orderByDesc('order_items_count')
            ->limit(5)
            ->get();

        return Inertia::render('seller/dashboard', [
            'store' => $store,
            'stats' => $stats,
            'revenueChart' => $revenueChart,
            'orderBreakdown' => $orderBreakdown,
            'recentOrders' => $recentOrders,
            'topProducts' => $topProducts,
        ]);
    }
}
