<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Reset your password — Hirfaty</title>
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
        .cta-wrap { margin: 36px 0; }
        .cta-btn { display: inline-block; background-color: #C1522A; color: #FAF7F2 !important; text-decoration: none; font-size: 14px; font-family: 'Georgia', serif; letter-spacing: 0.04em; padding: 16px 36px; }
        .expire-note { display: inline-block; background: rgba(193,82,42,0.08); color: #C1522A; font-size: 11px; padding: 6px 12px; margin-top: 8px; }
        .warning-box { background: rgba(28,25,23,0.04); border-left: 3px solid #78716C; padding: 14px 16px; margin: 28px 0; }
        .warning-box p { font-size: 13px; line-height: 1.7; color: rgba(28,25,23,0.65); }
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
            <a href="{{ config('app.url') }}" class="logo">Hirfaty<span>.</span></a>
        </div>

        <p class="eyebrow">Account security</p>

        <h1 class="headline">
            Reset your<br>
            <em>password</em>.
        </h1>

        <p class="body-text">
            Hello {{ $user->first_name }}, we received a request to reset the
            password for your Hirfaty account.
            Click the button below to create a new password.
        </p>

        <div class="cta-wrap">
            <a href="{{ $url }}" class="cta-btn">Reset my password →</a>
        </div>

        <span class="expire-note">⏱ This link expires in 60 minutes</span>

        <div class="warning-box" style="margin-top:28px;">
            <p>
                If you didn't request a password reset, ignore this email.
                Your password will remain unchanged and your account stays secure.
            </p>
        </div>

        <hr class="divider" />

        <div class="fallback">
            <p>If the button doesn't work, copy this link into your browser:</p>
            <a href="{{ $url }}">{{ $url }}</a>
        </div>

        <div class="footer">
            <p>
                © {{ date('Y') }} Hirfaty — The Moroccan artisan platform.<br>
                <a href="{{ config('app.url') }}/privacy">Privacy Policy</a>
                &nbsp;·&nbsp;
                <a href="{{ config('app.url') }}/terms">Terms of Service</a>
            </p>
        </div>
    </div>
</body>
</html>