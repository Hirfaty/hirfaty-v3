<?php

namespace App\Services;

use App\Models\Address;
use App\Models\City;
use App\Models\DeliveryRate;
use App\Models\DeliveryService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What the admin desk does to a delivery service's rate card: read it,
 * re-price a line, and add or remove a destination.
 *
 * Which destinations exist *is* the rate card — checkout offers a country only
 * if the card has a row for it, and a Moroccan city only if the card has a row
 * for that city (see DeliveryFeeService) — so adding or removing a row here is
 * what opens or closes a destination to buyers.
 *
 * Removing a Moroccan city's row removes it from the card only. The `cities`
 * registry stays as it is: stores, orders and saved addresses point at those
 * rows, and a city that Amana does not serve any more is still a real place.
 */
class DeliveryRateService
{
    /**
     * A service's card, sorted country then city, shaped for the admin page.
     *
     * Sorted here rather than in SQL: city names live on the joined row, and
     * ordering by them in the query would need a join that drops the
     * country-level rows (which have no city).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function card(?int $serviceId): Collection
    {
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
                'fee' => (float) $rate->fee,
            ])
            ->sortBy([
                fn ($a, $b) => strcmp($a['country_name'], $b['country_name']),
                fn ($a, $b) => strcmp($a['city_name'] ?? '', $b['city_name'] ?? ''),
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

    public function updateFee(DeliveryRate $rate, float $fee): void
    {
        $rate->update(['fee' => $fee]);
    }

    /**
     * Prices delivery to a Moroccan city. Pass an existing `$cityId`, or a
     * `$newName` to add the city to the registry at the same time.
     */
    public function addCity(DeliveryService $service, ?int $cityId, ?string $newName, ?string $region, float $fee): DeliveryRate
    {
        return DB::transaction(function () use ($service, $cityId, $newName, $region, $fee) {
            $city = $cityId !== null
                ? City::findOrFail($cityId)
                : City::create(['name' => $newName, 'region' => $region]);

            return DeliveryRate::create([
                'delivery_service_id' => $service->id,
                'country_code' => Address::DEFAULT_COUNTRY,
                'country_name' => 'Maroc',
                'city_id' => $city->id,
                'fee' => $fee,
            ]);
        });
    }

    /** Prices delivery to a whole foreign country — one fee, no cities. */
    public function addCountry(DeliveryService $service, string $code, string $name, float $fee): DeliveryRate
    {
        return DeliveryRate::create([
            'delivery_service_id' => $service->id,
            'country_code' => strtoupper($code),
            'country_name' => $name,
            'city_id' => null,
            'fee' => $fee,
        ]);
    }

    public function remove(DeliveryRate $rate): void
    {
        $rate->delete();
    }
}
