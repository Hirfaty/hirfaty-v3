<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // ── KPI Stats ───────────────────────────────────────────
        $stats = [
            'total_orders'     => Order::count(),
            'total_revenue'    => (float) Order::where('status', 'delivered')->sum('total_amount'),
            'pending_products' => Product::where('status', 'pending')->count(),
            'total_users'      => User::count(),
            'total_sellers'    => User::where('role', 'seller')->count(),
            'total_buyers'     => User::where('role', 'buyer')->count(),

            // Financial Deep Dive
            'total_platform_fees' => (float) Order::where('status', 'delivered')->sum('platform_fee'),
            'pending_payouts'     => (float) \App\Models\Payout::where('status', 'pending')->sum('amount'),
            'total_paid_out'      => (float) \App\Models\Payout::where('status', 'processed')->sum('amount'),
            'seller_balance_pending' => (float) \App\Models\Earning::where('status', 'pending')->sum('net_amount'),
            'seller_balance_available' => (float) \App\Models\Earning::where('status', 'available')->sum('net_amount'),
        ];

        // ── Recent Activity ────────────────────────────────────
        $latest_users = User::latest()->take(6)->get(['id', 'first_name', 'last_name', 'email', 'role', 'created_at']);

        $pending_approval = Product::with(['store', 'category'])
            ->where('status', 'pending')
            ->latest()
            ->take(5)
            ->get();

        // ── Sales Trend (Last 6 Months) ────────────────────────
        $sales_trend = Order::select(
            DB::raw('SUM(total_amount) as total'),
            DB::raw('SUM(platform_fee) as commission'),
            DB::raw('COUNT(id) as count'),
            DB::raw("DATE_FORMAT(created_at, '%b') as month"),
            DB::raw("DATE_FORMAT(created_at, '%Y-%m') as sort_key")
        )
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupBy('month', 'sort_key')
            ->orderBy('sort_key', 'asc')
            ->get();

        return Inertia::render('admin/dashboard', [
            'stats'            => $stats,
            'latestUsers'      => $latest_users,
            'pendingProducts'  => $pending_approval,
            'salesTrend'       => $sales_trend,
        ]);
    }
}
