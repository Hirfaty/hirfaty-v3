<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Social sign-in
    |--------------------------------------------------------------------------
    |
    | The two buttons under the email field on /login. Both are optional: with
    | no credentials set, SocialAuthService::isConfigured() reports the provider
    | unavailable, the button is not rendered and its routes 404 — so a fresh
    | checkout with an empty .env keeps a working sign-in screen rather than two
    | links to an OAuth error page.
    |
    | The redirect URIs must match what is registered with each provider
    | exactly, including scheme and path:
    |   https://your-host/auth/google/callback
    |   https://your-host/auth/facebook/callback
    |
    */

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI', '/auth/facebook/callback'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payzone (card payments)
    |--------------------------------------------------------------------------
    |
    | Card payment is offered at checkout only when all three credentials are
    | set (see PayzoneClient::isConfigured). The sandbox paywall is
    | https://payment-sandbox.payzone.ma/pwv4/launch; Payzone sends the
    | production URL with the live credentials.
    |
    | Payzone's callback must reach this app from the internet, which
    | localhost cannot. PAYZONE_CALLBACK_BASE_URL points just the callback at
    | a tunnel (e.g. `cloudflared tunnel --url http://localhost:8000`) while
    | the browser stays on APP_URL. Leave it empty on staging and production.
    |
    */

    'payzone' => [
        'merchant_account' => env('PAYZONE_MERCHANT_ACCOUNT'),
        'secret_key' => env('PAYZONE_SECRET_KEY'),
        'notification_key' => env('PAYZONE_NOTIFICATION_KEY'),
        'paywall_url' => env('PAYZONE_PAYWALL_URL', 'https://payment-sandbox.payzone.ma/pwv4/launch'),
        'callback_base_url' => env('PAYZONE_CALLBACK_BASE_URL'),
        // How long an unpaid card purchase holds its stock before it is released.
        'expire_after_minutes' => (int) env('PAYZONE_EXPIRE_AFTER_MINUTES', 60),
    ]
    // | Google Analytics (GA4)
    // |--------------------------------------------------------------------------
    // |
    // | Hirfaty's own property is the default, so production tracks without any
    // | .env entry. The tag is never printed on the staging host, and an empty
    // | GOOGLE_ANALYTICS_ID= (as in .env.example, for local machines) switches
    // | it off — local and staging visits would otherwise be counted as real
    // | traffic. See resources/views/partials/analytics.blade.php.
    // |
    ,

    'google_analytics' => [
        'measurement_id' => env('GOOGLE_ANALYTICS_ID', 'G-GJ98MN1C4R'),
    ],

];
