@php
    /*
     * The 6-digit code email — one template for both purposes and all three
     * locales, copy from lang/<locale>/emails.php.
     *
     * `$copy` selects the block: 'registration_code' when the address has no
     * account yet, 'login_code' when it does and the visitor chose a code over
     * their password. One template rather than two because the layout is
     * identical and only the words differ — the same reasoning that collapsed
     * six verification templates into emails/verify.blade.php, and the same
     * drift it was collapsed to avoid.
     *
     * Modelled on emails/verify.blade.php rather than the emails.layout
     * component: tables with inline styles, because Outlook renders mail
     * through Word and supports neither flexbox nor `gap`. That matters more
     * here than anywhere else in the app — the code itself is a wide,
     * letter-spaced run of digits inside a bordered box, and a div-based
     * version of that collapses into unreadable text in Outlook.
     *
     * $code, $locale, $expiresInMinutes, $copy
     */
    $t = fn (string $key, array $replace = []) => __("emails.{$copy}.{$key}", $replace, $locale);

    $rtl = $locale === 'ar';
    $dir = $rtl ? 'rtl' : 'ltr';
    $align = $rtl ? 'right' : 'left';

    // Georgia, not the brand's Didot: mail clients don't reliably load
    // webfonts, and a websafe serif keeps the editorial tone everywhere.
    // Arabic has no Georgia glyphs, so it gets Tahoma — mirrors the
    // [lang='ar'] overrides in app.css and in emails/layout.blade.php.
    $font = $rtl ? "Tahoma, Arial, sans-serif" : "Georgia, 'Times New Roman', serif";

    // The code is always Latin digits, so it keeps the serif in every locale.
    $codeFont = "Georgia, 'Times New Roman', serif";

    $ink = '#38230C';        // souk-title
    $inkSoft = '#6B5B4B';
    $brown = '#82472A';      // souk-brown
    $terracotta = '#C1522A';
    $paper = '#FFFEFB';      // souk-paper
    $peach = '#FFF1E2';      // souk-peach
    $line = '#E6DAC8';
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $t('subject') }}</title>
</head>
<body style="margin:0; padding:0; width:100%; background-color:{{ $peach }};">

    {{-- Inbox preview line. Hidden in the body, shown next to the subject.
         Carries the code itself: most people can finish the step straight from
         the notification without opening the mail at all. --}}
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; mso-hide:all;">
        {{ $t('preheader', ['code' => $code]) }}
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="background-color:{{ $peach }};">
        <tr>
            <td align="center" style="padding:32px 16px;">

                <table role="presentation" width="580" cellpadding="0" cellspacing="0" border="0"
                       style="width:580px; max-width:100%; background-color:{{ $paper }}; border:1px solid {{ $line }};">

                    {{-- Zellij frise, echoing the site header --}}
                    <tr>
                        <td style="height:6px; line-height:6px; font-size:0;
                                   background-color:{{ $terracotta }};">&nbsp;</td>
                    </tr>

                    {{-- Logo --}}
                    <tr>
                        <td align="{{ $align }}" style="padding:28px 40px 0 40px;">
                            <a href="{{ config('app.url') }}"
                               style="font-family:{{ $font }}; font-size:22px; font-weight:700;
                                      color:{{ $ink }}; text-decoration:none;">
                                Hirfaty<span style="color:{{ $terracotta }};">.</span>
                            </a>
                        </td>
                    </tr>

                    {{-- Eyebrow + headline --}}
                    <tr>
                        <td align="{{ $align }}" style="padding:26px 40px 0 40px;">
                            <p style="margin:0 0 14px 0; font-family:{{ $font }}; font-size:11px;
                                      letter-spacing:0.18em; text-transform:uppercase; color:{{ $brown }};">
                                {{ $t('eyebrow') }}
                            </p>
                            <h1 style="margin:0; font-family:{{ $font }}; font-size:30px; line-height:1.22;
                                       font-weight:400; color:{{ $ink }};">
                                {{ $t('headline') }}
                            </h1>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td align="{{ $align }}" style="padding:18px 40px 0 40px;">
                            <p style="margin:0; font-family:{{ $font }}; font-size:15px; line-height:1.7;
                                      color:{{ $inkSoft }};">
                                {{ $t('body') }}
                            </p>
                        </td>
                    </tr>

                    {{-- The code. Centred in its own bordered box, letter-spaced
                         so the digits are easy to read off and retype. `dir=ltr`
                         explicitly even in Arabic: the digits are a single
                         left-to-right token and must not be reordered. --}}
                    <tr>
                        <td align="center" style="padding:28px 40px 0 40px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0"
                                   style="background-color:{{ $peach }}; border:1px solid {{ $line }};">
                                <tr>
                                    <td align="center" dir="ltr"
                                        style="padding:20px 38px; font-family:{{ $codeFont }};
                                               font-size:36px; line-height:1.1; font-weight:700;
                                               letter-spacing:0.28em; color:{{ $ink }};">
                                        {{ $code }}
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:14px 0 0 0; font-family:{{ $font }}; font-size:12px;
                                      color:{{ $inkSoft }};">
                                {{ $t('expires', ['minutes' => $expiresInMinutes]) }}
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:32px 40px 0 40px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr><td style="height:1px; line-height:1px; font-size:0;
                                               background-color:{{ $line }};">&nbsp;</td></tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td align="{{ $align }}" style="padding:26px 40px 34px 40px;">
                            <p style="margin:0 0 8px 0; font-family:{{ $font }}; font-size:12px;
                                      line-height:1.7; color:{{ $inkSoft }};">
                                {{ $t('ignore') }}
                            </p>
                            <p style="margin:0; font-family:{{ $font }}; font-size:11px;
                                      line-height:1.8; color:#A2937F;">
                                &copy; {{ date('Y') }} Hirfaty — {{ $t('tagline') }}<br>
                                <a href="{{ config('app.url') }}/privacy"
                                   style="color:#A2937F;">{{ $t('privacy') }}</a>
                                &nbsp;·&nbsp;
                                <a href="{{ config('app.url') }}/terms"
                                   style="color:#A2937F;">{{ $t('terms') }}</a>
                            </p>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>
</body>
</html>
