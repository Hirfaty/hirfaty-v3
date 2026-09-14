<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BrochureLead;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only: who has asked for the brochure.
 *
 * There is nothing for an admin to edit here. The rows are what visitors typed
 * about themselves, and an edit screen would only offer a way to quietly
 * rewrite that.
 */
class BrochureLeadController extends Controller
{
    public function index(Request $request): Response
    {
        $query = BrochureLead::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        $leads = $query->latest()->paginate(20)->withQueryString();

        // Counts ignore the active type filter — otherwise every tab but the
        // selected one reads 0 — while still respecting the search.
        $counts = [
            'all' => BrochureLead::count(),
            'buyer' => BrochureLead::where('type', 'buyer')->count(),
            'seller' => BrochureLead::where('type', 'seller')->count(),
        ];

        return Inertia::render('admin/brochure-leads/index', [
            'leads' => $leads,
            'counts' => $counts,
            'filters' => $request->only(['search', 'type']),
        ]);
    }
}
