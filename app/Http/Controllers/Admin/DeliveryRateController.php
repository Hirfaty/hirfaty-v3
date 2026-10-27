<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\DeliveryRate;
use App\Models\DeliveryService;
use App\Models\DeliveryWeightBand;
use App\Services\DeliveryRateService;
use App\Services\SpreadsheetExportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * The delivery rate cards, and the desk's edits to them.
 *
 * The cards are loaded by the delivery rate seeders (which only ever add
 * missing destinations, so they never overwrite a fee set here). From this
 * page the desk re-prices any line and adds or removes a Moroccan city or a
 * foreign country — and since checkout offers exactly the destinations on the
 * cards, that is also how a destination is opened or closed to buyers. On a
 * weight-priced card (DHL) a country carries a zone instead of a fee, and the
 * desk edits the zone's weight grid and the fees around it. See
 * DeliveryRateService.
 */
class DeliveryRateController extends Controller
{
    public function index(Request $request, DeliveryRateService $rates)
    {
        $services = DeliveryService::query()
            ->orderBy('sort_order')
            ->get();

        // Defaults to the first service in display order; `?service=slug`
        // narrows the page to one card.
        $selected = $services->firstWhere('slug', $request->query('service'))
            ?? $services->first();

        $weighed = $selected?->isWeightPriced() ?? false;

        return Inertia::render('admin/delivery-rates/index', [
            'services' => $services->map(fn (DeliveryService $service) => [
                'id' => $service->id,
                'slug' => $service->slug,
                'name' => $service->name,
                'pricing' => $service->pricing,
            ])->values()->all(),
            'selectedService' => $selected?->id,
            'rates' => $rates->card($selected?->id),
            'availableCities' => $weighed ? [] : $rates->unratedCities($selected?->id),
            // Only for a weight-priced card: its grid and the fees around it.
            'pricing' => $weighed ? $rates->pricingSettings($selected) : null,
            'grid' => $weighed ? $rates->weightGrid($selected) : null,
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

    /**
     * Adds a Moroccan city or a foreign country to a service's card — a
     * country only, with a zone instead of a fee, on a weight-priced card.
     */
    public function store(Request $request, DeliveryRateService $rates)
    {
        $serviceId = $request->input('delivery_service_id');
        $service = DeliveryService::find($serviceId);
        $weighed = $service?->isWeightPriced() ?? false;

        $data = $request->validate([
            'delivery_service_id' => ['required', 'integer', 'exists:delivery_services,id'],
            // A weight-priced carrier (DHL) ships abroad only.
            'type' => ['required', Rule::in($weighed ? ['country'] : ['city', 'country'])],
            'fee' => $weighed ? ['exclude'] : ['required', 'numeric', 'min:0', 'max:100000'],
            'zone' => $weighed
                ? ['required', 'integer', Rule::in($rates->zones($service))]
                : ['exclude'],

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
            // Optional: left blank, CityService fills in the names it knows.
            'city_name_ar' => ['exclude_unless:type,city', 'nullable', 'string', 'max:255'],
            'city_name_en' => ['exclude_unless:type,city', 'nullable', 'string', 'max:255'],

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
            'type.in' => 'Ce transporteur ne livre qu’à l’international.',
            'city_id.unique' => 'Cette ville a déjà un tarif.',
            'city_name.unique' => 'Une ville portant ce nom existe déjà.',
            'country_code.not_in' => 'Le Maroc est tarifé ville par ville.',
            'country_code.unique' => 'Ce pays a déjà un tarif.',
            'zone.in' => 'Cette zone n’a pas de grille de prix.',
        ]);

        if ($data['type'] === 'city') {
            $rates->addCity(
                $service,
                $data['city_id'] ?? null,
                $data['city_name'] ?? null,
                $data['region'] ?? null,
                (float) $data['fee'],
                [
                    'ar' => $data['city_name_ar'] ?? null,
                    'en' => $data['city_name_en'] ?? null,
                ],
            );

            return back()->with('success', 'Ville ajoutée à la grille.');
        }

        $rates->addCountry(
            $service,
            $data['country_code'],
            $data['country_name'],
            isset($data['fee']) ? (float) $data['fee'] : null,
            isset($data['zone']) ? (int) $data['zone'] : null,
        );

        return back()->with('success', 'Pays ajouté à la grille.');
    }

    /** One rate line's fee — or its zone, on a weight-priced card. */
    public function update(Request $request, DeliveryRate $rate, DeliveryRateService $rates)
    {
        if ($rate->service?->isWeightPriced()) {
            $validated = $request->validate([
                'zone' => ['required', 'integer', Rule::in($rates->zones($rate->service))],
            ], [
                'zone.in' => 'Cette zone n’a pas de grille de prix.',
            ]);

            $rates->updateZone($rate, (int) $validated['zone']);

            return back()->with('success', 'Zone mise à jour.');
        }

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

    /** One price on a weight-priced service's grid. */
    public function updateBand(Request $request, DeliveryWeightBand $band, DeliveryRateService $rates)
    {
        $validated = $request->validate([
            'price' => ['required', 'numeric', 'min:0', 'max:1000000'],
        ]);

        $rates->updateBand($band, (float) $validated['price']);

        return back()->with('success', 'Tarif mis à jour.');
    }

    /** The fees around a weight-priced service's grid — fuel surcharge first. */
    public function updatePricing(Request $request, DeliveryService $service, DeliveryRateService $rates)
    {
        abort_unless($service->isWeightPriced(), 404);

        $validated = $request->validate([
            'shipment_fee' => ['required', 'numeric', 'min:0', 'max:100000'],
            'fuel_surcharge_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'default_piece_weight' => ['required', 'numeric', 'gt:0', 'max:1000'],
            'handling_fee' => ['required', 'numeric', 'min:0', 'max:100000'],
            'overweight_fee' => ['required', 'numeric', 'min:0', 'max:100000'],
        ]);

        $rates->updatePricing($service, array_map('floatval', $validated));

        return back()->with('success', 'Paramètres de tarification enregistrés.');
    }
}
