<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Services\ProductSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(private ProductSearchService $search) {}

    /**
     * Live results for the header's search panel.
     *
     * Plain JSON rather than an Inertia response: this fires on every few
     * keystrokes and only ever repaints a dropdown, so it must not push a
     * history entry or re-render the page underneath it.
     */
    public function suggestions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $term = trim((string) ($validated['q'] ?? ''));

        return response()->json([
            'term' => $term,
            'products' => $this->search->suggest($request->user(), $term),
            'total' => $this->search->countFor($request->user(), $term),
        ]);
    }
}
