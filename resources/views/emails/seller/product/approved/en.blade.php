<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Product approved — Hirfaty</title>
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
        .product-card { border: 1px solid rgba(28,25,23,0.12); padding: 20px; margin: 28px 0; background: #FAF7F2; }
        .product-name { font-size: 18px; font-weight: 600; color: #1C1917; margin-bottom: 6px; }
        .product-meta { font-size: 12px; color: #78716C; }
        .product-price { font-size: 20px; color: #C1522A; margin-top: 10px; }
        .badge { display: inline-block; background: #D1FAE5; color: #065F46; font-size: 10px; letter-spacing: 0.15em; text-transform: uppercase; padding: 4px 10px; margin-bottom: 24px; }
        .cta-wrap { margin: 36px 0; }
        .cta-btn { display: inline-block; background-color: #C1522A; color: #FAF7F2 !important; text-decoration: none; font-size: 14px; font-family: 'Georgia', serif; letter-spacing: 0.04em; padding: 16px 36px; }
        .divider { border: none; border-top: 1px solid rgba(28,25,23,0.12); margin: 36px 0; }
        .tip-box { background: rgba(193,82,42,0.06); border-left: 3px solid #C1522A; padding: 16px; margin: 24px 0; }
        .tip-box p { font-size: 13px; line-height: 1.7; color: rgba(28,25,23,0.75); }
        .tip-box strong { color: #1C1917; }
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

        <span class="badge">✓ Product approved</span>

        <p class="eyebrow">Great news for your store</p>

        <h1 class="headline">
            Your product is<br>
            <em>now live</em>.
        </h1>

        <p class="body-text">
            Hello {{ $user->first_name }}, your product has been reviewed and approved
            by our team. It is now visible to all buyers on Hirfaty.
        </p>

        <div class="product-card">
            <p class="product-name">{{ $product->name }}</p>
            <p class="product-meta">
                {{ $product->category?->name ?? 'No category' }}
                · {{ $product->store?->store_name }}
            </p>
            @if($product->sale_price)
                <p class="product-price">
                    {{ number_format($product->sale_price, 2) }} MAD
                    <span style="font-size:13px;color:#78716C;text-decoration:line-through;margin-left:6px;">
                        {{ number_format($product->base_price, 2) }} MAD
                    </span>
                </p>
            @else
                <p class="product-price">{{ number_format($product->base_price, 2) }} MAD</p>
            @endif
        </div>

        <div class="cta-wrap">
            <a href="{{ config('app.url') }}/products/{{ $product->slug }}" class="cta-btn">
                View my product online →
            </a>
        </div>

        <hr class="divider" />

        <div class="tip-box">
            <p>
                <strong>Tip:</strong> share your product link on social media
                to maximize visibility. The more views your product gets, the
                higher it ranks in Hirfaty search results.
            </p>
        </div>

        <div class="footer">
            <p>You're receiving this because you're a seller on Hirfaty.</p>
            <p>
                © {{ date('Y') }} Hirfaty — The Moroccan artisan platform.<br>
                <a href="{{ config('app.url') }}/seller/products">My products</a>
                &nbsp;·&nbsp;
                <a href="{{ config('app.url') }}/seller/dashboard">My dashboard</a>
            </p>
        </div>
    </div>
</body>
</html>