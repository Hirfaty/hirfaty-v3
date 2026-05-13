<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>إعادة تعيين كلمة المرور — حرفتي</title>
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
        .cta-wrap { margin: 36px 0; }
        .cta-btn { display: inline-block; background-color: #C1522A; color: #FAF7F2 !important; text-decoration: none; font-size: 14px; padding: 16px 36px; }
        .expire-note { display: inline-block; background: rgba(193,82,42,0.08); color: #C1522A; font-size: 11px; padding: 6px 12px; margin-top: 8px; }
        .warning-box { background: rgba(28,25,23,0.04); border-right: 3px solid #78716C; padding: 14px 16px; margin: 28px 0; }
        .warning-box p { font-size: 13px; line-height: 1.8; color: rgba(28,25,23,0.65); }
        .fallback { margin: 24px 0; }
        .fallback p { font-size: 11px; color: #78716C; margin-bottom: 6px; }
        .fallback a { font-size: 11px; color: #C1522A; word-break: break-all; }
        .divider { border: none; border-top: 1px solid rgba(28,25,23,0.12); margin: 36px 0; }
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

        <p class="eyebrow">أمان الحساب</p>

        <h1 class="headline">
            إعادة تعيين<br>
            <em>كلمة المرور</em>.
        </h1>

        <p class="body-text">
            مرحباً {{ $user->first_name }}، تلقينا طلباً لإعادة تعيين كلمة المرور
            المرتبطة بحسابك على حرفتي.
            انقر على الزر أدناه لإنشاء كلمة مرور جديدة.
        </p>

        <div class="cta-wrap">
            <a href="{{ $url }}" class="cta-btn">← إعادة تعيين كلمة المرور</a>
        </div>

        <span class="expire-note">⏱ ينتهي هذا الرابط خلال 60 دقيقة</span>

        <div class="warning-box" style="margin-top:28px;">
            <p>
                إذا لم تطلب إعادة تعيين كلمة المرور، تجاهل هذا البريد الإلكتروني.
                ستبقى كلمة مرورك دون تغيير وحسابك آمن.
            </p>
        </div>

        <hr class="divider" />

        <div class="fallback">
            <p>إذا لم يعمل الزر، انسخ هذا الرابط في متصفحك:</p>
            <a href="{{ $url }}">{{ $url }}</a>
        </div>

        <div class="footer">
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