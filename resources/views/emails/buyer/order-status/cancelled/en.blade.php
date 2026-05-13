<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8" />
    <title>Order cancelled — Hirfaty</title>
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
        .badge { display: inline-block; background: #FEE2E2; color: #991B1B; font-size: 10px; letter-spacing: 0.15em; text-transform: uppercase; padding: 4px 10px; margin-bottom: 24px; }
        .order-meta { border: 1px solid rgba(28,25,23,0.12); padding: 20px; margin: 24px 0; background: #FAF7F2; }
        .order-meta-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(28,25,23,0.08); font-size: 13px; }
        .order-meta-row:last-child { border-bottom: none; }
        .order-meta-label { color: #78716C; }
        .order-meta-value { color: #1C1917; font-weight: 500; }
        .reason-box { background: #FEF2F2; border-left: 3px solid #EF4444; padding: 16px; margin: 24px 0; }
        .reason-box .label { font-size: 10px; letter-spacing: 0.15em; text-transform: uppercase; color: #EF4444; margin-bottom: 8px; font-weight: 600; }
        .reason-box p { font-size: 13px; line-height: 1.7; color: #7F1D1D; }
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

        <span class="badge">✕ Order cancelled</span>

        <p class="eyebrow">Order update</p>

        <h1 class="headline">
            Your order<br>has been cancelled.
        </h1>

        <p class="body-text">
            Hello {{ $user->first_name }}, we wanted to let you know that your order
            #{{ $order->id }} from <strong>{{ $order->store->store_name }}</strong>
            has been cancelled by the seller.
        </p>

        <div class="order-meta">
            <div class="order-meta-row">
                <span class="order-meta-label">Order</span>
                <span class="order-meta-value">#{{ $order->id }}</span>
            </div>
            <div class="order-meta-row">
                <span class="order-meta-label">Store</span>
                <span class="order-meta-value">{{ $order->store->store_name }}</span>
            </div>
            <div class="order-meta-row">
                <span class="order-meta-label">Amount</span>
                <span class="order-meta-value">{{ number_format($order->total_amount, 2) }} MAD</span>
            </div>
        </div>

        <div class="reason-box">
            <p class="label">Cancellation reason</p>
            <p>{{ $order->cancellation_reason }}</p>
        </div>

        <div class="tip-box">
            <p>
                If you were charged, your refund will be processed within 3 to 5 business days.
                You can find similar products from other artisans on Hirfaty.
            </p>
        </div>

        <a href="{{ config('app.url') }}/products" class="cta-btn">
            Discover other artisans →
        </a>

        <div class="footer">
            <p>Have questions? Contact our support team.</p>
            <p>
                © {{ date('Y') }} Hirfaty — The Moroccan artisan marketplace.<br>
                <a href="{{ config('app.url') }}/orders">My orders</a>
            </p>
        </div>
    </div>
</body>
</html>