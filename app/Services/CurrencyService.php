<?php

namespace App\Services;

use Illuminate\Http\Request;

/**
 * Which currency this visitor sees, and what a MAD figure looks like in it.
 *
 * The one place that answers "what currency are we in". Resolution order:
 *
 *   1. an explicit choice the buyer made (session), which always wins;
 *   2. the country their IP resolves to;
 *   3. the base currency.
 *
 * Only the first is reliable, which is exactly why it outranks the other
 * two — see GeoLocationService on how often IP geolocation is wrong.
 *
 * Display only. Products are priced in MAD, orders settle in MAD, and the
 * earnings ledger is MAD; nothing here writes a converted figure back.
 * `SESSION_KEY` holds a currency code, never a rate — a rate cached in a
 * session would go stale in a way the buyer could not see.
 */
class CurrencyService
{
    public const SESSION_KEY = 'currency';

    public function __construct(
        private ExchangeRateService $rates,
        private GeoLocationService $geo,
    ) {}

    /**
     * The active currency code for this request.
     */
    public function resolve(Request $request): string
    {
        $chosen = $request->session()->get(self::SESSION_KEY);

        if (is_string($chosen) && $this->isSupported($chosen)) {
            return strtoupper($chosen);
        }

        $country = $this->geo->countryFor($request);

        if ($country !== null) {
            $mapped = config("currency.country_currency.{$country}");

            if (is_string($mapped) && $this->isSupported($mapped)) {
                return strtoupper($mapped);
            }
        }

        return $this->base();
    }

    /**
     * Everything the frontend needs to render a price, resolved once per
     * response and shared globally (HandleInertiaRequests).
     *
     * The rate travels with it so the client can convert without a round trip
     * per price — a listing page renders dozens, and asking the server for
     * each would be absurd. It is safe to expose: it is public information,
     * and it is display-only by construction.
     *
     * @return array{code: string, symbol: string, decimals: int, locale: string, rate: float, is_base: bool}
     */
    public function payload(Request $request): array
    {
        $code = $this->resolve($request);
        $meta = (array) config("currency.supported.{$code}", []);

        return [
            'code' => $code,
            'symbol' => (string) ($meta['symbol'] ?? $code),
            'decimals' => (int) ($meta['decimals'] ?? 2),
            'locale' => (string) ($meta['locale'] ?? 'fr-MA'),
            'rate' => $this->rates->for($code),
            // Lets the UI drop the "prices shown in X, charged in MAD" notice
            // for the home market, where it would be noise.
            'is_base' => $code === $this->base(),
        ];
    }

    /**
     * Remember an explicit choice. Returns false for anything unsupported, so
     * the caller can 404 rather than store junk that `resolve()` would then
     * have to defend against.
     */
    public function remember(Request $request, string $currency): bool
    {
        if (! $this->isSupported($currency)) {
            return false;
        }

        $request->session()->put(self::SESSION_KEY, strtoupper($currency));

        return true;
    }

    /**
     * Convert a MAD amount for display.
     */
    public function convert(float $amount, string $currency): float
    {
        return $this->rates->convert($amount, $currency);
    }

    /**
     * The unrounded multiplier for one base unit.
     *
     * Distinct from `convert(1.0, ...)`, which rounds to the currency's
     * display decimals — that would turn a 0.0923 EUR rate into 0.09 and
     * bend every price derived from it.
     */
    public function rateFor(string $currency): float
    {
        return $this->rates->for($currency);
    }

    /**
     * The currencies offered in the header switcher.
     *
     * @return array<int, array{code: string, symbol: string}>
     */
    public function options(): array
    {
        return collect((array) config('currency.supported', []))
            ->map(fn (array $meta, string $code) => [
                'code' => $code,
                'symbol' => (string) ($meta['symbol'] ?? $code),
            ])
            ->values()
            ->all();
    }

    public function isSupported(string $currency): bool
    {
        return array_key_exists(
            strtoupper($currency),
            (array) config('currency.supported', []),
        );
    }

    public function base(): string
    {
        return strtoupper((string) config('currency.base', 'MAD'));
    }
}
