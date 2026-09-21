<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Order shipped — Hirfaty</title>
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
        .badge { display: inline-block; background: #D1FAE5; color: #065F46; font-size: 10px; letter-spacing: 0.15em; text-transform: uppercase; padding: 4px 10px; margin-bottom: 24px; }
        .order-meta { border: 1px solid rgba(28,25,23,0.12); padding: 20px; margin: 24px 0; background: #FAF7F2; }
        .order-meta-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(28,25,23,0.08); font-size: 13px; }
        .order-meta-row:last-child { border-bottom: none; }
        .order-meta-label { color: #78716C; }
        .order-meta-value { color: #1C1917; font-weight: 500; }
        .tracking-box { border: 1px solid rgba(193,82,42,0.3); background: rgba(193,82,42,0.06); padding: 20px; margin: 24px 0; text-align: center; }
        .tracking-box .label { font-size: 10px; letter-spacing: 0.15em; text-transform: uppercase; color: #78716C; margin-bottom: 8px; }
        .tracking-box .number { font-size: 22px; font-weight: 700; color: #C1522A; letter-spacing: 0.06em; }
        .store-block { border: 1px solid rgba(28,25,23,0.12); margin: 24px 0; padding: 14px 16px; display: flex; justify-content: space-between; align-items: baseline; gap: 10px; }
        .store-name { font-size: 14px; font-weight: 600; color: #1C1917; }
        .store-ref { font-size: 11px; color: #78716C; }
        .shipping-box { background: rgba(28,25,23,0.04); padding: 16px; margin: 24px 0; }
        .shipping-box p { font-size: 13px; line-height: 1.7; color: rgba(28,25,23,0.7); }
        .shipping-box .label { font-size: 10px; letter-spacing: 0.15em; text-transform: uppercase; color: #78716C; margin-bottom: 8px; }
        .cta-wrap { margin: 32px 0; }
        .cta-btn { display: inline-block; background-color: #C1522A; color: #FAF7F2 !important; text-decoration: none; font-size: 14px; font-family: 'Georgia', serif; letter-spacing: 0.04em; padding: 14px 32px; }
        .steps { margin: 0; padding: 0; list-style: none; }
        .step { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 14px; }
        .step-number { flex-shrink: 0; width: 22px; height: 22px; border: 1px solid #1C1917; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #1C1917; margin-top: 2px; }
        .step-number.done { background: #1C1917; color: #FAF7F2; }
        .step-text { font-size: 13px; line-height: 1.6; color: rgba(28,25,23,0.7); }
        .step-text strong { color: #1C1917; }
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

        @php
            // A shared tracking number usually means one physical parcel:
            // Hirfaty's desk collects the purchase from every workshop it
            // touched and hands the whole thing over for delivery as one job
            // (see Admin\OrderController). Listed in case the group's
            // history carries more than one.
            $trackingNumbers = $orders->pluck('tracking_number')->filter()->unique()->values();
        @endphp

        <span class="badge">🚚 Order shipped</span>

        <p class="eyebrow">On its way to you</p>

        <h1 class="headline">
            Your order is<br>
            <em>on its way</em>.
        </h1>

        <p class="body-text">
            @if($orders->count() > 1)
                Hello {{ $user->first_name }}, your order {{ $reference }} is out
                for delivery — each of the {{ $orders->count() }} workshops it
                involves has prepared their share.
            @else
                Hello {{ $user->first_name }}, your order {{ $reference }} from
                {{ $orders->first()->store->store_name }} is out for delivery.
            @endif
        </p>

        @if($trackingNumbers->count() === 1)
        <div class="tracking-box">
            <p class="label">Tracking number</p>
            <p class="number">{{ $trackingNumbers->first() }}</p>
        </div>
        @endif

        <div class="order-meta">
            <div class="order-meta-row">
                <span class="order-meta-label">Order number</span>
                <span class="order-meta-value">{{ $reference }}</span>
            </div>
            <div class="order-meta-row">
                <span class="order-meta-label">Delivery to</span>
                <span class="order-meta-value">{{ $orders->first()->shipping_address }}</span>
            </div>
        </div>

        @if($orders->count() > 1)
        {{-- One block per workshop, so a grouped parcel still reads as one
             purchase. --}}
        @foreach($orders as $order)
        <div class="store-block">
            <span class="store-name">{{ $order->store->store_name }}</span>
            <span class="store-ref">{{ $order->reference }}{{ $order->tracking_number && $trackingNumbers->count() > 1 ? ' · '.$order->tracking_number : '' }}</span>
        </div>
        @endforeach
        @endif

        <div class="shipping-box">
            <p class="label">Delivery</p>
            <p>
                Delivery usually takes 3 to 7 days anywhere in Morocco.
                Keep your phone nearby — the courier will call you before
                dropping the parcel off.
            </p>
        </div>

        <div class="cta-wrap">
            <a href="{{ config('app.url') }}/orders" class="cta-btn">
                Track my order →
            </a>
        </div>

        <hr class="divider" />

        <p class="eyebrow" style="margin-bottom:16px;">Where your order stands</p>
        <ul class="steps">
            <li class="step">
                <div class="step-number done">✓</div>
                <p class="step-text"><strong>Confirmed</strong> by {{ $orders->count() > 1 ? 'the workshops' : 'the workshop' }}.</p>
            </li>
            <li class="step">
                <div class="step-number done">✓</div>
                <p class="step-text"><strong>Out for delivery</strong>.</p>
            </li>
            <li class="step">
                <div class="step-number">3</div>
                <p class="step-text"><strong>On its way</strong> to the address provided.</p>
            </li>
        </ul>

        <div class="footer">
            <p>Have a question? Contact us from your orders page.</p>
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
