<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\StoreVerificationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StoreController extends Controller
{
    public function __construct(private StoreVerificationService $verification) {}

    public function index(Request $request)
    {
        $query = Store::query()->with(['user', 'category', 'city'])->withCount('products');

        if ($search = $request->string('search')->trim()->value()) {
            $query->where('store_name', 'like', "%{$search}%");
        }

        if ($status = $request->input('status')) {
            $query->where('verification_status', $status);
        }

        // Oldest submission first: a review queue worked newest-first leaves
        // the earliest applicant waiting longest.
        $stores = $query
            ->orderByRaw('CASE WHEN verification_status = ? THEN 0 ELSE 1 END', [Store::VERIFICATION_PENDING])
            ->orderByRaw('COALESCE(submitted_at, created_at) ASC')
            ->paginate(20)
            ->withQueryString();

        // Counts ignore the active status filter — otherwise every tab but the
        // selected one reads 0 — while still respecting the search.
        $counts = Store::query()
            ->when($search, fn ($q) => $q->where('store_name', 'like', "%{$search}%"))
            ->selectRaw('verification_status, count(*) as count')
            ->groupBy('verification_status')
            ->pluck('count', 'verification_status');

        return Inertia::render('admin/stores/index', [
            'stores' => $stores->through(fn (Store $store) => [
                'id' => $store->id,
                'store_name' => $store->store_name,
                'slug' => $store->slug,
                'type' => $store->type,
                'logo' => $store->logo,
                'description' => $store->description,
                'verification_status' => $store->verification_status,
                'rejection_reason' => $store->rejection_reason,
                'submitted_at' => $store->submitted_at?->toIso8601String(),
                'approved_at' => $store->approved_at?->toIso8601String(),
                'created_at' => $store->created_at?->toIso8601String(),
                'products_count' => $store->products_count,
                // The exact fields the seller still owes us, so an admin can
                // see why a shop is not reviewable without opening it.
                'missing_fields' => $store->missingProfileFields(),
                'category' => $store->category?->translated_name,
                'city' => $store->city?->name,
                'owner' => $store->user === null ? null : [
                    'id' => $store->user->id,
                    'name' => trim("{$store->user->first_name} {$store->user->last_name}"),
                    'email' => $store->user->email,
                ],
            ]),
            'filters' => [
                'search' => $search ?? '',
                'status' => $status ?? '',
            ],
            'statusCounts' => $counts,
            'statuses' => Store::VERIFICATION_STATUSES,
        ]);
    }

    public function approve(Request $request, Store $store)
    {
        /*
         * Refuse to approve a shop that is still missing required fields.
         * The button is hidden for these in the UI, but the endpoint is the
         * boundary — approving an empty shop would put a blank storefront in
         * front of buyers.
         */
        if (! $store->hasCompleteProfile()) {
            return back()->with('error', "« {$store->store_name} » n'a pas encore rempli toutes ses informations.");
        }

        $this->verification->approve($store, $request->user());

        return back()->with('success', "Atelier « {$store->store_name} » validé.");
    }

    public function reject(Request $request, Store $store)
    {
        $data = $request->validate([
            // Required and substantive: the reason is mailed to the seller as
            // their only instruction on what to fix.
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $this->verification->reject($store, $request->user(), $data['reason']);

        return back()->with('success', "Atelier « {$store->store_name} » refusé. Le vendeur a été prévenu.");
    }
}
