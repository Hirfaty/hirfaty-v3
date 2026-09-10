<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\City;
use App\Models\Store;
use App\Services\StoreSetupService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class StoreSetupController extends Controller
{
    public function __construct(private StoreSetupService $stores) {}

    public function show()
    {
        if (auth()->user()->store) {
            return redirect()->route('seller.dashboard');
        }

        return Inertia::render('seller/setup_store', [
            /*
             * `translated_name`, never the raw model. `categories.name` is a
             * per-locale map ({fr, ar, en}), so shipping the model as-is put
             * an object where the <option> expected a string — React threw
             * "Objects are not valid as a React child" and took the whole
             * page down with it.
             */
            'categories' => Category::whereNull('parent_id')
                ->orderBy('id')
                ->get()
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'name' => $category->translated_name,
                ]),
            'cities' => City::orderBy('name')
                ->get()
                ->map(fn (City $city) => [
                    'id' => $city->id,
                    'name' => $city->name,
                ]),
            'types' => Store::TYPES,
        ]);
    }

    public function store(Request $request)
    {
        /*
         * Checked before validation, not after: `store_name` is `unique:stores`,
         * so a seller who already has a shop and resubmits would otherwise be
         * handed a confusing "name already taken" against their own shop
         * rather than being told they are done.
         */
        if ($request->user()->store) {
            return redirect()->route('seller.dashboard');
        }

        $data = $request->validate([
            'store_name' => ['required', 'string', 'max:255', 'unique:stores'],
            // Was never collected here, so every self-serve shop silently took
            // the column default ('artist') — including companies and
            // cooperatives, which the storefront badges and filters on.
            'type' => ['required', Rule::in(Store::TYPES)],
            /*
             * Optional so a seller can open the shop from step one and finish
             * later — both columns are nullable, and Seller\SettingsController
             * requires them on the way back in. Guessing a craft and a city
             * for someone who skipped would put wrong data in the filters,
             * which is worse than an obviously-unfinished shop.
             */
            'category_id' => ['nullable', 'exists:categories,id'],
            'city_id' => ['nullable', 'exists:cities,id'],
            'description' => ['nullable', 'string', 'max:2500'],
            'terms_accepted' => ['required', 'accepted'],
        ]);

        $store = $this->stores->create($request->user(), $data);

        // Straight to the confirmation screen rather than the product list: the
        // seller has just been told nothing publishes without review, and
        // dropping them on an empty catalogue says none of that.
        return redirect()->route('seller.store.created', [
            'name' => $store->store_name,
        ]);
    }

    /**
     * "Your workshop is created, here's what happens next."
     *
     * Reached only via the redirect from store(), but written to survive a
     * refresh or a bookmarked visit — hence reading the shop off the user
     * rather than trusting the querystring for anything but the greeting.
     */
    public function created(Request $request)
    {
        $store = $request->user()->store;

        return Inertia::render('seller/store_created', [
            'storeName' => $store->store_name,
            // Nothing is publicly readable until an admin approves it, so the
            // page can promise review honestly — see Admin\ProductController.
            'hasProducts' => $store->products()->exists(),
            // Set when the seller opened the shop from step one. The page turns
            // its "no rush" note into an actual link to settings, so a skipped
            // craft/city does not just quietly stay missing.
            'isIncomplete' => $store->category_id === null
                || $store->city_id === null
                || blank($store->description),
        ]);
    }
}
