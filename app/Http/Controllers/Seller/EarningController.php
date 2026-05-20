<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EarningController extends Controller
{
    public function index(Request $request)
    {
        $store = $request->user()->store;

        $earnings = $store->earnings()->with(['order.buyer', 'order.items.product'])->latest()->paginate(20);

        $summary = [
            'total_earnings' => (float) $store->earnings()->where('status', 'paid_out')->sum('net_amount'),
            'available_earnings' => (float) $store->earnings()->where('status', 'available')->sum('net_amount'),
            'pending_earnings' => (float) $store->earnings()->where('status', 'pending')->sum('net_amount'),
        ];

        return Inertia::render('seller/earnings/index', [
            'earnings' => $earnings,
            'summary' => $summary,
        ]);

    }
}
