<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <title>تم إلغاء الطلب — حرفتي</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-color: #F5F0E8; font-family: 'Georgia', serif; color: #1C1917; direction: rtl; text-align: right; }
        .wrapper { max-width: 580px; margin: 40px auto; padding: 0 16px 60px; }
        .header { padding: 32px 0 28px; border-bottom: 1px solid rgba(28,25,23,0.12); margin-bottom: 40px; }
        .logo { font-size: 22px; font-weight: 700; color: #1C1917; text-decoration: none; }
        .logo span { color: #C1522A; }
        .eyebrow { font-size: 10px; color: #78716C; margin-bottom: 16px; }
        .headline { font-size: 32px; line-height: 1.4; color: #1C1917; margin-bottom: 20px; }
        .body-text { font-size: 15px; line-height: 1.9; color: rgba(28,25,23,0.75); margin-bottom: 16px; }
        .badge { display: inline-block; background: #FEE2E2; color: #991B1B; font-size: 10px; padding: 4px 10px; margin-bottom: 24px; }
        .order-meta { border: 1px solid rgba(28,25,23,0.12); padding: 20px; margin: 24px 0; background: #FAF7F2; }
        .order-meta-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(28,25,23,0.08); font-size: 13px; flex-direction: row-reverse; }
        .order-meta-row:last-child { border-bottom: none; }
        .order-meta-label { color: #78716C; }
        .order-meta-value { color: #1C1917; font-weight: 500; }
        .reason-box { background: #FEF2F2; border-right: 3px solid #EF4444; padding: 16px; margin: 24px 0; }
        .reason-box .label { font-size: 10px; color: #EF4444; margin-bottom: 8px; font-weight: 600; }
        .reason-box p { font-size: 13px; line-height: 1.8; color: #7F1D1D; }
        .tip-box { background: rgba(28,25,23,0.04); border-right: 3px solid #78716C; padding: 14px 16px; margin: 24px 0; }
        .tip-box p { font-size: 13px; line-height: 1.8; color: rgba(28,25,23,0.7); }
        .cta-btn { display: inline-block; background-color: #C1522A; color: #FAF7F2 !important; text-decoration: none; font-size: 14px; padding: 14px 32px; margin: 28px 0; }
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
        <p class="eyebrow">تحديث طلبك</p>
        <h1 class="headline">تم إلغاء طلبك.</h1>

        <p class="body-text">
            مرحباً {{ $user->first_name }}، نعلمك بأن طلبك رقم
            #{{ $order->id }} من <strong>{{ $order->store->store_name }}</strong>
            قد تم إلغاؤه من قِبل البائع.
        </p>

        <div class="order-meta">
            <div class="order-meta-row"><span class="order-meta-value">#{{ $order->id }}</span><span class="order-meta-label">رقم الطلب</span></div>
            <div class="order-meta-row"><span class="order-meta-value">{{ $order->store->store_name }}</span><span class="order-meta-label">المتجر</span></div>
            <div class="order-meta-row"><span class="order-meta-value">{{ number_format($order->total_amount, 2) }} د.م</span><span class="order-meta-label">المبلغ</span></div>
        </div>

        <div class="reason-box">
            <p class="label">سبب الإلغاء</p>
            <p>{{ $order->cancellation_reason }}</p>
        </div>

        <div class="tip-box">
            <p>إذا تم خصم المبلغ من حسابك، سيتم رد المبلغ خلال 3 إلى 5 أيام عمل. يمكنك إيجاد منتجات مشابهة لدى حرفيين آخرين على حرفتي.</p>
        </div>

        <a href="{{ config('app.url') }}/products" class="cta-btn">← اكتشف حرفيين آخرين</a>

        <div class="footer">
            <p>© {{ date('Y') }} حرفتي · <a href="{{ config('app.url') }}/orders">طلباتي</a></p>
        </div>
    </div>
</body>
</html>