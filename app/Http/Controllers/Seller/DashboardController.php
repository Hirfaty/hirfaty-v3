<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $store = $user->store;

        /*
         * Defensive only. Every seller has a shop — signup creates both in one
         * transaction (RegistrationCodeService::completeSeller), and admin
         * promotion creates one too (Admin\UserController) — so this guards a
         * row deleted by hand rather than a state the app can reach. There is
         * no setup wizard to send them to any more; the holding page copes
         * with a null shop, so it is the honest fallback.
         */
        if (! $store) {
            return redirect()->route('seller.welcome');
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

        /*
         * Revenue chart — last 6 months.
         *
         * Grouped in PHP rather than SQL on purpose: this used to call
         * MONTH()/YEAR(), which are MySQL-only. SQLite (local and CI) has no
         * such functions and Postgres (production) spells them EXTRACT, so the
         * query threw on every environment this project actually runs on — it
         * only stayed hidden because the dashboard route was disabled.
         *
         * Six months of one shop's earnings is a small set, so pulling the
         * rows and bucketing them here costs nothing and works on all three.
         */
        $revenueChart = $store->earnings()
            ->where('created_at', '>=', now()->subMonths(6)->startOfMonth())
            ->get(['net_amount', 'created_at'])
            ->groupBy(fn ($earning) => $earning->created_at->format('Y-m'))
            ->map(fn ($group, $key) => [
                'label' => CarbonImmutable::createFromFormat('Y-m', $key)->format('M'),
                'total' => (float) $group->sum('net_amount'),
            ])
            // groupBy keys are "YYYY-MM", so a plain key sort is chronological
            // and does not put December before February.
            ->sortKeys()
            ->values();

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
