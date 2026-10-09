<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\City;
use App\Models\Store;
use App\Services\LocaleService;
use App\Services\StoreImageService;
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
            'categories' => Category::roots()->get(),
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

    /**
     * The shop's photo — the framed picture on the public shop page. Its own
     * endpoint rather than a field on updateStore: it uploads the moment a
     * file is picked, and a new photo is not a reason to re-run the review.
     */
    public function updateLogo(Request $request, StoreImageService $images)
    {
        $request->validate([
            'logo' => ['required', ...$images->rules()],
        ]);

        $images->replace(auth()->user()->store, $request->file('logo'), 'logo');

        return back()->with('success', 'Photo de l’atelier mise à jour.');
    }

    public function updateLocale(Request $request, LocaleService $locales)
    {
        $request->validate([
            'locale' => ['required', 'in:fr,ar,en'],
        ]);

        auth()->user()->update(['locale' => $request->locale]);

        // An explicit choice, like the switcher: takes effect immediately and
        // outranks the browser's language from now on.
        $locales->choose($request, $request->locale);

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
