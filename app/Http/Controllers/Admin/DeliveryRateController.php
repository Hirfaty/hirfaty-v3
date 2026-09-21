<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryRate;
use App\Models\DeliveryService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The delivery rate cards, as a reference.
 *
 * Read-only on purpose — there is no store/update/destroy here and no route
 * for one. These figures belong to the carriers (Amana's card today, DHL's
 * tomorrow): they arrive as a rate card and are loaded by
 * `DeliveryRateSeeder`, so letting the admin desk edit them in the app would
 * only create a second, quietly diverging copy of someone else's pricing.
 * What the desk needs is to *look one up* — optionally narrowing to a single
 * carrier once there is more than one.
 */
class DeliveryRateController extends Controller
{
    public function index(Request $request)
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
        ]);
    }
}
