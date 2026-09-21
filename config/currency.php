<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Base currency
    |--------------------------------------------------------------------------
    |
    | Every price in the database is stored in this currency: products are
    | priced in MAD by the admin, orders settle in MAD, and the earnings
    | ledger is MAD. Everything else on this page is presentation — a rate
    | applied on the way out to the storefront, never on the way into the
    | database.
    |
    */

    'base' => 'MAD',

    /*
    |--------------------------------------------------------------------------
    | Currencies the storefront can display
    |--------------------------------------------------------------------------
    |
    | `decimals` is what the amount is rounded to for display. MAD shows whole
    | dirhams because that is how the market prices things; the others show
    | cents because a European reading "€24" for a 249 MAD piece would think
    | the price was round when it is not.
    |
    | `locale` drives Intl.NumberFormat on the frontend, so the separators and
    | symbol placement match how the currency is actually written in the
    | region that uses it.
    |
    */

    'supported' => [
        'MAD' => ['symbol' => 'MAD', 'decimals' => 0, 'locale' => 'fr-MA'],
        'EUR' => ['symbol' => '€', 'decimals' => 2, 'locale' => 'fr-FR'],
        'USD' => ['symbol' => '$', 'decimals' => 2, 'locale' => 'en-US'],
        'GBP' => ['symbol' => '£', 'decimals' => 2, 'locale' => 'en-GB'],
        'CAD' => ['symbol' => 'CA$', 'decimals' => 2, 'locale' => 'en-CA'],
        'AED' => ['symbol' => 'AED', 'decimals' => 2, 'locale' => 'ar-AE'],
        'SAR' => ['symbol' => 'SAR', 'decimals' => 2, 'locale' => 'ar-SA'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Country → currency
    |--------------------------------------------------------------------------
    |
    | Which currency a visitor is shown, by the country their IP resolves to.
    | Deliberately skewed to where Hirfaty actually ships (see
    | DeliveryRateSeeder's country list) rather than trying to map the world —
    | anything not listed here falls back to `base`, which is the honest
    | answer for a country the platform has no delivery rate for anyway.
    |
    | The eurozone is spelled out rather than inferred: "in Europe" is not the
    | same as "uses the euro", and guessing wrong is how a British visitor
    | gets quoted in a currency they do not spend.
    |
    */

    'country_currency' => [
        // Morocco — home market.
        'MA' => 'MAD',

        // Eurozone, limited to where the diaspora and the export market are.
        'FR' => 'EUR', 'ES' => 'EUR', 'BE' => 'EUR', 'NL' => 'EUR',
        'DE' => 'EUR', 'IT' => 'EUR', 'PT' => 'EUR', 'IE' => 'EUR',
        'AT' => 'EUR', 'FI' => 'EUR', 'LU' => 'EUR', 'GR' => 'EUR',

        // Non-euro Europe and North America.
        'GB' => 'GBP',
        'US' => 'USD',
        'CA' => 'CAD',
        'CH' => 'EUR', // Swiss francs are not offered; euro is the closer read.

        // Gulf.
        'AE' => 'AED',
        'SA' => 'SAR',
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback rates (1 MAD → currency)
    |--------------------------------------------------------------------------
    |
    | Used when the live API has never answered or its cached value has
    | expired and the refetch failed. They are approximate by nature and will
    | drift; the point is that a rates outage degrades to a slightly stale
    | price rather than to a blank page or a nonsense number.
    |
    | MAD is 1.0 by definition — it is the base.
    |
    */

    'fallback_rates' => [
        'MAD' => 1.0,
        'EUR' => 0.092,
        'USD' => 0.099,
        'GBP' => 0.078,
        'CAD' => 0.138,
        'AED' => 0.364,
        'SAR' => 0.372,
    ],

    /*
    |--------------------------------------------------------------------------
    | Live rates
    |--------------------------------------------------------------------------
    |
    | open.er-api.com needs no key and answers with every rate against one
    | base in a single call, which is why it is the default. `ttl` is how long
    | a successful fetch is trusted; rates move far too little for indicative
    | display pricing to justify asking more than once a day.
    |
    | `timeout` is short on purpose: this can run inside a web request when
    | the scheduled refresh has not happened yet, and a slow rates API must
    | never become a slow storefront.
    |
    */

    'rates_api' => [
        'enabled' => env('CURRENCY_RATES_ENABLED', true),
        'url' => env('CURRENCY_RATES_URL', 'https://open.er-api.com/v6/latest/MAD'),
        'timeout' => 4,
        'ttl_hours' => 24,
    ],

    /*
    |--------------------------------------------------------------------------
    | IP geolocation
    |--------------------------------------------------------------------------
    |
    | How a visitor's country is worked out when no explicit choice is in the
    | session. A CDN header is preferred and costs nothing; the HTTP lookup is
    | the fallback and is cached per IP so a returning visitor never pays for
    | it twice.
    |
    | Disable `enabled` to switch geolocation off entirely — every visitor
    | then gets `base` until they pick a currency themselves.
    |
    */

    'geo' => [
        'enabled' => env('CURRENCY_GEO_ENABLED', true),
        'url' => env('CURRENCY_GEO_URL', 'http://ip-api.com/json/:ip?fields=status,countryCode'),
        'timeout' => 2,
        'cache_hours' => 24,

        /*
         * When the request carries no usable client IP — local development,
         * where everything arrives from 127.0.0.1 — fall back to geolocating
         * this server's own outbound address.
         *
         * On by default only in `local`, and that restriction is the point:
         * in production the client IP is real (nginx forwards it and
         * `trustProxies` is set), so this path should never be reached. If
         * forwarding ever broke, having it enabled would geolocate every
         * visitor to the server's country instead of falling back to the base
         * currency — a wrong answer everywhere rather than a safe one.
         */
        'resolve_own_ip' => env('CURRENCY_GEO_RESOLVE_OWN_IP', env('APP_ENV') === 'local'),
    ],

];
