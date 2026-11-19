<?php

namespace App\Services\DhlExpress;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The towns DHL Express files a postcode under, from its address check —
 * what checkout offers in its "Ville" field abroad, and what the desk's
 * shipment dialog suggests. Cached a day per postcode.
 */
class DhlTownService
{
    public function __construct(private readonly DhlExpressClient $client) {}

    public function enabled(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * DHL's towns for a postcode, title-cased ("PARIS" → "Paris"); empty when
     * DHL does not know the postcode.
     *
     * @return list<string>
     *
     * @throws DhlExpressException
     */
    public function townsFor(string $country, string $postalCode): array
    {
        $country = Str::upper(trim($country));
        $postalCode = Str::upper(trim($postalCode));

        return Cache::remember("dhl.towns.{$country}.".md5($postalCode), now()->addDay(), fn () => collect(
            $this->client->validateAddress($country, $postalCode),
        )->pluck('cityName')->filter()->map(fn ($n) => Str::title(Str::lower((string) $n)))->unique()->values()->all());
    }

    /**
     * townsFor(), for a buyer: an empty list rather than an error when DHL
     * is not configured or cannot answer — the field stays free to type.
     *
     * @return list<string>
     */
    public function suggestions(string $country, string $postalCode): array
    {
        if (! $this->enabled()) {
            return [];
        }

        try {
            return $this->townsFor($country, $postalCode);
        } catch (DhlExpressException $e) {
            Log::info("DHL towns for {$country} {$postalCode} unavailable: ".$e->getMessage());

            return [];
        }
    }
}
