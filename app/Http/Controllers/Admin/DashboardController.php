<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\HealthReportService;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /** French short month names, indexed 1-12 — avoids relying on the
     *  server locale for Carbon::translatedFormat(). */
    private const MONTHS = [
        1 => 'Janv', 2 => 'Févr', 3 => 'Mars', 4 => 'Avr',
        5 => 'Mai', 6 => 'Juin', 7 => 'Juil', 8 => 'Août',
        9 => 'Sept', 10 => 'Oct', 11 => 'Nov', 12 => 'Déc',
    ];

    public function index(HealthReportService $health)
    {
        /*
         * ── KPI Stats ──────────────────────────────────────────
         *
         * Order money only. The commission take, payout balances and
         * seller-owed figures that used to live here are deliberately gone:
         * no screen reports what Hirfaty or an artisan earns for now. The
         * `earnings` ledger is still written on every checkout
         * (CheckoutService), so this is a display decision, not a data one —
         * turning the numbers back on is a matter of re-adding the queries.
         */
        $stats = [
            'total_orders' => Order::count(),
            'total_revenue' => (float) Order::where('status', 'delivered')->sum('total_amount'),
            'pending_revenue' => (float) Order::whereNotIn('status', ['delivered', 'cancelled'])->sum('total_amount'),
            'cancelled_orders' => Order::where('status', 'cancelled')->count(),
            // 'pending_review' is the actual Product::status value — this
            // previously filtered on 'pending', a status that never exists,
            // so this count (and the list below) were always zero.
            'pending_products' => Product::where('status', 'pending_review')->count(),
            'total_users' => User::count(),
            'total_sellers' => User::where('role', 'seller')->count(),
            'total_buyers' => User::where('role', 'buyer')->count(),
        ];

        // ── Recent Activity ────────────────────────────────────
        $latestUsers = User::where('role', '!=', 'super_admin')
            ->latest()
            ->take(6)
            ->get(['id', 'first_name', 'last_name', 'email', 'role', 'created_at']);

        $pendingApproval = Product::with(['store', 'category', 'media'])
            ->where('status', 'pending_review')
            ->latest()
            ->take(5)
            ->get();

        return Inertia::render('admin/dashboard', [
            'stats' => $stats,
            'latestUsers' => $latestUsers,
            'pendingProducts' => $pendingApproval,
            'salesTrend' => $this->salesTrend(),
            'health' => $health->report(),
        ]);
    }

    /**
     * Order value per month for the last 6 months, always including every
     * month even when it had zero orders — the previous DB::raw() GROUP BY
     * skipped empty months (leaving gaps in the chart) and used MySQL's
     * DATE_FORMAT(), which doesn't exist under the SQLite driver tests run on.
     *
     * The `commission` series this used to carry alongside `total` is gone:
     * the platform's take is not plotted anywhere for now.
     *
     * @return array<int, array{month: string, total: float, count: int}>
     */
    private function salesTrend(): array
    {
        $since = now()->subMonths(5)->startOfMonth();

        $orders = Order::where('status', '!=', 'cancelled')
            ->where('created_at', '>=', $since)
            ->get(['total_amount', 'created_at']);

        return collect(range(5, 0))
            ->map(function (int $monthsAgo) use ($orders) {
                $month = now()->subMonths($monthsAgo);
                $matching = $orders->filter(
                    fn ($order) => $order->created_at->isSameMonth($month)
                );

                return [
                    'month' => self::MONTHS[(int) $month->format('n')],
                    'total' => (float) $matching->sum('total_amount'),
                    'count' => $matching->count(),
                ];
            })
            ->values()
            ->all();
    }
}
