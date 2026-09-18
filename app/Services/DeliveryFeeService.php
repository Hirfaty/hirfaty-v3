<?php

namespace App\Services;

use App\Models\Address;
use App\Models\AmanaDeliveryRate;

/**
 * What Amana charges to deliver one basket to a given destination.
 *
 * The single place that turns a (country, city) pair into money. Checkout
 * quotes through it and `CheckoutService` bills through it, so the figure on
 * the summary and the figure on the order cannot disagree.
 */
class DeliveryFeeService
{
    /**
     * Charged when a destination has no rate on file at all.
     *
     * Only reachable if a country slipped past CheckoutRequest's validation
     * (which whitelists destinations straight out of this table) or if a rate
     * row was deleted between quoting and billing. Falling back to the old
     * flat fee bills something defensible rather than shipping for free.
     */
    public const FALLBACK_FEE = 20.00;

    /**
     * @param  int|null  $cityId  Only meaningful for Morocco, which is priced per city.
     */
    public function resolve(?string $countryCode, ?int $cityId = null): float
    {
        if (! $countryCode) {
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
            $cityRate = AmanaDeliveryRate::query()
                ->where('country_code', $country)
                ->where('city_id', $cityId)
                ->value('fee');

            if ($cityRate !== null) {
                return (float) $cityRate;
            }
        }

        $countryRate = AmanaDeliveryRate::query()
            ->where('country_code', $country)
            ->whereNull('city_id')
            ->value('fee');

        return $countryRate !== null ? (float) $countryRate : self::FALLBACK_FEE;
    }

    /**
     * The whole rate card, shaped for the checkout page.
     *
     * Small enough (a few dozen rows) to hand over wholesale rather than
     * making the page ask the server every time the destination changes —
     * which would put a network round-trip between picking a city and seeing
     * the total move.
     *
     * @return array{countries: array<int, array{code: string, name: string, fee: float|null}>, cityFees: array<int, float>}
     */
    public function rateCard(): array
    {
        $rates = AmanaDeliveryRate::query()->orderBy('country_name')->get();

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

    /**
     * ISO codes Hirfaty currently ships to — drives checkout validation.
     *
     * Falls back to Morocco alone when the rate card has not been loaded (a
     * fresh database, a test that never ran AmanaDeliveryRateSeeder). Without
     * that, an empty table makes `Rule::in([])` reject *every* country and
     * quietly closes checkout altogether — much worse than shipping domestic
     * only at the fallback fee, which is what the platform did before rates
     * existed anyway.
     *
     * @return array<int, string>
     */
    public function shippableCountryCodes(): array
    {
        $codes = AmanaDeliveryRate::query()
            ->distinct()
            ->pluck('country_code')
            ->all();

        return $codes !== [] ? $codes : [Address::DEFAULT_COUNTRY];
    }
}
