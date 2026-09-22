{{--
    The 6-digit sign-in / signup code, in its own bordered box.

    Centred and letter-spaced so the digits are easy to read off and retype.
    `dir="ltr"` explicitly even in Arabic: the code is a single left-to-right
    token and must not be reordered.

    Kept in the serif in every locale — the digits are always Latin, and the
    Arabic fallback font spaces them less evenly.

    Props:
      code — the digits
      note — optional caption underneath (expiry, usually)
--}}
@props(['code', 'note' => null])
@aware(['locale' => 'fr'])

@php
    $theme = config('mail_theme');
    $font = $locale === 'ar' ? $theme['font_rtl'] : $theme['font'];
@endphp

<tr>
    <td align="center" style="padding:28px 40px 0 40px;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0"
               style="background-color:{{ $theme['page'] }}; border:1px solid {{ $theme['line'] }};">
            <tr>
                <td align="center" dir="ltr"
                    style="padding:20px 38px; font-family:{{ $theme['font'] }}; font-size:36px; line-height:1.1; font-weight:700; letter-spacing:0.28em; color:{{ $theme['ink'] }};">
                    {{ $code }}
                </td>
            </tr>
        </table>

        @if ($note)
            <p style="margin:14px 0 0 0; font-family:{{ $font }}; font-size:12px; color:{{ $theme['soft'] }};">
                {{ $note }}
            </p>
        @endif
    </td>
</tr>
