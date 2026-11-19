<?php

namespace App\Services\DhlExpress;

use App\Models\DeliveryService;
use App\Services\ProductShippingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * What DHL Express itself charges Hirfaty's account to ship a basket abroad —
 * the price checkout shows and bills, rather than the rate card's estimate.
 *
 * Asked through DhlExpressClient::rate() for Express Worldwide, one box per
 * unit as ProductShippingService counts pieces (a piece with no weight on
 * file weighs the service's default). DHL bills each box on the greater of
 * its weight and its volume (L×W×H ÷ 5000), adds fuel and surcharges, and
 * answers in MAD for a Moroccan account.
 *
 * Kept for CACHE_MINUTES per destination and basket, so the quote the page
 * shows is exactly what CheckoutService bills a moment later. Null whenever
 * DHL cannot answer (not configured, down, slow, no MAD price): the caller
 * falls back on the rate card, and checkout never stops for DHL.
 */
class DhlRateService
{
    public const CACHE_MINUTES = 30;

    public function __construct(
        private readonly DhlExpressClient $client,
        private readonly ProductShippingService $shipping,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('services.dhl_express.live_rates', true) && $this->client->isConfigured();
    }

    /**
     * DHL's price in MAD for the basket to the destination, or null when it
     * cannot be had.
     *
     * @param  iterable<mixed>  $lines  Cart items (product, variant, quantity).
     */
    public function quote(DeliveryService $service, string $country, ?string $postalCode, iterable $lines): ?float
    {
        if (! $this->enabled()) {
            return null;
        }

        $packages = $this->packages($service, $lines);

        if ($packages === []) {
            return null;
        }

        $country = Str::upper(trim($country));
        $postalCode = filled($postalCode) ? Str::upper(trim((string) $postalCode)) : null;
        $product = (string) config('services.dhl_express.product_code', 'P');
        $key = 'dhl.rate.'.md5(json_encode([$this->client->baseUrl(), $this->client->accountNumber(), $product, $country, $postalCode, $packages]));

        if (($cached = Cache::get($key)) !== null) {
            return (float) $cached;
        }

        // DHL needs a place it knows: the postcode when typed, else the
        // country's capital (the price does not depend on the town).
        $capital = CountryCapitals::for($country);

        try {
            try {
                $offer = $this->client->rate($product, $country, $postalCode, $postalCode ? null : $capital, $packages);
            } catch (DhlExpressException $e) {
                // A postcode DHL cannot place (a typo, a format it refuses): the capital prices it.
                if ($postalCode === null || $capital === null || $e->status === null || $e->status >= 500) {
                    throw $e;
                }

                $offer = $this->client->rate($product, $country, null, $capital, $packages);
            }
        } catch (DhlExpressException $e) {
            Log::warning("DHL rate for {$country} failed, rate card used: ".$e->getMessage());

            return null;
        }

        $price = collect((array) ($offer['totalPrice'] ?? []))
            ->filter(fn ($p) => ($p['priceCurrency'] ?? null) === 'MAD' && isset($p['price']))
            ->sortBy(fn ($p) => ($p['currencyType'] ?? '') === 'BILLC' ? 0 : 1)
            ->first();

        if (! $price || (float) $price['price'] <= 0) {
            Log::warning("DHL gave no MAD price for {$country}, rate card used.");

            return null;
        }

        $fee = round((float) $price['price'], 2);
        Cache::put($key, $fee, now()->addMinutes(self::CACHE_MINUTES));

        return $fee;
    }

    /**
     * The basket as DHL's boxes: one per unit, its weight (or the service's
     * default) and, when known, its dimensions.
     *
     * @param  iterable<mixed>  $lines
     * @return list<array{weight: float, dimensions?: array{length: float, width: float, height: float}}>
     */
    private function packages(DeliveryService $service, iterable $lines): array
    {
        $default = (float) ($service->default_piece_weight ?? 1) ?: 1.0;

        return array_map(fn (array $piece) => array_filter([
            'weight' => round(max(0.1, (float) ($piece['weight'] ?? $default)), 3),
            'dimensions' => $piece['dimensions'] ? [
                'length' => round(max(1, (float) $piece['dimensions']['length']), 1),
                'width' => round(max(1, (float) $piece['dimensions']['width']), 1),
                'height' => round(max(1, (float) $piece['dimensions']['height']), 1),
            ] : null,
        ]), $this->shipping->pieces($lines));
    }
}
