<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BrochureLead;
use App\Services\BrochureLeadService;
use App\Services\SpreadsheetExportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
    public function __construct(private readonly BrochureLeadService $brochure) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'type']);

        $leads = $this->brochure->adminQuery($filters)->latest()->paginate(20)->withQueryString();

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
            'filters' => $filters,
        ]);
    }

    /**
     * Download the leads the admin is looking at — every page — as CSV or
     * Excel.
     */
    public function export(Request $request)
    {
        $data = $request->validate([
            'format' => ['required', Rule::in(SpreadsheetExportService::FORMATS)],
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(BrochureLead::TYPES)],
        ]);

        return $this->brochure->export($data['format'], $data);
    }
}
