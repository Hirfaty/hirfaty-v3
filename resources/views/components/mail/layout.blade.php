{{--
    The shell every transactional email is built in.

    Tables with inline styles, not divs with classes. Two reasons, both of
    which the old templates hit in production:

      - Outlook renders mail through Word, which supports neither
        `display:flex` nor `gap`. Every label/value row built as a flex
        container collapsed into one run of text there.
      - Gmail strips <style> blocks in several ordinary cases (forwarded mail,
        the mobile app signed into a non-Gmail account), so a layout whose
        styling lives in CSS classes arrives as unstyled text.

    Children are placed directly inside the card's table, so each one renders
    its own <tr> — see the siblings in this directory. They read the locale off
    this component with @aware, which is why `locale` is a real prop and not
    just an attribute.

    Props:
      title      — <title>, and what most clients show as the tab/window name
      locale     — 'fr' | 'en' | 'ar'; drives direction, font and alignment
      preheader  — optional inbox preview line shown next to the subject

    Slots:
      default    — the body, as <tr> rows
      footer     — optional; the fine print under the last hairline
--}}
@props([
    'title',
    'locale' => 'fr',
    'preheader' => null,
])

@php
    $rtl = $locale === 'ar';
    $dir = $rtl ? 'rtl' : 'ltr';
    // Explicit per-cell alignment: email clients have no support for
    // `text-align: start`, so RTL has to be resolved here rather than inherited.
    $align = $rtl ? 'right' : 'left';

    $theme = config('mail_theme');
    $font = $rtl ? $theme['font_rtl'] : $theme['font'];
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    {{-- Opt out of the automatic dark-mode inversion Outlook and Apple Mail
         apply, which turns a warm paper background muddy and can drop the
         contrast on the terracotta accent below AA. --}}
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $title }}</title>
</head>
<body style="margin:0; padding:0; width:100%; background-color:{{ $theme['page'] }};">

    @if ($preheader)
        {{-- Shown next to the subject in the inbox list, never in the open
             mail. `mso-hide:all` is what hides it in Outlook, which ignores
             the other three properties. --}}
        <div style="display:none; max-height:0; overflow:hidden; opacity:0; mso-hide:all;">
            {{ $preheader }}
        </div>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="background-color:{{ $theme['page'] }};">
        <tr>
            <td align="center" style="padding:32px 16px;">

                {{-- `width` as an attribute *and* a style: Outlook reads the
                     attribute, everything else reads the style, and max-width
                     is what lets it shrink on a phone. --}}
                <table role="presentation" width="580" cellpadding="0" cellspacing="0" border="0"
                       style="width:580px; max-width:100%; background-color:{{ $theme['paper'] }}; border:1px solid {{ $theme['line'] }};">

                    {{-- The one flash of colour in the whole shell. --}}
                    <tr>
                        <td style="height:5px; line-height:5px; font-size:0; background-color:{{ $theme['accent'] }};">&nbsp;</td>
                    </tr>

                    <tr>
                        <td align="{{ $align }}" style="padding:28px 40px 0 40px;">
                            <a href="{{ config('app.url') }}"
                               style="font-family:{{ $font }}; font-size:22px; font-weight:700; color:{{ $theme['ink'] }}; text-decoration:none;">
                                Hirfaty<span style="color:{{ $theme['accent'] }};">.</span>
                            </a>
                        </td>
                    </tr>

                    {{ $slot }}

                    <tr>
                        <td align="{{ $align }}" style="padding:30px 40px 34px 40px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="height:1px; line-height:1px; font-size:0; background-color:{{ $theme['line'] }};">&nbsp;</td>
                                </tr>
                            </table>

                            @isset($footer)
                                <div style="margin-top:20px; font-family:{{ $font }}; font-size:12px; line-height:1.7; color:{{ $theme['soft'] }};">
                                    {{ $footer }}
                                </div>
                            @endisset

                            <p style="margin:16px 0 0 0; font-family:{{ $font }}; font-size:11px; line-height:1.8; color:{{ $theme['muted'] }};">
                                &copy; {{ date('Y') }} Hirfaty
                            </p>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>
</body>
</html>
