<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>تم إلغاء الطلب — حرفتي</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-color: #F5F0E8; font-family: 'Georgia', serif; color: #1C1917; direction: rtl; text-align: right; -webkit-font-smoothing: antialiased; }
        .wrapper { max-width: 580px; margin: 40px auto; padding: 0 16px 60px; }
        .header { padding: 32px 0 28px; border-bottom: 1px solid rgba(28,25,23,0.12); margin-bottom: 40px; }
        .logo { font-size: 22px; font-weight: 700; color: #1C1917; text-decoration: none; }
        .logo span { color: #C1522A; }
        .eyebrow { font-size: 10px; color: #78716C; margin-bottom: 16px; }
        .headline { font-size: 32px; line-height: 1.4; color: #1C1917; margin-bottom: 20px; }
        .headline em { color: #C1522A; font-style: normal; }
        .body-text { font-size: 15px; line-height: 1.9; color: rgba(28,25,23,0.75); margin-bottom: 16px; }
        .badge { display: inline-block; background: #FEE2E2; color: #991B1B; font-size: 10px; padding: 4px 10px; margin-bottom: 24px; }
        .order-meta { border: 1px solid rgba(28,25,23,0.12); padding: 20px; margin: 24px 0; background: #FAF7F2; }
        .order-meta-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(28,25,23,0.08); font-size: 13px; flex-direction: row-reverse; }
        .order-meta-row:last-child { border-bottom: none; }
        .order-meta-label { color: #78716C; }
        .order-meta-value { color: #1C1917; font-weight: 500; }
        .reason-box { border: 1px solid rgba(153,27,27,0.2); background: rgba(153,27,27,0.05); padding: 16px; margin: 24px 0; }
        .reason-box .label { font-size: 10px; color: #991B1B; margin-bottom: 8px; }
        .reason-box p { font-size: 13px; line-height: 1.8; color: rgba(28,25,23,0.75); }
        .cta-wrap { margin: 32px 0; }
        .cta-btn { display: inline-block; background-color: #C1522A; color: #FAF7F2 !important; text-decoration: none; font-size: 14px; padding: 14px 32px; }
        .divider { border: none; border-top: 1px solid rgba(28,25,23,0.12); margin: 32px 0; }
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

        <span class="badge">✕ تم إلغاء الطلب</span>

        <p class="eyebrow">نعتذر عن ذلك</p>

        <h1 class="headline">
            تم إلغاء<br>
            <em>طلبك</em>.
        </h1>

        <p class="body-text">
            مرحباً {{ $user->first_name }}، قام {{ $order->store->store_name }} بإلغاء
            طلبك {{ $order->reference }}. إذا كنت قد دفعت بالفعل، سيتم استرداد المبلغ.
        </p>

        <div class="order-meta">
            <div class="order-meta-row">
                <span class="order-meta-value">{{ $order->reference }}</span>
                <span class="order-meta-label">رقم الطلب</span>
            </div>
            <div class="order-meta-row">
                <span class="order-meta-value">{{ $order->store->store_name }}</span>
                <span class="order-meta-label">المتجر</span>
            </div>
        </div>

        @if($order->cancellation_reason)
        <div class="reason-box">
            <p class="label">السبب المذكور من طرف البائع</p>
            <p>{{ $order->cancellation_reason }}</p>
        </div>
        @endif

        <div class="cta-wrap">
            <a href="{{ config('app.url') }}/products" class="cta-btn">
                ← متابعة التسوق
            </a>
        </div>

        <hr class="divider" />

        <div class="footer">
            <p>لديك سؤال حول هذا الإلغاء؟ تواصل مع البائع من صفحة طلباتك.</p>
            <p>
                © {{ date('Y') }} حرفتي — منصة الحرفيين المغاربة.<br>
                <a href="{{ config('app.url') }}/orders">طلباتي</a>
                &nbsp;·&nbsp;
                <a href="{{ config('app.url') }}/privacy">سياسة الخصوصية</a>
            </p>
        </div>
    </div>
</body>
</html>
