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

    /*
    | `newsletter_segment_id` is the Resend segment every footer signup is
    | added to (ResendContactService). Leave it — or the key — blank and the
    | sync is skipped: the signup itself never depends on Resend.
    */
    'resend' => [
        'key' => env('RESEND_API_KEY'),
        'newsletter_segment_id' => env('RESEND_NEWSLETTER_SEGMENT_ID'),
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
        /*
         * The REST API (refunds), signed with the API caller credentials
         * Payzone's account manager hands out — separate from the paywall's
         * secret key. Without them card refunds stay manual. The host defaults
         * to the paywall's (payment-sandbox.payzone.ma in the sandbox).
         */
        'api_url' => env('PAYZONE_API_URL'),
        'api_caller_name' => env('PAYZONE_API_CALLER_NAME'),
        'api_caller_password' => env('PAYZONE_API_CALLER_PASSWORD'),
        // How long an unpaid card purchase holds its stock before it is released.
        'expire_after_minutes' => (int) env('PAYZONE_EXPIRE_AFTER_MINUTES', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | PayPal
    |--------------------------------------------------------------------------
    |
    | Offered at checkout once the client id and secret are set (see
    | PayPalClient::isConfigured). `mode` picks the API host: `sandbox` for
    | api-m.sandbox.paypal.com, `live` for api-m.paypal.com — the credentials
    | of one never work on the other.
    |
    | The webhook is registered in PayPal's developer dashboard, pointing at
    | /payments/paypal/webhook; its id is what lets the app check that an event
    | really came from PayPal. Without it the return from PayPal still places
    | the order, but a delayed (PENDING) payment never settles and a refund
    | made from PayPal is never recorded.
    |
    | PayPal cannot charge MAD. A buyer reading a currency PayPal takes is
    | charged in it, at the rate stored on their order at checkout; everyone
    | else — dirhams included — is charged in `currency`.
    |
    */

    'paypal' => [
        'mode' => env('PAYPAL_MODE', 'sandbox'),
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        'currency' => env('PAYPAL_CURRENCY', 'EUR'),
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

    /*
    |--------------------------------------------------------------------------
    | Cathedis (delivery in Morocco)
    |--------------------------------------------------------------------------
    |
    | The tracker API behind Cathedis's client space. The city list
    | (`cathedis:sync-cities`) is public and needs no credentials; creating
    | deliveries logs in with the client account Cathedis opened for Hirfaty.
    | api.cathedis.delivery and app.cathedis.delivery answer the same.
    |
    | The pickup point is optional: without it a pickup request goes to the
    | account's own address; with it (an id from Cathedis's client space),
    | Cathedis is sent to that point.
    |
    */

    'cathedis' => [
        'url' => env('CATHEDIS_API_URL', 'https://api.cathedis.delivery'),
        'username' => env('CATHEDIS_USERNAME'),
        'password' => env('CATHEDIS_PASSWORD'),
        'pickup_point_id' => env('CATHEDIS_PICKUP_POINT_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | DHL Express (delivery abroad) — MyDHL API
    |--------------------------------------------------------------------------
    |
    | Basic auth with the MyDHL API username and password DHL sends by email,
    | plus the DHL Express account number every shipment is billed to. The
    | test environment (…/mydhlapi/test) and production (…/mydhlapi) have
    | different usernames and passwords; the account number is the same.
    |
    | The shipper is Hirfaty's desk — it gathers every workshop's piece and
    | hands DHL one parcel per purchase — so its address is what DHL prints on
    | the label and where its courier comes to collect.
    |
    */

    'dhl_express' => [
        'url' => env('DHL_EXPRESS_URL', 'https://express.api.dhl.com/mydhlapi/test'),
        'username' => env('DHL_EXPRESS_USERNAME'),
        'password' => env('DHL_EXPRESS_PASSWORD'),
        'account_number' => env('DHL_EXPRESS_ACCOUNT_NUMBER'),
        // Express Worldwide (goods). A different product only on DHL's advice.
        'product_code' => env('DHL_EXPRESS_PRODUCT_CODE', 'P'),
        // Price delivery abroad at checkout with DHL's own rates for the
        // account (DhlRateService), the rate card being only the fallback.
        'live_rates' => (bool) env('DHL_EXPRESS_LIVE_RATES', true),
        'shipper' => [
            'company' => env('DHL_EXPRESS_SHIPPER_COMPANY', 'Hirfaty'),
            'name' => env('DHL_EXPRESS_SHIPPER_NAME'),
            'phone' => env('DHL_EXPRESS_SHIPPER_PHONE'),
            'email' => env('DHL_EXPRESS_SHIPPER_EMAIL'),
            'address' => env('DHL_EXPRESS_SHIPPER_ADDRESS'),
            'city' => env('DHL_EXPRESS_SHIPPER_CITY', 'Casablanca'),
            'postal_code' => env('DHL_EXPRESS_SHIPPER_POSTAL_CODE'),
            'country' => env('DHL_EXPRESS_SHIPPER_COUNTRY', 'MA'),
        ],
        // When the desk's door closes, and where the courier asks for the parcel.
        'pickup_close_time' => env('DHL_EXPRESS_PICKUP_CLOSE_TIME', '18:00'),
        'pickup_location' => env('DHL_EXPRESS_PICKUP_LOCATION', 'reception'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS — one-time codes and order updates for phone sign-ins
    |--------------------------------------------------------------------------
    |
    | `log` writes each message to the log instead of sending it: the default,
    | and what the tests use. `messagepit` delivers to the local Messagepit
    | container (docker-compose.yml), which catches local mail too — texts are
    | in its SMS tab at http://localhost:8025. Production needs
    | `twilio`, or nobody signing in by phone ever receives their code —
    | SmsService refuses phone sign-in outright there rather than pretend it
    | sent one.
    |
    | `twilio`: the account SID and auth token from the Twilio console, and the
    | sender — a Twilio number, or an alphanumeric sender ID ("Hirfaty") where
    | the destination country allows one (Morocco does, once registered).
    |
    */

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'twilio' => [
            'sid' => env('TWILIO_SID'),
            'token' => env('TWILIO_AUTH_TOKEN'),
            'from' => env('TWILIO_FROM'),
        ],
        'messagepit' => [
            'url' => env('MESSAGEPIT_URL', 'http://127.0.0.1:8200'),
            // Only checked when the container runs with MP_TWILIO_AUTH_TOKEN.
            'token' => env('MESSAGEPIT_TOKEN'),
            'from' => env('MESSAGEPIT_FROM', 'Hirfaty'),
        ],
    ],

];
