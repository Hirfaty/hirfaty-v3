<!DOCTYPE html>
{{-- Hands the buyer to Payzone's hosted payment page: a form posting the signed
     payload, submitted on load — the same shape as Payzone's own
     launch_paywall.php. Rendered by Buyer\PaymentController::launch. --}}
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('buyer.payment.redirect_title') }} · Hirfaty</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fbf8f3;
            color: #3b2a1e;
            font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
            text-align: center;
            padding: 16px;
        }
        .spinner {
            width: 28px;
            height: 28px;
            margin: 0 auto 20px;
            border: 3px solid rgba(59, 42, 30, 0.15);
            border-top-color: #3b2a1e;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        p { margin: 0 0 8px; font-size: 15px; }
        small { color: rgba(59, 42, 30, 0.6); }
        button {
            margin-top: 20px;
            padding: 12px 24px;
            border: 0;
            border-radius: 8px;
            background: #3b2a1e;
            color: #fff;
            font-size: 14px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <form id="openPaywall" action="{{ $url }}" method="POST">
        <input type="hidden" name="payload" value="{{ $payload }}">
        <input type="hidden" name="signature" value="{{ $signature }}">

        <div class="spinner" aria-hidden="true"></div>
        <p>{{ __('buyer.payment.redirecting') }}</p>
        <small>{{ __('buyer.payment.redirecting_note') }}</small>

        <noscript>
            <div><button type="submit">{{ __('buyer.payment.continue') }}</button></div>
        </noscript>
    </form>

    <script>
        document.getElementById('openPaywall').submit();
    </script>
</body>
</html>
