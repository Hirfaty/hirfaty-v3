<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\DeliveryRate;
use App\Models\DeliveryService;
use App\Services\DeliveryRateService;
use App\Services\SpreadsheetExportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * The delivery rate cards, and the desk's edits to them.
 *
 * The cards are loaded by `DeliveryRateSeeder` (which only ever adds missing
 * destinations, so it never overwrites a fee set here). From this page the desk
 * re-prices any line and adds or removes a Moroccan city or a foreign country —
 * and since checkout offers exactly the destinations on the card, that is also
 * how a destination is opened or closed to buyers. See DeliveryRateService.
 */
class DeliveryRateController extends Controller
{
    public function index(Request $request, DeliveryRateService $rates)
    {
        $services = DeliveryService::query()
            ->orderBy('sort_order')
            ->get(['id', 'slug', 'name']);

        // Defaults to the first service in display order; `?service=slug`
        // narrows the page to one card.
        $selected = $services->firstWhere('slug', $request->query('service'))?->id
            ?? $services->first()?->id;

        return Inertia::render('admin/delivery-rates/index', [
            // One entry today; a service selector appears on the page only
            // once a second carrier makes the page need one.
            'services' => $services->map(fn (DeliveryService $service) => [
                'id' => $service->id,
                'slug' => $service->slug,
                'name' => $service->name,
            ])->values()->all(),
            'selectedService' => $selected,
            'rates' => $rates->card($selected),
            'availableCities' => $rates->unratedCities($selected),
        ]);
    }

    /**
     * Download a service's rate card as CSV or Excel — `?service=slug` picks
     * the card the same way the page does, defaulting to the first one.
     */
    public function export(Request $request, DeliveryRateService $rates)
    {
        $data = $request->validate([
            'format' => ['required', Rule::in(SpreadsheetExportService::FORMATS)],
            'service' => ['nullable', 'string'],
        ]);

        $serviceId = DeliveryService::where('slug', $data['service'] ?? null)->value('id')
            ?? DeliveryService::orderBy('sort_order')->value('id');

        return $rates->export($data['format'], $serviceId);
    }

    /** Adds a Moroccan city or a foreign country to a service's card. */
    public function store(Request $request, DeliveryRateService $rates)
    {
        $serviceId = $request->input('delivery_service_id');

        $data = $request->validate([
            'delivery_service_id' => ['required', 'integer', 'exists:delivery_services,id'],
            'type' => ['required', Rule::in(['city', 'country'])],
            'fee' => ['required', 'numeric', 'min:0', 'max:100000'],

            // ── A Moroccan city: an existing one, or a brand-new one ──
            'city_id' => [
                'exclude_unless:type,city',
                'required_without:city_name',
                'nullable',
                'integer',
                'exists:cities,id',
                Rule::unique('delivery_rates', 'city_id')->where('delivery_service_id', $serviceId),
            ],
            'city_name' => [
                'exclude_unless:type,city',
                'required_without:city_id',
                'nullable',
                'string',
                'max:255',
                'unique:cities,name',
            ],
            'region' => ['exclude_unless:type,city', 'nullable', 'string', 'max:255'],

            // ── A foreign country: Morocco is priced per city instead ──
            'country_code' => [
                'exclude_unless:type,country',
                'required',
                'alpha',
                'size:2',
                Rule::notIn([Address::DEFAULT_COUNTRY, strtolower(Address::DEFAULT_COUNTRY)]),
                Rule::unique('delivery_rates', 'country_code')
                    ->where('delivery_service_id', $serviceId)
                    ->whereNull('city_id'),
            ],
            'country_name' => ['exclude_unless:type,country', 'required', 'string', 'max:100'],
        ], [
            'city_id.unique' => 'Cette ville a déjà un tarif.',
            'city_name.unique' => 'Une ville portant ce nom existe déjà.',
            'country_code.not_in' => 'Le Maroc est tarifé ville par ville.',
            'country_code.unique' => 'Ce pays a déjà un tarif.',
        ]);

        $service = DeliveryService::findOrFail($data['delivery_service_id']);

        if ($data['type'] === 'city') {
            $rates->addCity(
                $service,
                $data['city_id'] ?? null,
                $data['city_name'] ?? null,
                $data['region'] ?? null,
                (float) $data['fee'],
            );

            return back()->with('success', 'Ville ajoutée à la grille.');
        }

        $rates->addCountry($service, $data['country_code'], $data['country_name'], (float) $data['fee']);

        return back()->with('success', 'Pays ajouté à la grille.');
    }

    /** One rate line's fee. */
    public function update(Request $request, DeliveryRate $rate, DeliveryRateService $rates)
    {
        $validated = $request->validate([
            'fee' => ['required', 'numeric', 'min:0', 'max:100000'],
        ]);

        $rates->updateFee($rate, (float) $validated['fee']);

        return back()->with('success', 'Tarif mis à jour.');
    }

    /** Stops delivering to one destination. */
    public function destroy(DeliveryRate $rate, DeliveryRateService $rates)
    {
        $rates->remove($rate);

        return back()->with('success', 'Destination retirée de la grille.');
    }
}
