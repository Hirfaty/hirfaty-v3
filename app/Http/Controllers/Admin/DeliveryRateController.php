<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryRate;
use App\Models\DeliveryService;
use App\Services\DeliveryFeeService;
use App\Services\DeliverySettingsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The delivery rate cards, and the switches over them.
 *
 * The cards are loaded by `DeliveryRateSeeder` (which only ever adds missing
 * destinations, so it never overwrites a fee set here), and the desk can
 * correct any single line of them. On top sit two settings: whether checkout
 * offers cities and countries at all, and the flat fee every basket pays when
 * it does not — see DeliverySettingsService.
 */
class DeliveryRateController extends Controller
{
    public function index(Request $request, DeliverySettingsService $settings)
    {
        $services = DeliveryService::query()
            ->orderBy('sort_order')
            ->get(['id', 'slug', 'name']);

        // Defaults to the first service in display order; `?service=slug`
        // narrows the page to one card.
        $selected = $services->firstWhere('slug', $request->query('service'))?->id
            ?? $services->first()?->id;

        $rates = DeliveryRate::query()
            ->with(['service:id,name,slug', 'city:id,name,region'])
            ->when($selected, fn ($query) => $query->where('delivery_service_id', $selected))
            ->get()
            ->map(fn (DeliveryRate $rate) => [
                'id' => $rate->id,
                'service_name' => $rate->service?->name ?? '—',
                'country_code' => $rate->country_code,
                'country_name' => $rate->country_name,
                'city_name' => $rate->city?->name,
                'region' => $rate->city?->region,
                'fee' => (float) $rate->fee,
            ])
            // Sorted here rather than in SQL: city names live on the joined
            // row, and ordering by them in the query would need a join that
            // drops the country-level rows (which have no city).
            ->sortBy([
                fn ($a, $b) => strcmp($a['country_name'], $b['country_name']),
                fn ($a, $b) => strcmp($a['city_name'] ?? '', $b['city_name'] ?? ''),
            ])
            ->values();

        return Inertia::render('admin/delivery-rates/index', [
            // One entry today; a service selector appears on the page only
            // once a second carrier makes the page need one.
            'services' => $services->map(fn (DeliveryService $service) => [
                'id' => $service->id,
                'slug' => $service->slug,
                'name' => $service->name,
            ])->values()->all(),
            'selectedService' => $selected,
            'rates' => $rates,
            'settings' => [
                'show_destinations' => $settings->showsDestinations(),
                'flat_fee' => $settings->flatFee(),
            ],
        ]);
    }

    /** One rate line's fee. */
    public function update(Request $request, DeliveryRate $rate, DeliveryFeeService $deliveryFees)
    {
        $validated = $request->validate([
            'fee' => ['required', 'numeric', 'min:0', 'max:100000'],
        ]);

        $deliveryFees->updateRateFee($rate, (float) $validated['fee']);

        return back()->with('success', 'Tarif mis à jour.');
    }

    /** The flat fee and the show-cities-and-countries toggle. */
    public function updateSettings(Request $request, DeliverySettingsService $settings)
    {
        $validated = $request->validate([
            'flat_fee' => ['required', 'numeric', 'min:0', 'max:100000'],
            'show_destinations' => ['required', 'boolean'],
        ]);

        $settings->update((float) $validated['flat_fee'], (bool) $validated['show_destinations']);

        return back()->with('success', 'Paramètres de livraison enregistrés.');
    }
}
