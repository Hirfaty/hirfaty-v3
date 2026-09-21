<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Which country a request came from, as a two-letter code.
 *
 * Used only to pick a default display currency, so every failure path here
 * returns null and lets the caller fall back to the base currency. Nothing
 * about access, pricing correctness or money depends on this being right —
 * which is just as well, because IP geolocation routinely is not (VPNs,
 * travellers, mobile carriers routing through another country). That is why
 * the buyer can always override it from the header.
 */
class GeoLocationService
{
    /**
     * Headers a CDN or proxy may already have resolved for us, in preference
     * order. Free and instant when present, so they are always tried before
     * paying for an HTTP lookup. Cloudflare sets the first; the others are
     * common enough to be worth reading if the stack ever changes.
     */
    private const COUNTRY_HEADERS = [
        'CF-IPCountry',
        'X-Vercel-IP-Country',
        'X-Geo-Country',
    ];

    public function countryFor(Request $request): ?string
    {
        if (! config('currency.geo.enabled', true)) {
            return null;
        }

        foreach (self::COUNTRY_HEADERS as $header) {
            $value = $request->header($header);

            if ($this->isUsableCode($value)) {
                return strtoupper($value);
            }
        }

        $ip = $request->ip();

        if ($ip && ! $this->isLocal($ip)) {
            return $this->lookup($ip);
        }

        /*
         * No usable client IP — in practice, local development, where the
         * request always arrives from 127.0.0.1 however the machine is
         * routed. Asking the provider about *our own* outbound address
         * instead makes the detection testable: flip a VPN and the answer
         * changes, because the outbound address does.
         *
         * Off in production by default (config), where it would be actively
         * wrong: if `trustProxies` ever stopped resolving the forwarded
         * address, every visitor would silently be geolocated to the server's
         * own country rather than falling back to the base currency.
         */
        if (config('currency.geo.resolve_own_ip', false)) {
            return $this->lookupOwn();
        }

        return null;
    }

    /**
     * Where this server itself appears to be, per the provider.
     *
     * Cached under its own key — it is not tied to any visitor — but only
     * briefly, unlike the per-visitor lookup's 24 hours. The whole reason
     * this path exists is to make detection observable in development, and a
     * day-long cache would mean flipping a VPN appeared to do nothing. Short
     * enough to respond to a change, long enough not to call out on every
     * page load.
     */
    private const OWN_IP_CACHE_MINUTES = 5;

    private function lookupOwn(): ?string
    {
        $cached = Cache::get('geo.country.self');

        if ($cached !== null) {
            return $cached === '' ? null : $cached;
        }

        // The provider reports the caller's own address when asked about no
        // particular one, so the template's `:ip` slot is simply left empty.
        $country = $this->request('');

        Cache::put(
            'geo.country.self',
            $country ?? '',
            now()->addMinutes(self::OWN_IP_CACHE_MINUTES),
        );

        return $country;
    }

    /**
     * Ask the geolocation API, cached per IP.
     *
     * The cache holds failures too, as an empty string: a datacentre IP the
     * provider cannot place would otherwise trigger a fresh HTTP call on
     * every single request that IP makes.
     */
    private function lookup(string $ip): ?string
    {
        $key = 'geo.country.'.md5($ip);

        $cached = Cache::get($key);

        if ($cached !== null) {
            return $cached === '' ? null : $cached;
        }

        $country = $this->request($ip);

        Cache::put(
            $key,
            $country ?? '',
            now()->addHours((int) config('currency.geo.cache_hours', 24)),
        );

        return $country;
    }

    private function request(string $ip): ?string
    {
        try {
            $url = str_replace(
                ':ip',
                urlencode($ip),
                (string) config('currency.geo.url'),
            );

            $response = Http::timeout((int) config('currency.geo.timeout', 2))->get($url);

            if (! $response->successful()) {
                return null;
            }

            // ip-api.com reports failures in the body with a 200, so the
            // status alone is not enough to call this a success.
            if ($response->json('status') === 'fail') {
                return null;
            }

            $code = $response->json('countryCode');

            return $this->isUsableCode($code) ? strtoupper($code) : null;
        } catch (\Throwable $e) {
            Log::debug('Geolocation lookup failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Loopback and private ranges never resolve to a country, and asking
     * about them wastes a request per developer page load.
     */
    private function isLocal(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false;
    }

    private function isUsableCode(mixed $code): bool
    {
        // Cloudflare sends "XX" for anonymising proxies and "T1" for Tor,
        // neither of which is a country.
        return is_string($code)
            && preg_match('/^[A-Za-z]{2}$/', $code) === 1
            && ! in_array(strtoupper($code), ['XX', 'T1'], true);
    }
}
