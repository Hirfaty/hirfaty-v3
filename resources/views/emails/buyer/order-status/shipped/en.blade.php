<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8" />
    <title>Order shipped — Hirfaty</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-color: #F5F0E8; font-family: 'Georgia', serif; color: #1C1917; }
        .wrapper { max-width: 580px; margin: 40px auto; padding: 0 16px 60px; }
        .header { padding: 32px 0 28px; border-bottom: 1px solid rgba(28,25,23,0.12); margin-bottom: 40px; }
        .logo { font-size: 22px; font-weight: 700; color: #1C1917; text-decoration: none; }
        .logo span { color: #C1522A; }
        .eyebrow { font-size: 10px; letter-spacing: 0.18em; text-transform: uppercase; color: #78716C; margin-bottom: 16px; }
        .headline { font-size: 32px; line-height: 1.2; color: #1C1917; margin-bottom: 20px; }
        .body-text { font-size: 15px; line-height: 1.7; color: rgba(28,25,23,0.75); margin-bottom: 16px; }
        .badge { display: inline-block; background: #E0F2FE; color: #0369A1; font-size: 10px; letter-spacing: 0.15em; text-transform: uppercase; padding: 4px 10px; margin-bottom: 24px; }
        .order-meta { border: 1px solid rgba(28,25,23,0.12); padding: 20px; margin: 24px 0; background: #FAF7F2; }
        .order-meta-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(28,25,23,0.08); font-size: 13px; }
        .order-meta-row:last-child { border-bottom: none; }
        .order-meta-label { color: #78716C; }
        .order-meta-value { color: #1C1917; font-weight: 500; }
        .tracking-box { background: #F0F9FF; border-left: 3px solid #0EA5E9; padding: 16px; margin: 24px 0; }
        .tracking-box .label { font-size: 10px; letter-spacing: 0.15em; text-transform: uppercase; color: #0284C7; margin-bottom: 8px; font-weight: 600; }
        .tracking-box .number { font-size: 18px; font-weight: 700; color: #0C4A6E; letter-spacing: 0.05em; font-family: monospace; }
        .tip-box { background: rgba(28,25,23,0.04); border-left: 3px solid #78716C; padding: 14px 16px; margin: 24px 0; }
        .tip-box p { font-size: 13px; line-height: 1.7; color: rgba(28,25,23,0.7); }
        .cta-btn { display: inline-block; background-color: #C1522A; color: #FAF7F2 !important; text-decoration: none; font-size: 14px; font-family: 'Georgia', serif; letter-spacing: 0.04em; padding: 14px 32px; margin: 28px 0; }
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

        <span class="badge">🚚 Order shipped</span>

        <p class="eyebrow">Your package is on its way</p>

        <h1 class="headline">
            Your order<br>has been shipped.
        </h1>

        <p class="body-text">
            Hello {{ $user->first_name }}, great news! Your order
            #{{ $order->id }} from <strong>{{ $order->store->store_name }}</strong>
            has been handed over to Amana and is currently in transit.
        </p>

        <div class="tracking-box">
            <p class="label">Amana tracking number</p>
            <p class="number">{{ $order->tracking_number }}</p>
        </div>

        <div class="order-meta">
            <div class="order-meta-row">
                <span class="order-meta-label">Store</span>
                <span class="order-meta-value">{{ $order->store->store_name }}</span>
            </div>
            <div class="order-meta-row">
                <span class="order-meta-label">Destination</span>
                <span class="order-meta-value">{{ $order->city->name }}</span>
            </div>
        </div>

        <div class="tip-box">
            <p>
                You can track your delivery directly on the Amana website or via your Hirfaty account.
            </p>
        </div>

        <a href="https://www.amana.ma" class="cta-btn">
            Track on Amana →
        </a>

        <div class="footer">
            <p>Need help? Our support team is here for you.</p>
            <p>
                © {{ date('Y') }} Hirfaty — The Moroccan artisan marketplace.<br>
                <a href="{{ config('app.url') }}/orders">My orders</a>
            </p>
        </div>
    </div>
</body>
</html>
