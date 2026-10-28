<?php

namespace App\Services;

use App\Models\Address;
use App\Models\CartItem;
use App\Models\DeliveryRate;
use App\Models\DeliveryService;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * What a delivery service charges to deliver one basket to a given
 * destination — one price card per service.
 *
 * The single place that turns a (service, country, city, basket) into money.
 * Checkout quotes through it and `CheckoutService` bills through it, so the
 * figure on the summary and the figure on the order cannot disagree.
 *
 * A service prices in one of two ways (`DeliveryService::pricing`):
 *  - per destination (Amana): the fee on the `delivery_rates` row — per
 *    Moroccan city, or per whole country (`city_id` null);
 *  - by weight (DHL Express): the row only gives the country's zone, and
 *    WeightPricingService prices the basket's weight on that zone's grid.
 *
 * Every carrier's card together is the list of destinations: checkout offers
 * a country if any active service has a row for it, and bills it with the
 * service that does — Amana in Morocco, DHL abroad.
 */
class DeliveryFeeService
{
    /**
     * The carrier a checkout falls back on when nothing on file serves the
     * destination.
     *
     * The platform shipped every basket with Amana before services existed,
     * so a database with no card at all (a fresh install, most tests) keeps
     * billing exactly the way it always did. `defaultService()` drops through
     * to the first active service if Amana is ever deactivated.
     */
    public const DEFAULT_SERVICE_SLUG = 'amana';

    /**
     * Charged when a destination has no rate on file at all.
     *
     * Only reachable if a country slipped past CheckoutRequest's validation
     * (which whitelists destinations straight out of the rate cards) or if a
     * rate row was deleted between quoting and billing. Falling back to the
     * Moroccan city rate bills something defensible rather than shipping for
     * free.
     */
    public const FALLBACK_FEE = 40.00;

    public function __construct(
        private readonly WeightPricingService $weightPricing,
        private readonly ProductShippingService $shipping,
    ) {}

    /**
     * @param  int|null  $cityId  Only meaningful for Morocco, which is priced per city.
     * @param  iterable<CartItem>  $lines  The basket — read only by a weight-priced service.
     */
    public function resolve(?string $countryCode, ?int $cityId = null, ?DeliveryService $service = null, iterable $lines = []): float
    {
        if (! $countryCode) {
            return self::FALLBACK_FEE;
        }

        $service ??= $this->defaultService();

        // Service row gone (fresh DB, a seeder that never ran) — no card to
        // quote from, so fall back like a destination with no rate.
        if ($service === null) {
            return self::FALLBACK_FEE;
        }

        $rate = $this->rateFor($service, $countryCode, $cityId);

        if ($rate === null) {
            return self::FALLBACK_FEE;
        }

        if ($service->isWeightPriced()) {
            $quote = $rate->zone === null
                ? null
                : $this->weightPricing->quoteForZone($service, $rate->zone, $this->shipping->pieces($lines));

            return $quote ?? self::FALLBACK_FEE;
        }

        return $rate->fee !== null ? (float) $rate->fee : self::FALLBACK_FEE;
    }

    /**
     * Which carrier bills a destination.
     *
     * The one the buyer picked, when it ships there; with none picked, the
     * first active service in display order that does (Amana in Morocco, DHL
     * abroad). A pick that cannot ship there is refused rather than swapped:
     * a buyer who chose one carrier's price must not be billed another's
     * behind their back.
     *
     * When nothing on file ships there at all — a database with no card, as
     * in most tests — the pick (or Amana) bills at the fallback fee, as
     * checkout always has.
     *
     * @throws ValidationException
     */
    public function serviceFor(?string $countryCode, ?int $cityId, ?int $requestedId = null): ?DeliveryService
    {
        $serving = $this->servicesServing($countryCode, $cityId);

        if ($serving->isEmpty()) {
            return ($requestedId !== null ? DeliveryService::find($requestedId) : null)
                ?? $this->defaultService();
        }

        if ($requestedId === null) {
            return $serving->first();
        }

        return $serving->firstWhere('id', $requestedId)
            ?? throw ValidationException::withMessages([
                'delivery_service_id' => __('buyer.checkout.err_delivery_service'),
            ]);
    }

    /**
     * Active services, in display order, with a rate for the destination: a
     * row for the city, or one for the whole country.
     *
     * @return Collection<int, DeliveryService>
     */
    public function servicesServing(?string $countryCode, ?int $cityId): Collection
    {
        if (! $countryCode) {
            return collect();
        }

        $country = strtoupper($countryCode);

        return DeliveryService::query()
            ->where('is_active', true)
            ->whereHas('rates', fn ($rates) => $rates
                ->where('country_code', $country)
                ->where(fn ($row) => $row
                    ->whereNull('city_id')
                    ->when($cityId !== null, fn ($row) => $row->orWhere('city_id', $cityId))))
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Every active service with its rate card for this basket, keyed by slug.
     *
     * What CheckoutController hands the checkout page: the list of carriers
     * plus, for each, the figures needed to move the delivery line the moment
     * the destination changes. A weight-priced service's countries arrive
     * already priced for the basket on the page — the basket cannot change
     * there, only the destination — so the page never asks the server again.
     *
     * `rates` is keyed by service slug, and `services` is ordered by
     * `sort_order` (the order the page should offer them in), not by slug, so
     * the two must not be assumed to line up by index.
     *
     * @param  iterable<CartItem>  $lines
     * @return array{services: array<int, array{id: int, slug: string, name: string, description: string|null}>, rates: array<string, array{countries: array<int, array{code: string, name: string, fee: float|null}>, cityFees: array<int, float>}>}
     */
    public function cards(iterable $lines = []): array
    {
        $services = DeliveryService::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $ratesByService = DeliveryRate::query()
            ->whereIn('delivery_service_id', $services->pluck('id'))
            ->orderBy('country_name')
            ->get()
            ->groupBy('delivery_service_id');

        $pieces = null;
        $rates = [];

        foreach ($services as $service) {
            $zoneFees = null;

            if ($service->isWeightPriced()) {
                $pieces ??= $this->shipping->pieces($lines);
                $zoneFees = $this->weightPricing->quotesByZone($service, $pieces);
            }

            $rates[$service->slug] = $this->cardFrom(
                $ratesByService->get($service->id) ?? collect(),
                $zoneFees,
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
     * Prices baskets for a fixed list of countries, ISO code ⇒ carrier name
     * and price: the carrier checkout preselects there (the first active one in display
     * order with a rate, as serviceFor()) at what it would bill.
     *
     * Built for the Google Merchant feed, which prices every item of the
     * catalogue: the cards and grids are read once here, and the returned
     * closure only does arithmetic, so the feed costs no query per item.
     *
     * A feed can only say one price per country, and Morocco is priced per
     * city, so a per-destination card gives its dearest rate there: a listing
     * may overstate delivery, never understate it. A country nothing ships
     * to, or that cannot be priced (no zone, no grid), is left out rather
     * than quoted at the fallback fee.
     *
     * @param  array<int, string>  $countryCodes
     * @return Closure(iterable<CartItem>): array<string, array{service: string, price: float}>
     */
    public function countryQuoter(array $countryCodes): Closure
    {
        $codes = array_values(array_unique(array_map('strtoupper', $countryCodes)));

        $services = DeliveryService::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with([
                'rates' => fn ($rates) => $rates->whereIn('country_code', $codes),
                'weightBands',
            ])
            ->get();

        // country ⇒ how to price it: a flat fee, or a weight-priced service and zone grid.
        $plans = [];

        foreach ($codes as $code) {
            $service = $services->first(fn (DeliveryService $service) => $service->rates->contains('country_code', $code));

            if ($service === null) {
                continue;
            }

            $rows = $service->rates->where('country_code', $code);

            if (! $service->isWeightPriced()) {
                $fee = $rows->whereNotNull('fee')->max(fn (DeliveryRate $rate) => (float) $rate->fee);

                if ($fee !== null) {
                    $plans[$code] = ['service' => $service, 'fee' => (float) $fee];
                }

                continue;
            }

            $zone = $rows->firstWhere('city_id', null)?->zone;
            $bands = $zone !== null ? $service->weightBands->where('zone', $zone)->values() : collect();

            if ($bands->isNotEmpty()) {
                $plans[$code] = ['service' => $service, 'bands' => $bands];
            }
        }

        return function (iterable $lines) use ($plans): array {
            $pieces = null;
            $quotes = [];

            foreach ($plans as $code => $plan) {
                if (isset($plan['fee'])) {
                    $price = $plan['fee'];
                } else {
                    $pieces ??= $this->shipping->pieces($lines);
                    $price = $this->weightPricing->quote($plan['service'], $plan['bands'], $pieces);
                }

                $quotes[$code] = ['service' => $plan['service']->name, 'price' => $price];
            }

            return $quotes;
        };
    }

    /**
     * The carrier billed when nothing on file serves a destination.
     *
     * Amana is the historical carrier, so its row wins when it exists; a
     * fresh/future database where it has been retired falls through to the
     * first active service in display order.
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
     * ISO codes checkout ships to — drives checkout validation. One service's
     * card when given, otherwise every active service's together.
     *
     * Falls back to Morocco alone when the card is empty (a fresh database, a
     * test that never ran DeliveryRateSeeder). Without that, an empty card
     * makes `Rule::in([])` reject *every* country and quietly closes checkout
     * altogether — much worse than shipping domestic only at the fallback
     * fee, which is what the platform did before rates existed anyway.
     *
     * @return array<int, string>
     */
    public function shippableCountryCodes(?DeliveryService $service = null): array
    {
        $codes = $this->cardRows($service)
            ->distinct()
            ->pluck('country_code')
            ->all();

        return $codes !== [] ? $codes : [Address::DEFAULT_COUNTRY];
    }

    /**
     * The Moroccan cities with a rate — the ones checkout offers. One
     * service's card when given, otherwise every active service's together.
     *
     * Null (no restriction) when there are no city rows at all: a fresh
     * database or a test that never ran DeliveryRateSeeder, where every city
     * in the registry has to stay selectable at the fallback fee. Once the
     * card has even one city, only the cities on it are deliverable, which is
     * how the admin desk closes a city.
     *
     * @return array<int, int>|null
     */
    public function deliverableCityIds(?DeliveryService $service = null): ?array
    {
        $ids = $this->cardRows($service)
            ->where('country_code', Address::DEFAULT_COUNTRY)
            ->whereNotNull('city_id')
            ->distinct()
            ->pluck('city_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $ids !== [] ? $ids : null;
    }

    /**
     * Narrows an `addresses` query to destinations the cards still serve: a
     * country on a card and, for Morocco, a city on one.
     *
     * Takes either an Eloquent or a query builder, since it is used both to
     * list the buyer's saved addresses and inside an `exists` validation rule.
     * An address with no country is a Moroccan one (the column's default).
     *
     * @template TQuery of \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public function scopeToDeliverable($query, ?DeliveryService $service = null)
    {
        $codes = $this->shippableCountryCodes($service);
        $cityIds = $this->deliverableCityIds($service);

        $query->where(function ($q) use ($codes) {
            $q->whereIn('country', $codes);

            if (in_array(Address::DEFAULT_COUNTRY, $codes, true)) {
                $q->orWhereNull('country');
            }
        });

        if ($cityIds !== null) {
            $query->where(function ($q) use ($cityIds) {
                $q->where(fn ($abroad) => $abroad
                    ->whereNotNull('country')
                    ->where('country', '<>', Address::DEFAULT_COUNTRY))
                    ->orWhereIn('city_id', $cityIds);
            });
        }

        return $query;
    }

    /** The row that prices a destination: the city's, else the whole country's. */
    private function rateFor(DeliveryService $service, string $countryCode, ?int $cityId): ?DeliveryRate
    {
        $rows = DeliveryRate::query()
            ->where('delivery_service_id', $service->id)
            ->where('country_code', strtoupper($countryCode));

        /*
         * City first, then the country-level row. Morocco has a row per city
         * and no country-level fallback; everywhere else is the reverse. One
         * lookup order covers both, and an unknown Moroccan city degrades to
         * the fallback rather than to some other city's price.
         */
        if ($cityId !== null) {
            $cityRate = (clone $rows)->where('city_id', $cityId)->first();

            if ($cityRate !== null) {
                return $cityRate;
            }
        }

        return $rows->whereNull('city_id')->first();
    }

    /** One service's rows, or every active service's. */
    private function cardRows(?DeliveryService $service)
    {
        return DeliveryRate::query()->when(
            $service,
            fn ($rows) => $rows->where('delivery_service_id', $service->id),
            fn ($rows) => $rows->whereIn(
                'delivery_service_id',
                DeliveryService::query()->where('is_active', true)->select('id'),
            ),
        );
    }

    /**
     * Turn a service's rate rows into the two shapes the checkout page uses.
     *
     * @param  Collection<int, DeliveryRate>  $rates
     * @param  array<int, float>|null  $zoneFees  This basket's price per zone, for a weight-priced service.
     * @return array{countries: array<int, array{code: string, name: string, fee: float|null}>, cityFees: array<int, float>}
     */
    private function cardFrom(Collection $rates, ?array $zoneFees = null): array
    {
        $countries = $rates
            ->groupBy('country_code')
            ->map(function ($group, $code) use ($zoneFees) {
                $flat = $group->firstWhere('city_id', null);

                return [
                    'code' => (string) $code,
                    'name' => $group->first()->country_name,
                    // Null for Morocco, which has no country-level row: its
                    // fee only exists once a city is chosen. Null too for a
                    // zone with no grid — a country that cannot be priced.
                    'fee' => match (true) {
                        $flat === null => null,
                        $zoneFees !== null => $flat->zone !== null ? ($zoneFees[$flat->zone] ?? null) : null,
                        default => $flat->fee !== null ? (float) $flat->fee : null,
                    },
                ];
            })
            // Accents folded, or "Égypte" and "États-Unis" sort after "Zimbabwe".
            ->sortBy(fn (array $country) => Str::lower(Str::ascii($country['name'])))
            ->values()
            ->all();

        $cityFees = $rates
            ->whereNotNull('city_id')
            ->whereNotNull('fee')
            ->mapWithKeys(fn ($rate) => [(int) $rate->city_id => (float) $rate->fee])
            ->all();

        return ['countries' => $countries, 'cityFees' => $cityFees];
    }
}
