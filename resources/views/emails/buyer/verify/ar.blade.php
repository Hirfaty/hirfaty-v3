<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>تحقق من بريدك — حرفتي</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background-color: #F5F0E8;
            font-family: 'Georgia', serif;
            color: #1C1917;
            direction: rtl;
            text-align: right;
            -webkit-font-smoothing: antialiased;
        }
        .wrapper { max-width: 580px; margin: 40px auto; padding: 0 16px 60px; }
        .header { padding: 32px 0 28px; border-bottom: 1px solid rgba(28,25,23,0.12); margin-bottom: 40px; }
        .logo { font-size: 22px; font-weight: 700; color: #1C1917; text-decoration: none; }
        .logo span { color: #C1522A; }
        .eyebrow { font-size: 10px; letter-spacing: 0.1em; text-transform: uppercase; color: #78716C; margin-bottom: 16px; }
        .headline { font-size: 32px; line-height: 1.4; color: #1C1917; margin-bottom: 20px; }
        .headline em { color: #C1522A; font-style: normal; }
        .body-text { font-size: 15px; line-height: 1.9; color: rgba(28,25,23,0.75); margin-bottom: 16px; }
        .cta-wrap { margin: 36px 0; }
        .cta-btn {
            display: inline-block;
            background-color: #C1522A;
            color: #FAF7F2 !important;
            text-decoration: none;
            font-size: 14px;
            font-family: 'Georgia', serif;
            padding: 16px 36px;
        }
        .divider { border: none; border-top: 1px solid rgba(28,25,23,0.12); margin: 36px 0; }
        .steps { margin: 0; padding: 0; list-style: none; }
        .step { display: flex; align-items: flex-start; gap: 14px; margin-bottom: 16px; flex-direction: row-reverse; }
        .step-number {
            flex-shrink: 0; width: 24px; height: 24px;
            border: 1px solid #1C1917;
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; color: #1C1917; margin-top: 2px;
        }
        .step-text { font-size: 13px; line-height: 1.8; color: rgba(28,25,23,0.7); }
        .step-text strong { color: #1C1917; font-weight: 600; }
        .fallback { background-color: rgba(28,25,23,0.04); border-right: 3px solid #C1522A; padding: 14px 16px; margin: 24px 0; }
        .fallback p { font-size: 11px; color: #78716C; margin-bottom: 6px; }
        .fallback a { font-size: 11px; color: #C1522A; word-break: break-all; }
        .expire-note { display: inline-block; background: rgba(193,82,42,0.08); color: #C1522A; font-size: 11px; padding: 6px 12px; margin-top: 8px; }
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

        <p class="eyebrow">التحقق من حساب المشتري</p>

        <h1 class="headline">
            مرحباً {{ $user->first_name }}،<br>
            أهلاً بك في <em>حرفتي</em>.
        </h1>

        <p class="body-text">
            شكراً لتسجيلك في حرفتي. تحقق من بريدك الإلكتروني للوصول إلى
            آلاف المنتجات الحرفية المصنوعة يدوياً من قِبل حرفيين مغاربة.
        </p>

        <div class="cta-wrap">
            <a href="{{ $url }}" class="cta-btn">← التحقق من بريدي الإلكتروني</a>
        </div>

        <span class="expire-note">⏱ ينتهي هذا الرابط خلال 60 دقيقة</span>

        <hr class="divider" />

        <p class="eyebrow" style="margin-bottom: 20px;">ما الذي ينتظرك</p>
        <ul class="steps">
            <li class="step">
                <div class="step-number">1</div>
                <p class="step-text">
                    <strong>اكتشف السوق</strong> — فخار، جلود، نسيج،
                    مجوهرات وأكثر من ذلك بكثير.
                </p>
            </li>
            <li class="step">
                <div class="step-number">2</div>
                <p class="step-text">
                    <strong>اطلب بثقة</strong> — توصيل آمن عبر أمانة
                    في جميع أنحاء المغرب.
                </p>
            </li>
            <li class="step">
                <div class="step-number">3</div>
                <p class="step-text">
                    <strong>تابع طلباتك</strong> — رقم تتبع أمانة
                    يُرسل فور الشحن.
                </p>
            </li>
        </ul>

        <div class="fallback">
            <p>إذا لم يعمل الزر، انسخ هذا الرابط في متصفحك:</p>
            <a href="{{ $url }}">{{ $url }}</a>
        </div>

        <div class="footer">
            <p>إذا لم تقم بإنشاء حساب على حرفتي، تجاهل هذا البريد الإلكتروني.</p>
            <p>
                © {{ date('Y') }} حرفتي — منصة الحرفيين المغاربة.<br>
                <a href="{{ config('app.url') }}/privacy">سياسة الخصوصية</a>
                &nbsp;·&nbsp;
                <a href="{{ config('app.url') }}/terms">شروط الاستخدام</a>
            </p>
        </div>
    </div>
</body>
</html>