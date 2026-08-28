<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <title>تأكيد الطلب — حرفتي</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-color: #F5F0E8; font-family: 'Arial', sans-serif; color: #1C1917; text-align: right; }
        .wrapper { max-width: 580px; margin: 40px auto; padding: 0 16px 60px; }
        .header { padding: 32px 0 28px; border-bottom: 1px solid rgba(28,25,23,0.12); margin-bottom: 40px; }
        .logo { font-size: 22px; font-weight: 700; color: #1C1917; text-decoration: none; }
        .logo span { color: #C1522A; }
        .eyebrow { font-size: 11px; letter-spacing: 0.05em; color: #78716C; margin-bottom: 16px; font-weight: bold; }
        .headline { font-size: 32px; line-height: 1.2; color: #1C1917; margin-bottom: 20px; }
        .body-text { font-size: 16px; line-height: 1.7; color: rgba(28,25,23,0.75); margin-bottom: 16px; }
        .badge { display: inline-block; background: #ECFDF5; color: #065F46; font-size: 11px; letter-spacing: 0.05em; padding: 4px 10px; margin-bottom: 24px; border-radius: 2px; }
        .order-meta { border: 1px solid rgba(28,25,23,0.12); padding: 20px; margin: 24px 0; background: #FAF7F2; }
        .order-meta-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(28,25,23,0.08); font-size: 14px; }
        .order-meta-row:last-child { border-bottom: none; }
        .order-meta-label { color: #78716C; }
        .order-meta-value { color: #1C1917; font-weight: 500; }
        .tip-box { background: rgba(28,25,23,0.04); border-right: 3px solid #78716C; padding: 14px 16px; margin: 24px 0; }
        .tip-box p { font-size: 14px; line-height: 1.7; color: rgba(28,25,23,0.7); }
        .cta-btn { display: inline-block; background-color: #C1522A; color: #FAF7F2 !important; text-decoration: none; font-size: 15px; padding: 14px 32px; margin: 28px 0; border-radius: 0; }
        .footer { border-top: 1px solid rgba(28,25,23,0.12); padding-top: 28px; margin-top: 40px; }
        .footer p { font-size: 12px; color: #A8A29E; line-height: 1.7; margin-bottom: 6px; }
        .footer a { color: #78716C; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <a href="{{ config('app.url') }}" class="logo">حرفتي<span>.</span></a>
        </div>

        <span class="badge">✓ تم تأكيد الطلب</span>

        <p class="eyebrow">أخبار سارة</p>

        <h1 class="headline">
            لقد تم تأكيد<br>طلبك بنجاح.
        </h1>

        <p class="body-text">
            مرحباً {{ $user->first_name }}، يسعدنا إبلاغك بأن طلبك رقم
            #{{ $order->id }} من متجر <strong>{{ $order->store->store_name }}</strong>
            قد تمت الموافقة عليه من قبل الحرفي.
        </p>

        <div class="order-meta">
            <div class="order-meta-row">
                <span class="order-meta-label">الطلب</span>
                <span class="order-meta-value">#{{ $order->id }}</span>
            </div>
            <div class="order-meta-row">
                <span class="order-meta-label">المتجر</span>
                <span class="order-meta-value">{{ $order->store->store_name }}</span>
            </div>
            <div class="order-meta-row">
                <span class="order-meta-label">المبلغ الإجمالي</span>
                <span class="order-meta-value">{{ number_format($order->total_amount, 2) }} درهم</span>
            </div>
        </div>

        <div class="tip-box">
            <p>
                الحرفي يقوم الآن بتجهيز طردك. ستصلك رسالة أخرى فور شحن الطلب عبر أمانة.
            </p>
        </div>

        <a href="{{ config('app.url') }}/orders" class="cta-btn">
            تتبع طلبي ←
        </a>

        <div class="footer">
            <p>لديك أي استفسار؟ فريقنا دائماً في خدمتكم.</p>
            <p>
                © {{ date('Y') }} حرفتي — منصة الحرفيين المغاربة.<br>
                <a href="{{ config('app.url') }}/orders">طلباتي</a>
            </p>
        </div>
    </div>
</body>
</html>
