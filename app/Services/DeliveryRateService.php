<?php

namespace App\Services;

use App\Models\Address;
use App\Models\City;
use App\Models\DeliveryRate;
use App\Models\DeliveryService;
use App\Models\DeliveryWeightBand;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * What the admin desk does to a delivery service's rate card: read it,
 * re-price a line, and add or remove a destination — and, for a
 * weight-priced service (DHL), move a country to another zone, re-price a
 * line of the weight grid, and set the fees around it (fuel surcharge…).
 *
 * Which destinations exist *is* the rate card — checkout offers a country only
 * if a card has a row for it, and a Moroccan city only if a card has a row for
 * that city (see DeliveryFeeService) — so adding or removing a row here is
 * what opens or closes a destination to buyers.
 *
 * Removing a Moroccan city's row removes it from the card only. The `cities`
 * registry stays as it is: stores, orders and saved addresses point at those
 * rows, and a city no carrier serves any more is still a real place.
 */
class DeliveryRateService
{
    public function __construct(
        private readonly SpreadsheetExportService $spreadsheets,
        private readonly CityService $cities,
    ) {}

    /**
     * A service's card as CSV or Excel — the same rows, in the same order, as
     * the admin page shows.
     *
     * @param  'csv'|'xlsx'  $format
     */
    public function export(string $format, ?int $serviceId): StreamedResponse|BinaryFileResponse
    {
        return $this->spreadsheets->download(
            $format,
            'tarifs-livraison',
            ['Transporteur', 'Code pays', 'Pays', 'Ville', 'Région', 'Zone', 'Tarif (MAD)'],
            fn () => $this->card($serviceId)->map(fn (array $rate) => [
                $rate['service_name'],
                $rate['country_code'],
                $rate['country_name'],
                // Blank on a country-wide row: the fee covers the whole country.
                $rate['city_name'] ?? '',
                $rate['region'] ?? '',
                $rate['zone'] ?? '',
                // Blank on a zone row: the price depends on the parcel's weight.
                $rate['fee'] ?? '',
            ]),
        );
    }

    /**
     * A service's card, sorted country then city, shaped for the admin page.
     *
     * Sorted here rather than in SQL: city names live on the joined row, and
     * ordering by them in the query would need a join that drops the
     * country-level rows (which have no city). Accents are folded so
     * "États-Unis" sorts under E rather than after Z.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function card(?int $serviceId): Collection
    {
        $key = fn (?string $name) => Str::lower(Str::ascii($name ?? ''));

        return DeliveryRate::query()
            ->with(['service:id,name,slug', 'city:id,name,region'])
            ->when($serviceId, fn ($query) => $query->where('delivery_service_id', $serviceId))
            ->get()
            ->map(fn (DeliveryRate $rate) => [
                'id' => $rate->id,
                'service_name' => $rate->service?->name ?? '—',
                'country_code' => $rate->country_code,
                'country_name' => $rate->country_name,
                'city_name' => $rate->city?->name,
                'region' => $rate->city?->region,
                'zone' => $rate->zone,
                'fee' => $rate->fee !== null ? (float) $rate->fee : null,
            ])
            ->sortBy([
                fn ($a, $b) => strcmp($key($a['country_name']), $key($b['country_name'])),
                fn ($a, $b) => strcmp($key($a['city_name']), $key($b['city_name'])),
            ])
            ->values();
    }

    /**
     * Cities in the registry that this service has no rate for yet — what the
     * "add a city" form offers before it falls back to creating a new one.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    public function unratedCities(?int $serviceId): Collection
    {
        return City::query()
            ->whereNotIn('id', DeliveryRate::query()
                ->where('delivery_service_id', $serviceId)
                ->whereNotNull('city_id')
                ->select('city_id'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (City $city) => ['id' => $city->id, 'name' => $city->name]);
    }

    /**
     * The fees around a weight-priced service's grid, as the page edits them.
     *
     * @return array{shipment_fee: float, fuel_surcharge_percent: float, default_piece_weight: float|null, handling_fee: float, overweight_fee: float}
     */
    public function pricingSettings(DeliveryService $service): array
    {
        return [
            'shipment_fee' => (float) $service->shipment_fee,
            'fuel_surcharge_percent' => (float) $service->fuel_surcharge_percent,
            'default_piece_weight' => $service->default_piece_weight !== null ? (float) $service->default_piece_weight : null,
            'handling_fee' => (float) $service->handling_fee,
            'overweight_fee' => (float) $service->overweight_fee,
        ];
    }

    /**
     * A weight-priced service's grid laid out the way the carrier's card
     * reads: a row per weight, a cell per zone. Flat bands first, then the
     * per-step stretches, each with the weight it starts from.
     *
     * @return array{zones: array<int, int>, brackets: array<int, array<string, mixed>>, steps: array<int, array<string, mixed>>}
     */
    public function weightGrid(DeliveryService $service): array
    {
        $bands = DeliveryWeightBand::query()
            ->where('delivery_service_id', $service->id)
            ->get();

        $rows = fn (Collection $bands) => $bands
            ->groupBy(fn (DeliveryWeightBand $band) => (string) (float) $band->up_to)
            ->map(fn (Collection $row) => [
                'up_to' => (float) $row->first()->up_to,
                'step' => $row->first()->step !== null ? (float) $row->first()->step : null,
                'cells' => $row->sortBy('zone')->map(fn (DeliveryWeightBand $band) => [
                    'id' => $band->id,
                    'zone' => $band->zone,
                    'price' => (float) $band->price,
                ])->values()->all(),
            ])
            ->sortBy('up_to')
            ->values();

        $brackets = $rows($bands->whereNull('step'));
        $from = $brackets->last()['up_to'] ?? 0.0;

        $steps = $rows($bands->whereNotNull('step'))->map(function (array $row) use (&$from) {
            $row['from'] = $from;
            $from = $row['up_to'];

            return $row;
        });

        return [
            'zones' => $this->zones($service),
            'brackets' => $brackets->all(),
            'steps' => $steps->all(),
        ];
    }

    /**
     * The zones a weight-priced service has a grid for — the only ones a
     * country can be put in.
     *
     * @return array<int, int>
     */
    public function zones(DeliveryService $service): array
    {
        return DeliveryWeightBand::query()
            ->where('delivery_service_id', $service->id)
            ->distinct()
            ->orderBy('zone')
            ->pluck('zone')
            ->map(fn ($zone) => (int) $zone)
            ->all();
    }

    public function updateFee(DeliveryRate $rate, float $fee): void
    {
        $rate->update(['fee' => $fee]);
    }

    /** Moves a country to another zone of its weight-priced service. */
    public function updateZone(DeliveryRate $rate, int $zone): void
    {
        $rate->update(['zone' => $zone]);
    }

    /** One price on a weight-priced service's grid. */
    public function updateBand(DeliveryWeightBand $band, float $price): void
    {
        $band->update(['price' => $price]);
    }

    /**
     * The fees around a weight-priced service's grid.
     *
     * @param  array{shipment_fee: float, fuel_surcharge_percent: float, default_piece_weight: float, handling_fee: float, overweight_fee: float}  $settings
     */
    public function updatePricing(DeliveryService $service, array $settings): void
    {
        $service->update($settings);
    }

    /**
     * Prices delivery to a Moroccan city. Pass an existing `$cityId`, or a
     * `$newName` (French) to add the city to the registry at the same time —
     * with `$translations` for its Arabic and English names, keyed by locale.
     *
     * @param  array<string, string|null>  $translations
     */
    public function addCity(DeliveryService $service, ?int $cityId, ?string $newName, ?string $region, float $fee, array $translations = []): DeliveryRate
    {
        return DB::transaction(function () use ($service, $cityId, $newName, $region, $fee, $translations) {
            $city = $cityId !== null
                ? City::findOrFail($cityId)
                : City::create([
                    'name' => $newName,
                    'name_translations' => $this->cities->translationsFor($newName, $translations),
                    'region' => $region,
                ]);

            return DeliveryRate::create([
                'delivery_service_id' => $service->id,
                'country_code' => Address::DEFAULT_COUNTRY,
                'country_name' => 'Maroc',
                'city_id' => $city->id,
                'fee' => $fee,
            ]);
        });
    }

    /**
     * Opens a whole foreign country: one fee, no cities — or, on a
     * weight-priced service, a zone and no fee of its own.
     */
    public function addCountry(DeliveryService $service, string $code, string $name, ?float $fee, ?int $zone = null): DeliveryRate
    {
        return DeliveryRate::create([
            'delivery_service_id' => $service->id,
            'country_code' => strtoupper($code),
            'country_name' => $name,
            'city_id' => null,
            'zone' => $service->isWeightPriced() ? $zone : null,
            'fee' => $service->isWeightPriced() ? null : $fee,
        ]);
    }

    public function remove(DeliveryRate $rate): void
    {
        $rate->delete();
    }
}
