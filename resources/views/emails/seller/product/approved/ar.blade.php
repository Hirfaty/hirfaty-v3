<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>تمت الموافقة على المنتج — حرفتي</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-color: #F5F0E8; font-family: 'Georgia', serif; color: #1C1917; direction: rtl; text-align: right; -webkit-font-smoothing: antialiased; }
        .wrapper { max-width: 580px; margin: 40px auto; padding: 0 16px 60px; }
        .header { padding: 32px 0 28px; border-bottom: 1px solid rgba(28,25,23,0.12); margin-bottom: 40px; }
        .logo { font-size: 22px; font-weight: 700; color: #1C1917; text-decoration: none; }
        .logo span { color: #C1522A; }
        .eyebrow { font-size: 10px; letter-spacing: 0.1em; color: #78716C; margin-bottom: 16px; }
        .headline { font-size: 32px; line-height: 1.4; color: #1C1917; margin-bottom: 20px; }
        .headline em { color: #C1522A; font-style: normal; }
        .body-text { font-size: 15px; line-height: 1.9; color: rgba(28,25,23,0.75); margin-bottom: 16px; }
        .product-card { border: 1px solid rgba(28,25,23,0.12); padding: 20px; margin: 28px 0; background: #FAF7F2; }
        .product-name { font-size: 18px; font-weight: 600; color: #1C1917; margin-bottom: 6px; }
        .product-meta { font-size: 12px; color: #78716C; }
        .product-price { font-size: 20px; color: #C1522A; margin-top: 10px; }
        .badge { display: inline-block; background: #D1FAE5; color: #065F46; font-size: 10px; letter-spacing: 0.1em; padding: 4px 10px; margin-bottom: 24px; }
        .cta-wrap { margin: 36px 0; }
        .cta-btn { display: inline-block; background-color: #C1522A; color: #FAF7F2 !important; text-decoration: none; font-size: 14px; font-family: 'Georgia', serif; padding: 16px 36px; }
        .divider { border: none; border-top: 1px solid rgba(28,25,23,0.12); margin: 36px 0; }
        .tip-box { background: rgba(193,82,42,0.06); border-right: 3px solid #C1522A; padding: 16px; margin: 24px 0; }
        .tip-box p { font-size: 13px; line-height: 1.8; color: rgba(28,25,23,0.75); }
        .tip-box strong { color: #1C1917; }
        .footer { border-top: 1px solid rgba(28,25,23,0.12); padding-top: 28px; margin-top: 40px; }
        .footer p { font-size: 11px; color: #A8A29E; line-height: 1.7; margin-bottom: 6px; }
        .footer a { color: #78716C; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <a href="{{ config('app.url') }}" class="logo">حرفتي<span>.</span></a>
        </div>

        <span class="badge">✓ تمت الموافقة على المنتج</span>

        <p class="eyebrow">خبر سار لمتجرك</p>

        <h1 class="headline">
            منتجك أصبح<br>
            <em>متاحاً للعموم</em>.
        </h1>

        <p class="body-text">
            مرحباً {{ $user->first_name }}، تمت مراجعة منتجك والموافقة عليه من قِبل فريقنا.
            أصبح الآن مرئياً لجميع المشترين على حرفتي.
        </p>

        <div class="product-card">
            <p class="product-name">{{ $product->name }}</p>
            <p class="product-meta">
                {{ $product->category?->name ?? 'بدون فئة' }}
                · {{ $product->store?->store_name }}
            </p>
            @if($product->sale_price)
                <p class="product-price">
                    {{ number_format($product->sale_price, 2) }} د.م
                    <span style="font-size:13px;color:#78716C;text-decoration:line-through;margin-right:6px;">
                        {{ number_format($product->base_price, 2) }} د.م
                    </span>
                </p>
            @else
                <p class="product-price">{{ number_format($product->base_price, 2) }} د.م</p>
            @endif
        </div>

        <div class="cta-wrap">
            <a href="{{ config('app.url') }}/products/{{ $product->slug }}" class="cta-btn">
                ← مشاهدة منتجي على الموقع
            </a>
        </div>

        <hr class="divider" />

        <div class="tip-box">
            <p>
                <strong>نصيحة:</strong> شارك رابط منتجك على وسائل التواصل الاجتماعي
                لزيادة وضوحك. كلما زادت المشاهدات، ارتفع ترتيب منتجك في نتائج البحث على حرفتي.
            </p>
        </div>

        <div class="footer">
            <p>تلقيت هذا البريد الإلكتروني لأنك بائع على حرفتي.</p>
            <p>
                © {{ date('Y') }} حرفتي — منصة الحرفيين المغاربة.<br>
                <a href="{{ config('app.url') }}/seller/products">إدارة منتجاتي</a>
                &nbsp;·&nbsp;
                <a href="{{ config('app.url') }}/seller/dashboard">لوحة التحكم</a>
            </p>
        </div>
    </div>
</body>
</html>