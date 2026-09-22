{{--
    "If the button doesn't work, copy this link" — the plain URL under a CTA.

    Worth its own component because of `word-break:break-all`: a signed reset
    or verification URL is long enough to force a horizontal scrollbar on a
    phone without it, which pushes the whole card out of the viewport.

    Props:
      url   — the same href the button points at
      label — the line introducing it
--}}
@props(['url', 'label'])
@aware(['locale' => 'fr'])

@php
    $theme = config('mail_theme');
    $rtl = $locale === 'ar';
    $font = $rtl ? $theme['font_rtl'] : $theme['font'];
    $align = $rtl ? 'right' : 'left';
@endphp

<tr>
    <td style="padding:20px 40px 0 40px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="background-color:{{ $theme['page'] }};">
            <tr>
                <td align="{{ $align }}" style="padding:14px 16px;">
                    <p style="margin:0 0 6px 0; font-family:{{ $font }}; font-size:12px; color:{{ $theme['soft'] }};">
                        {{ $label }}
                    </p>
                    {{-- dir=ltr: a URL is a left-to-right token and Arabic
                         must not reorder its segments. --}}
                    <a href="{{ $url }}" dir="ltr"
                       style="font-family:{{ $theme['font'] }}; font-size:12px; color:{{ $theme['accent'] }}; word-break:break-all;">{{ $url }}</a>
                </td>
            </tr>
        </table>
    </td>
</tr>
