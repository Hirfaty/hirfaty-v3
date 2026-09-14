<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\City;
use App\Models\Store;
use App\Services\StoreVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function __construct(private StoreVerificationService $verification) {}

    public function index()
    {
        $user = auth()->user()->load('store');

        return Inertia::render('seller/settings/index', [
            'user' => $user,
            'categories' => Category::whereNull('parent_id')->get(),
            'cities' => City::orderBy('name')->get(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', Rule::unique('users', 'phone_number')->ignore($user->id)],
            'bio' => ['nullable', 'string', 'max:500'],
        ]);

        $user->update([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'name' => $data['first_name'].' '.$data['last_name'],
            'phone_number' => $data['phone_number'] ?? null,
            'bio' => $data['bio'] ?? null,
        ]);

        return back()->with('success', 'Profile updated.');
    }

    // ── Store info ───────────────────────────────────────────
    public function updateStore(Request $request)
    {
        $store = auth()->user()->store;

        $data = $request->validate([
            'store_name' => ['required', 'string', 'max:255', Rule::unique('stores', 'store_name')->ignore($store->id)],
            'description' => ['nullable', 'string', 'max:2500'],
            'category_id' => ['required', 'exists:categories,id'],
            'city_id' => ['required', 'exists:cities,id'],
        ]
        );

        $store->update([
            'store_name' => $data['store_name'],
            'description' => $data['description'] ?? null,
            'category_id' => $data['category_id'],
            'city_id' => $data['city_id'],
        ]);

        /*
         * Saving a now-complete profile is what puts the shop in the review
         * queue — there is no separate "submit" button to forget to press.
         * No-ops on an already-approved shop, so editing a live shop's
         * description does not knock it back into review.
         */
        $before = $store->verification_status;
        $store = $this->verification->submit($store->refresh());

        if ($store->isPendingVerification() && $before !== Store::VERIFICATION_PENDING) {
            return back()->with('success', 'Atelier enregistré et envoyé en validation.');
        }

        return back()->with('success', 'Atelier enregistré.');
    }

    public function updateLocale(Request $request)
    {
        $request->validate([
            'locale' => ['required', 'in:fr,ar,en'],
        ]);

        auth()->user()->update(['locale' => $request->locale]);

        // Also update the session so it takes effect immediately
        session(['locale' => $request->locale]);

        return back()->with('success', 'Language updated.');
    }

    // ── Password ─────────────────────────────────────────────
    public function updatePassword(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return back()->with('success', 'Password updated.');
    }
}
