<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\OnboardingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The short intro a buyer sees once, straight after verifying their email
 * (VerifyEmailResponse routes them here). Every step is skippable — the whole
 * thing exists to orient someone, not to gate the storefront behind a form.
 */
class OnboardingController extends Controller
{
    public function __construct(private OnboardingService $onboarding) {}

    public function show(Request $request)
    {
        $user = $request->user();

        /*
         * Someone who has already been through this (or who deep-links back
         * to the URL later) goes straight to the market. The tour is a
         * one-time thing, and re-running it would silently re-sync their
         * interests from an empty form.
         */
        if (! $user->needsOnboarding()) {
            return redirect()->route('products.index');
        }

        return Inertia::render('buyer/onboarding', [
            // Top-level crafts only: the tour offers a handful of broad
            // choices, not the full taxonomy.
            'categories' => Category::whereNull('parent_id')
                ->orderBy('id')
                ->get()
                ->map(fn (Category $c) => [
                    'id' => $c->id,
                    'name' => $c->translated_name,
                    'icon' => $c->icon,
                ]),
            'firstName' => $user->first_name,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'interests' => ['nullable', 'array'],
            'interests.*' => ['integer', 'exists:categories,id'],
        ]);

        $this->onboarding->complete($request->user(), $data['interests'] ?? []);

        return redirect()->route('products.index')
            ->with('success', 'Bienvenue sur Hirfaty !');
    }

    public function skip(Request $request)
    {
        $this->onboarding->skip($request->user());

        return redirect()->route('products.index');
    }
}
