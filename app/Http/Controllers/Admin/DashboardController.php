<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardService;
use App\Services\HealthReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request, AdminDashboardService $dashboard, HealthReportService $health)
    {
        // Closures, so changing the registration chart's filters (a partial
        // reload of `registrations`) does not recompute the rest of the page.
        return Inertia::render('admin/dashboard', [
            'stats' => fn () => $dashboard->stats(),
            'latestUsers' => fn () => $dashboard->latestUsers(),
            'pendingProducts' => fn () => $dashboard->pendingProducts(),
            'registrations' => fn () => $dashboard->registrations(
                $request->query('period'),
                $request->query('role'),
            ),
            'health' => fn () => $health->report(),
        ]);
    }
}
