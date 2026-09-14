@php
    /*
     * One template for every verification email — buyer or seller, in any of
     * the three locales. It replaced six near-identical files whose CSS had
     * already drifted apart; the copy now lives in lang/<locale>/emails.php.
     *
     * Built on tables with inline styles, not the divs/flexbox the old
     * templates used: Outlook renders mail through Word, which supports
     * neither `display:flex` nor `gap`, so those numbered steps collapsed into
     * a single run of text there.
     *
     * $audience  'buyer' | 'seller'
     * $user, $url, $locale
     */
    // Prefixes `emails.` only — the keys below already carry their `verify.`
    // segment, so prefixing that here too yields emails.verify.verify.*, which
    // __() cannot resolve and renders as the raw key.
    $t = fn (string $key, array $replace = []) => __("emails.{$key}", $replace, $locale);
    $copy = "verify.{$audience}";

    $rtl = $locale === 'ar';
    $dir = $rtl ? 'rtl' : 'ltr';
    $align = $rtl ? 'right' : 'left';

    // Georgia, not the brand's Didot: mail clients don't reliably load
    // webfonts, and a websafe serif keeps the editorial tone everywhere.
    $font = "Georgia, 'Times New Roman', serif";

    $ink = '#38230C';        // souk-title
    $inkSoft = '#6B5B4B';
    $brown = '#82472A';      // souk-brown
    $terracotta = '#C1522A';
    $paper = '#FFFEFB';      // souk-paper
    $peach = '#FFF1E2';      // souk-peach
    $line = '#E6DAC8';

    $steps = __("emails.{$copy}.steps", [], $locale);
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $t("{$copy}.subject") }}</title>
</head>
<body style="margin:0; padding:0; width:100%; background-color:{{ $peach }};">

    {{-- Inbox preview line. Hidden in the body, shown next to the subject. --}}
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; mso-hide:all;">
        {{ $t("{$copy}.preheader") }}
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
                                {{ $t("{$copy}.eyebrow") }}
                            </p>
                            <h1 style="margin:0; font-family:{{ $font }}; font-size:30px; line-height:1.22;
                                       font-weight:400; color:{{ $ink }};">
                                {{ $t('verify.greeting', ['name' => $user->first_name]) }}<br>
                                {{ $t("{$copy}.headline") }}
                            </h1>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td align="{{ $align }}" style="padding:18px 40px 0 40px;">
                            <p style="margin:0; font-family:{{ $font }}; font-size:15px; line-height:1.7;
                                      color:{{ $inkSoft }};">
                                {{ $t("{$copy}.body") }}
                            </p>
                        </td>
                    </tr>

                    {{-- CTA. A table rather than a styled <a>: Outlook drops
                         padding on inline anchors, collapsing the button. --}}
                    <tr>
                        <td align="{{ $align }}" style="padding:30px 40px 0 40px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="center" bgcolor="{{ $brown }}"
                                        style="background-color:{{ $brown }};">
                                        <a href="{{ $url }}"
                                           style="display:inline-block; padding:16px 34px;
                                                  font-family:{{ $font }}; font-size:15px;
                                                  letter-spacing:0.03em; color:#F0E4C9;
                                                  text-decoration:none;">
                                            {{ $t('verify.cta') }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:14px 0 0 0; font-family:{{ $font }}; font-size:12px;
                                      color:{{ $inkSoft }};">
                                {{ $t('verify.expires', ['minutes' => $expiresInMinutes]) }}
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

                    {{-- Steps. One table row each, with the number in its own
                         cell — the old flexbox version stacked wrongly in
                         Outlook. --}}
                    <tr>
                        <td align="{{ $align }}" style="padding:26px 40px 0 40px;">
                            <p style="margin:0 0 18px 0; font-family:{{ $font }}; font-size:11px;
                                      letter-spacing:0.18em; text-transform:uppercase; color:{{ $brown }};">
                                {{ $t("{$copy}.next_title") }}
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                @foreach ($steps as $i => $step)
                                    <tr>
                                        <td width="34" valign="top"
                                            style="width:34px; padding:0 0 16px 0;">
                                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                                <tr>
                                                    <td align="center" valign="middle" width="24" height="24"
                                                        style="width:24px; height:24px; border:1px solid {{ $brown }};
                                                               font-family:{{ $font }}; font-size:11px;
                                                               color:{{ $brown }};">
                                                        {{ $i + 1 }}
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                        <td valign="top" style="padding:0 0 16px 0;">
                                            <p style="margin:0; font-family:{{ $font }}; font-size:14px;
                                                      line-height:1.6; color:{{ $inkSoft }};">
                                                <strong style="color:{{ $ink }}; font-weight:700;">{{ $step['title'] }}</strong><br>
                                                {{ $step['body'] }}
                                            </p>
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>

                    {{-- Fallback URL --}}
                    <tr>
                        <td align="{{ $align }}" style="padding:8px 40px 0 40px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                   style="background-color:{{ $peach }};">
                                <tr>
                                    <td style="padding:14px 16px;">
                                        <p style="margin:0 0 6px 0; font-family:{{ $font }};
                                                  font-size:12px; color:{{ $inkSoft }};">
                                            {{ $t('verify.fallback') }}
                                        </p>
                                        <a href="{{ $url }}"
                                           style="font-family:{{ $font }}; font-size:12px;
                                                  color:{{ $terracotta }}; word-break:break-all;">{{ $url }}</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td align="{{ $align }}" style="padding:28px 40px 34px 40px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr><td style="height:1px; line-height:1px; font-size:0;
                                               background-color:{{ $line }};">&nbsp;</td></tr>
                            </table>
                            <p style="margin:20px 0 8px 0; font-family:{{ $font }}; font-size:12px;
                                      line-height:1.7; color:{{ $inkSoft }};">
                                {{ $t('verify.ignore') }}
                            </p>
                            <p style="margin:0; font-family:{{ $font }}; font-size:11px;
                                      line-height:1.8; color:#A2937F;">
                                &copy; {{ date('Y') }} Hirfaty — {{ $t('verify.tagline') }}<br>
                                <a href="{{ config('app.url') }}/privacy"
                                   style="color:#A2937F;">{{ $t('verify.privacy') }}</a>
                                &nbsp;·&nbsp;
                                <a href="{{ config('app.url') }}/terms"
                                   style="color:#A2937F;">{{ $t('verify.terms') }}</a>
                            </p>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>
</body>
</html>
