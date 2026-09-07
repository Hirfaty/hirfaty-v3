{{--
    Shared shell for transactional emails: the house CSS, header and footer in
    one place.

    The older templates under seller/ and buyer/order-placed/ each carry their
    own copy of this block — three per notification, one per locale. That was
    tolerable at two notifications and is not the pattern to keep extending, so
    new mail composes this instead. Existing templates are left alone rather
    than migrated in passing; they render correctly and rewriting them is not
    this change's job.

    Slots: $title, $dir (rtl for Arabic), $lang, plus the body via {{ $slot }}.
--}}
<!DOCTYPE html>
<html lang="{{ $lang ?? 'fr' }}" dir="{{ $dir ?? 'ltr' }}">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $title }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-color: #F5F0E8; font-family: 'Georgia', serif; color: #1C1917; -webkit-font-smoothing: antialiased; }
        /* Arabic has no Georgia glyphs and must never be italicised — mirrors
           the [lang='ar'] overrides in app.css. */
        body[dir="rtl"] { font-family: 'Tahoma', 'Arial', sans-serif; }
        body[dir="rtl"] em { font-style: normal; }
        .wrapper { max-width: 580px; margin: 40px auto; padding: 0 16px 60px; }
        .header { padding: 32px 0 28px; border-bottom: 1px solid rgba(28,25,23,0.12); margin-bottom: 40px; }
        .logo { font-size: 22px; font-weight: 700; color: #1C1917; text-decoration: none; }
        .logo span { color: #C1522A; }
        .eyebrow { font-size: 10px; letter-spacing: 0.18em; text-transform: uppercase; color: #78716C; margin-bottom: 16px; }
        .headline { font-size: 32px; line-height: 1.2; color: #1C1917; margin-bottom: 20px; }
        .headline em { color: #C1522A; font-style: italic; }
        .body-text { font-size: 15px; line-height: 1.7; color: rgba(28,25,23,0.75); margin-bottom: 16px; }
        .badge { display: inline-block; font-size: 10px; letter-spacing: 0.15em; text-transform: uppercase; padding: 4px 10px; margin-bottom: 24px; }
        .badge-ok { background: #D1FAE5; color: #065F46; }
        .badge-info { background: #FEF3C7; color: #92400E; }
        .cta-wrap { margin: 36px 0; }
        .cta-btn { display: inline-block; background-color: #C1522A; color: #FAF7F2 !important; text-decoration: none; font-size: 14px; font-family: 'Georgia', serif; letter-spacing: 0.04em; padding: 16px 36px; }
        .divider { border: none; border-top: 1px solid rgba(28,25,23,0.12); margin: 36px 0; }
        .tip-box { background: rgba(193,82,42,0.06); border-left: 3px solid #C1522A; padding: 16px; margin: 24px 0; }
        body[dir="rtl"] .tip-box { border-left: none; border-right: 3px solid #C1522A; }
        .tip-box p { font-size: 13px; line-height: 1.7; color: rgba(28,25,23,0.75); }
        .tip-box strong { color: #1C1917; }
        .footer { border-top: 1px solid rgba(28,25,23,0.12); padding-top: 28px; margin-top: 40px; }
        .footer p { font-size: 11px; color: #A8A29E; line-height: 1.7; margin-bottom: 6px; }
        .footer a { color: #78716C; text-decoration: underline; }
    </style>
</head>
<body dir="{{ $dir ?? 'ltr' }}">
    <div class="wrapper">
        <div class="header">
            <a href="{{ config('app.url') }}" class="logo">Hirfaty<span>.</span></a>
        </div>

        {{ $slot }}

        <div class="footer">
            {{ $footer }}
        </div>
    </div>
</body>
</html>
