<?php

namespace App\Console\Commands;

use App\Services\ExchangeRateService;
use Illuminate\Console\Command;

/**
 * Pull today's rates so a buyer never has to.
 *
 * ExchangeRateService fetches lazily when its cache is cold, which works but
 * makes one unlucky visitor wait on an external API. Running this daily means
 * the cache is warm before anyone asks.
 */
class RefreshExchangeRates extends Command
{
    protected $signature = 'currency:refresh-rates';

    protected $description = 'Fetch and cache the latest MAD exchange rates';

    public function handle(ExchangeRateService $rates): int
    {
        // Without this the command reads its own still-valid cache entry and
        // never actually reaches the API.
        $rates->forget();

        $fresh = $rates->all();

        $base = strtoupper((string) config('currency.base', 'MAD'));
        $fallback = (array) config('currency.fallback_rates', []);

        /*
         * `all()` always returns something — it falls back rather than
         * failing — so "did we get real rates?" has to be asked separately.
         * Comparing against the fallback table is the honest test: identical
         * values mean the fetch did not happen.
         */
        if ($fresh == $fallback) {
            $this->warn('Rates API unavailable — the configured fallback rates are still in use.');

            return self::FAILURE;
        }

        foreach ($fresh as $code => $rate) {
            if ($code === $base) {
                continue;
            }

            $this->line(sprintf('  1 %s = %s %s', $base, rtrim(rtrim(number_format($rate, 4), '0'), '.'), $code));
        }

        $this->info('Exchange rates refreshed.');

        return self::SUCCESS;
    }
}
