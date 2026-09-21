<?php

namespace App\Services;

use App\Models\Address;
use App\Models\DeliveryRate;
use App\Models\DeliveryService;
use Illuminate\Support\Collection;

/**
 * What a delivery service charges to deliver one basket to a given
 * destination — one price card per service.
 *
 * The single place that turns a (service, country, city) triple into money.
 * Checkout quotes through it and `CheckoutService` bills through it, so the
 * figure on the summary and the figure on the order cannot disagree.
 *
 * Every call resolves against one service (Amana today, DHL etc. later). A
 * service can price per destination in either of the two shapes the
 * `delivery_rates` table allows: per Moroccan city (Amana domestic) or per
 * whole country (`city_id` null — every international row, and the shape DHL
 * will use).
 */
class DeliveryFeeService
{
    /**
     * The carrier a checkout falls back on when no service is chosen.
     *
     * The platform shipped every basket with Amana before services existed,
     * so a checkout with no selected service keeps billing exactly the way it
     * always did. `defaultService()` drops through to the first active service
     * if Amana is ever deactivated.
     */
    public const DEFAULT_SERVICE_SLUG = 'amana';

    /**
     * Charged when a destination has no rate on file at all.
     *
     * Only reachable if a country slipped past CheckoutRequest's validation
     * (which whitelists destinations straight out of the rate card) or if a
     * rate row was deleted between quoting and billing. Falling back to the
     * Moroccan city rate bills something defensible rather than shipping for
     * free.
     */
    public const FALLBACK_FEE = 40.00;

    /**
     * @param  int|null  $cityId  Only meaningful for Morocco, which is priced per city.
     */
    public function resolve(?string $countryCode, ?int $cityId = null, ?DeliveryService $service = null): float
    {
        if (! $countryCode) {
            return self::FALLBACK_FEE;
        }

        $serviceId = ($service ?? $this->defaultService())?->id;

        // Service row gone (fresh DB, a seeder that never ran) — no card to
        // quote from, so fall back like a destination with no rate.
        if ($serviceId === null) {
            return self::FALLBACK_FEE;
        }

        $country = strtoupper($countryCode);

        /*
         * City first, then the country-level row. Morocco has a row per city
         * and no country-level fallback; everywhere else is the reverse. One
         * lookup order covers both, and an unknown Moroccan city degrades to
         * the fallback rather than to some other city's price.
         */
        if ($cityId !== null) {
            $cityRate = DeliveryRate::query()
                ->where('delivery_service_id', $serviceId)
                ->where('country_code', $country)
                ->where('city_id', $cityId)
                ->value('fee');

            if ($cityRate !== null) {
                return (float) $cityRate;
            }
        }

        $countryRate = DeliveryRate::query()
            ->where('delivery_service_id', $serviceId)
            ->where('country_code', $country)
            ->whereNull('city_id')
            ->value('fee');

        return $countryRate !== null ? (float) $countryRate : self::FALLBACK_FEE;
    }

    /**
     * One service's whole rate card, shaped for the checkout page.
     *
     * Small enough (a few dozen rows) to hand over wholesale rather than
     * making the page ask the server every time the destination changes —
     * which would put a network round-trip between picking a city and seeing
     * the total move.
     *
     * @return array{countries: array<int, array{code: string, name: string, fee: float|null}>, cityFees: array<int, float>}
     */
    public function rateCard(?DeliveryService $service = null): array
    {
        $serviceId = ($service ?? $this->defaultService())?->id;

        if ($serviceId === null) {
            return ['countries' => [], 'cityFees' => []];
        }

        return $this->cardFrom(
            DeliveryRate::query()
                ->where('delivery_service_id', $serviceId)
                ->orderBy('country_name')
                ->get(),
        );
    }

    /**
     * Every active service with its rate card, keyed by slug.
     *
     * What CheckoutController hands the checkout page: the list of carriers
     * the buyer can pick plus, for each, the figures needed to move the
     * delivery line the moment the destination changes. One round-trip free —
     * the whole card is a few dozen rows even with several services.
     *
     * `rates` is keyed by service slug, and `services` is ordered by
     * `sort_order` (the order the page should offer them in), not by slug, so
     * the two must not be assumed to line up by index.
     *
     * @return array{services: array<int, array{id: int, slug: string, name: string, description: string|null}>, rates: array<string, array{countries: array<int, array{code: string, name: string, fee: float|null}>, cityFees: array<int, float>}>}
     */
    public function cards(): array
    {
        $services = DeliveryService::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $ratesByService = DeliveryRate::query()
            ->orderBy('country_name')
            ->get()
            ->groupBy('delivery_service_id');

        $rates = [];
        foreach ($services as $service) {
            $rates[$service->slug] = $this->cardFrom(
                $ratesByService->get($service->id) ?? collect(),
            );
        }

        return [
            'services' => $services->map(fn (DeliveryService $service) => [
                'id' => $service->id,
                'slug' => $service->slug,
                'name' => $service->name,
                'description' => $service->description,
            ])->values()->all(),
            'rates' => $rates,
        ];
    }

    /**
     * The carrier checkout pre-selects when the buyer never picks one.
     *
     * Amana is the historical carrier, so its row wins when it exists; a
     * fresh/future database where it has been retired falls through to the
     * first active service in display order, which is what the checkout page
     * would have shown anyway.
     */
    public function defaultService(): ?DeliveryService
    {
        return DeliveryService::query()
            ->where('slug', self::DEFAULT_SERVICE_SLUG)
            ->first()
            ?? DeliveryService::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->first();
    }

    /**
     * ISO codes a given service currently ships to — drives checkout validation.
     *
     * Falls back to Morocco alone when its rate card is empty (a fresh
     * database, a test that never ran DeliveryRateSeeder). Without that, an
     * empty card makes `Rule::in([])` reject *every* country and quietly
     * closes checkout altogether — much worse than shipping domestic only at
     * the fallback fee, which is what the platform did before rates existed
     * anyway.
     *
     * @return array<int, string>
     */
    public function shippableCountryCodes(?DeliveryService $service = null): array
    {
        $serviceId = ($service ?? $this->defaultService())?->id;

        if ($serviceId === null) {
            return [Address::DEFAULT_COUNTRY];
        }

        $codes = DeliveryRate::query()
            ->where('delivery_service_id', $serviceId)
            ->distinct()
            ->pluck('country_code')
            ->all();

        return $codes !== [] ? $codes : [Address::DEFAULT_COUNTRY];
    }

    /**
     * Turn a service's rate rows into the two shapes the checkout page uses.
     *
     * @param  Collection<int, DeliveryRate>  $rates
     * @return array{countries: array<int, array{code: string, name: string, fee: float|null}>, cityFees: array<int, float>}
     */
    private function cardFrom(Collection $rates): array
    {
        $countries = $rates
            ->groupBy('country_code')
            ->map(fn ($group, $code) => [
                'code' => (string) $code,
                'name' => $group->first()->country_name,
                // Null for Morocco, which has no country-level row: its fee
                // only exists once a city is chosen.
                'fee' => ($flat = $group->firstWhere('city_id', null)) ? (float) $flat->fee : null,
            ])
            ->sortBy('name')
            ->values()
            ->all();

        $cityFees = $rates
            ->whereNotNull('city_id')
            ->mapWithKeys(fn ($rate) => [(int) $rate->city_id => (float) $rate->fee])
            ->all();

        return ['countries' => $countries, 'cityFees' => $cityFees];
    }
}
