<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * How many units of a display currency one dirham buys.
 *
 * Every price in the database is MAD (config/currency.php explains why), so
 * everything this class returns is a multiplier applied on the way out to the
 * storefront. It never converts on the way in.
 *
 * Three tiers, in order: a cached live rate, a fresh fetch, then the
 * hardcoded fallback. The last one matters more than it looks — this can be
 * called during a web request, and a rates API that is slow or down must
 * degrade to a slightly stale price rather than to an error page or, worse,
 * a zero.
 */
class ExchangeRateService
{
    /** Bumped when the cached payload's shape changes. */
    private const CACHE_KEY = 'currency.rates.v1';

    /**
     * Every supported currency's rate against MAD.
     *
     * @return array<string, float>
     */
    public function all(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $fetched = $this->fetch();

        if ($fetched !== null) {
            Cache::put(
                self::CACHE_KEY,
                $fetched,
                now()->addHours((int) config('currency.rates_api.ttl_hours', 24)),
            );

            return $fetched;
        }

        /*
         * Deliberately not cached. Caching the fallback would mean one failed
         * fetch pins stale figures for a whole TTL, when the next request
         * might well succeed.
         */
        return $this->fallback();
    }

    /**
     * One currency's rate, or 1.0 for the base and for anything unsupported.
     *
     * Unsupported returns 1.0 rather than throwing: the caller is rendering a
     * price, and a wrong-but-present figure in the base currency beats a
     * fatal on a product page.
     */
    public function for(string $currency): float
    {
        $currency = strtoupper($currency);

        if ($currency === $this->base()) {
            return 1.0;
        }

        return (float) ($this->all()[$currency] ?? 1.0);
    }

    /**
     * Convert a base-currency (MAD) amount into `$currency`.
     *
     * Rounded to that currency's `decimals` — a euro price carries cents, a
     * dirham price does not (config/currency.php).
     */
    public function convert(float $amount, string $currency): float
    {
        $currency = strtoupper($currency);
        $decimals = (int) (config("currency.supported.{$currency}.decimals") ?? 2);

        return round($amount * $this->for($currency), $decimals);
    }

    /**
     * Drop whatever is cached, so the next read refetches.
     *
     * Used by the scheduled refresh: without it a daily command would read
     * its own still-valid cache entry and never actually hit the API.
     */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Ask the rates API, or null when it cannot be trusted.
     *
     * "Cannot be trusted" covers more than a failed request: a 200 with a
     * malformed body, or one missing the currencies actually displayed, is
     * just as unusable and takes the same fallback path.
     *
     * @return array<string, float>|null
     */
    private function fetch(): ?array
    {
        if (! config('currency.rates_api.enabled', true)) {
            return null;
        }

        try {
            $response = Http::timeout((int) config('currency.rates_api.timeout', 4))
                ->get((string) config('currency.rates_api.url'));

            if (! $response->successful()) {
                Log::warning('Exchange rates API returned '.$response->status());

                return null;
            }

            $rates = $response->json('rates');

            if (! is_array($rates)) {
                Log::warning('Exchange rates API returned no rates array.');

                return null;
            }

            $wanted = array_keys((array) config('currency.supported', []));
            $result = [];

            foreach ($wanted as $code) {
                // The base is 1.0 by definition and some APIs omit it.
                if ($code === $this->base()) {
                    $result[$code] = 1.0;

                    continue;
                }

                if (isset($rates[$code]) && is_numeric($rates[$code]) && $rates[$code] > 0) {
                    $result[$code] = (float) $rates[$code];
                }
            }

            /*
             * A payload carrying only the base is not a usable rate table —
             * treat it as a failure so the fallback's real figures are used
             * instead of quoting every foreign price at parity with MAD.
             */
            if (count($result) <= 1) {
                Log::warning('Exchange rates API carried none of the supported currencies.');

                return null;
            }

            // Anything the API didn't carry keeps its fallback rather than
            // silently vanishing into a 1.0 at `for()`.
            return array_merge($this->fallback(), $result);
        } catch (\Throwable $e) {
            Log::warning('Exchange rates fetch failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * @return array<string, float>
     */
    private function fallback(): array
    {
        return array_map(
            fn ($rate) => (float) $rate,
            (array) config('currency.fallback_rates', []),
        );
    }

    private function base(): string
    {
        return strtoupper((string) config('currency.base', 'MAD'));
    }
}
