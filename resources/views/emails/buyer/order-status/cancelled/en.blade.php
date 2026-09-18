<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Order cancelled — Hirfaty</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-color: #F5F0E8; font-family: 'Georgia', serif; color: #1C1917; -webkit-font-smoothing: antialiased; }
        .wrapper { max-width: 580px; margin: 40px auto; padding: 0 16px 60px; }
        .header { padding: 32px 0 28px; border-bottom: 1px solid rgba(28,25,23,0.12); margin-bottom: 40px; }
        .logo { font-size: 22px; font-weight: 700; color: #1C1917; text-decoration: none; }
        .logo span { color: #C1522A; }
        .eyebrow { font-size: 10px; letter-spacing: 0.18em; text-transform: uppercase; color: #78716C; margin-bottom: 16px; }
        .headline { font-size: 32px; line-height: 1.2; color: #1C1917; margin-bottom: 20px; }
        .headline em { color: #C1522A; font-style: italic; }
        .body-text { font-size: 15px; line-height: 1.7; color: rgba(28,25,23,0.75); margin-bottom: 16px; }
        .badge { display: inline-block; background: #FEE2E2; color: #991B1B; font-size: 10px; letter-spacing: 0.15em; text-transform: uppercase; padding: 4px 10px; margin-bottom: 24px; }
        .order-meta { border: 1px solid rgba(28,25,23,0.12); padding: 20px; margin: 24px 0; background: #FAF7F2; }
        .order-meta-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(28,25,23,0.08); font-size: 13px; }
        .order-meta-row:last-child { border-bottom: none; }
        .order-meta-label { color: #78716C; }
        .order-meta-value { color: #1C1917; font-weight: 500; }
        .reason-box { border: 1px solid rgba(153,27,27,0.2); background: rgba(153,27,27,0.05); padding: 16px; margin: 24px 0; }
        .reason-box .label { font-size: 10px; letter-spacing: 0.15em; text-transform: uppercase; color: #991B1B; margin-bottom: 8px; }
        .reason-box p { font-size: 13px; line-height: 1.7; color: rgba(28,25,23,0.75); }
        .cta-wrap { margin: 32px 0; }
        .cta-btn { display: inline-block; background-color: #C1522A; color: #FAF7F2 !important; text-decoration: none; font-size: 14px; font-family: 'Georgia', serif; letter-spacing: 0.04em; padding: 14px 32px; }
        .divider { border: none; border-top: 1px solid rgba(28,25,23,0.12); margin: 32px 0; }
        .footer { border-top: 1px solid rgba(28,25,23,0.12); padding-top: 28px; margin-top: 40px; }
        .footer p { font-size: 11px; color: #A8A29E; line-height: 1.7; margin-bottom: 6px; }
        .footer a { color: #78716C; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <a href="{{ config('app.url') }}" class="logo">Hirfaty<span>.</span></a>
        </div>

        <span class="badge">✕ Order cancelled</span>

        <p class="eyebrow">We're sorry</p>

        <h1 class="headline">
            Your order has been<br>
            <em>cancelled</em>.
        </h1>

        <p class="body-text">
            Hello {{ $user->first_name }}, {{ $order->store->store_name }} has cancelled
            your order {{ $order->reference }}. If you already paid, you'll be refunded.
        </p>

        <div class="order-meta">
            <div class="order-meta-row">
                <span class="order-meta-label">Order number</span>
                <span class="order-meta-value">{{ $order->reference }}</span>
            </div>
            <div class="order-meta-row">
                <span class="order-meta-label">Store</span>
                <span class="order-meta-value">{{ $order->store->store_name }}</span>
            </div>
        </div>

        @if($order->cancellation_reason)
        <div class="reason-box">
            <p class="label">Reason given by the seller</p>
            <p>{{ $order->cancellation_reason }}</p>
        </div>
        @endif

        <div class="cta-wrap">
            <a href="{{ config('app.url') }}/products" class="cta-btn">
                Continue shopping →
            </a>
        </div>

        <hr class="divider" />

        <div class="footer">
            <p>Questions about this cancellation? Contact the seller from your orders page.</p>
            <p>
                © {{ date('Y') }} Hirfaty — The Moroccan artisan platform.<br>
                <a href="{{ config('app.url') }}/orders">My orders</a>
                &nbsp;·&nbsp;
                <a href="{{ config('app.url') }}/privacy">Privacy Policy</a>
            </p>
        </div>
    </div>
</body>
</html>
