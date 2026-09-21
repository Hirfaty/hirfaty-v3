<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>تم توصيل الطلب — حرفتي</title>
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
        .badge { display: inline-block; background: #D1FAE5; color: #065F46; font-size: 10px; padding: 4px 10px; margin-bottom: 24px; }
        .order-meta { border: 1px solid rgba(28,25,23,0.12); padding: 20px; margin: 24px 0; background: #FAF7F2; }
        .order-meta-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(28,25,23,0.08); font-size: 13px; flex-direction: row-reverse; }
        .order-meta-row:last-child { border-bottom: none; }
        .order-meta-label { color: #78716C; }
        .order-meta-value { color: #1C1917; font-weight: 500; }
        .store-block { border: 1px solid rgba(28,25,23,0.12); margin: 24px 0; }
        .store-head { background: rgba(28,25,23,0.04); padding: 12px 16px; border-bottom: 1px solid rgba(28,25,23,0.1); display: flex; justify-content: space-between; align-items: baseline; gap: 10px; flex-direction: row-reverse; }
        .store-name { font-size: 14px; font-weight: 600; color: #1C1917; }
        .store-ref { font-size: 11px; color: #78716C; }
        .items-table { width: 100%; border-collapse: collapse; }
        .items-table th { font-size: 10px; color: #78716C; text-align: right; padding: 10px 16px; border-bottom: 1px solid rgba(28,25,23,0.1); }
        .items-table td { font-size: 13px; padding: 12px 16px; border-bottom: 1px solid rgba(28,25,23,0.08); color: #1C1917; vertical-align: top; text-align: right; }
        .items-table tr:last-child td { border-bottom: none; }
        .items-table .amount { text-align: left; }
        .cta-wrap { margin: 32px 0; }
        .cta-btn { display: inline-block; background-color: #C1522A; color: #FAF7F2 !important; text-decoration: none; font-size: 14px; padding: 14px 32px; }
        .steps { margin: 0; padding: 0; list-style: none; }
        .step { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 14px; flex-direction: row-reverse; }
        .step-number { flex-shrink: 0; width: 22px; height: 22px; border: 1px solid #1C1917; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #1C1917; margin-top: 2px; }
        .step-number.done { background: #1C1917; color: #FAF7F2; }
        .step-text { font-size: 13px; line-height: 1.8; color: rgba(28,25,23,0.7); }
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
            <a href="{{ config('app.url') }}" class="logo">حرفتي<span>.</span></a>
        </div>

        <span class="badge">📦 تم توصيل الطلب</span>

        <p class="eyebrow">لقد وصل</p>

        <h1 class="headline">
            طلبك وصل<br>
            <em>بسلام</em>.
        </h1>

        <p class="body-text">
            @if($orders->count() > 1)
                مرحباً {{ $user->first_name }}، تم توصيل طلبك {{ $reference }} الذي جمع
                {{ $orders->count() }} ورشات. نتمنى أن ينال إعجابك بقدر ما استمتعنا
                بإيصاله إليك.
            @else
                مرحباً {{ $user->first_name }}، تم توصيل طلبك {{ $reference }} من
                {{ $orders->first()->store->store_name }}. نتمنى أن ينال إعجابك بقدر ما استمتعنا
                بإيصاله إليك.
            @endif
        </p>

        <div class="order-meta">
            <div class="order-meta-row">
                <span class="order-meta-value">{{ $reference }}</span>
                <span class="order-meta-label">رقم الطلب</span>
            </div>
            <div class="order-meta-row">
                <span class="order-meta-value">{{ optional($orders->first()->delivered_at)->format('d/m/Y') }}</span>
                <span class="order-meta-label">تاريخ التوصيل</span>
            </div>
        </div>

        {{-- كتلة لكل ورشة، لتبقى الطلبية المُسلَّمة من عدة بائعين مقروءة
             كعملية شراء واحدة. --}}
        @foreach($orders as $order)
        <div class="store-block">
            <div class="store-head">
                <span class="store-ref">{{ $order->reference }}</span>
                <span class="store-name">{{ $order->store->store_name }}</span>
            </div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="text-align:left">السعر</th>
                        <th style="text-align:center">الكمية</th>
                        <th>المنتج</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->items as $item)
                    <tr>
                        <td class="amount" style="text-align:left">{{ number_format($item->subtotal, 2) }} د.م TTC</td>
                        <td style="text-align:center;color:#78716C">× {{ $item->quantity }}</td>
                        <td>{{ $item->product->name }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endforeach

        {{-- تُضاف التقييمات من صفحة الطلب نفسه
             (buyer.orders.reviews.store): الطلب من ورشة واحدة يحيل مباشرة
             إليها، أما الطلب المجمّع فيحيل إلى قائمة الطلبات. --}}
        <div class="cta-wrap">
            <a href="{{ config('app.url') }}/orders{{ $orders->count() === 1 ? '/'.$orders->first()->reference : '' }}" class="cta-btn">
                ← أضف تقييمك
            </a>
        </div>

        <hr class="divider" />

        <p class="eyebrow" style="margin-bottom:16px;">أين وصل طلبك</p>
        <ul class="steps">
            <li class="step">
                <div class="step-number done">✓</div>
                <p class="step-text"><strong>تم التأكيد</strong> من طرف {{ $orders->count() > 1 ? 'الورشات' : 'الورشة' }}.</p>
            </li>
            <li class="step">
                <div class="step-number done">✓</div>
                <p class="step-text"><strong>خرج للتوصيل</strong>.</p>
            </li>
            <li class="step">
                <div class="step-number done">✓</div>
                <p class="step-text"><strong>تم التوصيل</strong> إلى العنوان المحدد.</p>
            </li>
        </ul>

        <div class="footer">
            <p>هل واجهت مشكلة في هذا الطلب؟ تواصل معنا من صفحة طلباتك.</p>
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
