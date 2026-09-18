<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AmanaDeliveryRate;
use Inertia\Inertia;

/**
 * Amana's rate card, as a reference table.
 *
 * Read-only on purpose — there is no store/update/destroy here and no route
 * for one. These figures are Amana's, not Hirfaty's: they arrive as a rate
 * card and are loaded by `AmanaDeliveryRateSeeder`, so letting the admin desk
 * edit them in the app would only create a second, quietly diverging copy of
 * someone else's pricing. What the desk needs is to *look one up*.
 */
class DeliveryRateController extends Controller
{
    public function index()
    {
        $rates = AmanaDeliveryRate::query()
            ->with('city:id,name,region')
            ->orderBy('country_name')
            // Country-level row first within a country, then cities by name.
            ->orderByRaw('CASE WHEN city_id IS NULL THEN 0 ELSE 1 END')
            ->get()
            ->map(fn (AmanaDeliveryRate $rate) => [
                'id' => $rate->id,
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
            'rates' => $rates,
        ]);
    }
}
