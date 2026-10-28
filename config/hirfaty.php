<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Operations inbox
    |--------------------------------------------------------------------------
    |
    | Where the desk is told about work it has to do — today, that a basket has
    | been bought and needs collecting from the workshops and delivering.
    |
    | Deliberately not the super-admin User's own email: this is a role, not a
    | person, and whoever runs fulfilment may never log in. Set ADMIN_EMAIL to
    | an empty string to silence these notifications entirely.
    |
    */

    'admin_email' => env('ADMIN_EMAIL', 'creati.maroc@gmail.com'),

    /*
    |--------------------------------------------------------------------------
    | Artisan onboarding inbox
    |--------------------------------------------------------------------------
    |
    | Told whenever an artisan signs up on /sell, so someone can call them and
    | file their first listings (sellers never create products themselves).
    | Accounts the desk opens by hand are not announced — the desk already
    | knows. Set SELLER_SIGNUP_EMAIL to an empty string to silence it.
    |
    */

    'seller_signup_email' => env('SELLER_SIGNUP_EMAIL', 's.fatia@hirfaty.com'),

    /*
    |--------------------------------------------------------------------------
    | Staging guard
    |--------------------------------------------------------------------------
    |
    | Requests whose host is `staging_host` are the test copy of the site.
    | The admin pages where data is easy to create by mistake (new product, a
    | user's profile) shout about it and point at `production_url`, so nothing
    | real is filed here. Shared with the frontend as the `staging` prop.
    |
    */

    'staging_host' => env('STAGING_DOMAIN', 'init.hirfaty.com'),

    'production_url' => env('PRODUCTION_URL', 'https://hirfaty.com'),

    /*
    |--------------------------------------------------------------------------
    | Search-engine indexing
    |--------------------------------------------------------------------------
    |
    | Only the real site may be indexed. Everywhere else — local, staging,
    | a review copy — robots.txt disallows everything, every page carries
    | `noindex`, and responses send an `X-Robots-Tag` header, so a test copy
    | can never compete with hirfaty.com in search results. Requests on
    | `staging_host` are refused indexing even if this is left on.
    |
    */

    'indexable' => (bool) env('SEO_INDEXABLE', env('APP_ENV') === 'production'),

    /*
    |--------------------------------------------------------------------------
    | Google Merchant Center shipping
    |--------------------------------------------------------------------------
    |
    | The countries the Google feed prices delivery for, as ISO codes: each
    | item carries one `g:shipping` per country, at what checkout bills for
    | one unit, so nothing has to be keyed into Merchant Center's shipping
    | settings by hand. List the countries the Merchant Center account targets
    | — Google caps how many shipping entries one item may carry, so this is
    | not every country on the DHL card. A country no carrier ships to is
    | skipped. Comma-separated in GOOGLE_FEED_SHIPPING_COUNTRIES.
    |
    */

    'google_feed_shipping_countries' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('GOOGLE_FEED_SHIPPING_COUNTRIES', 'MA,FR,BE,CH,ES,IT,DE,NL,GB,US,CA,AE,SA,QA')),
    ))),

];
