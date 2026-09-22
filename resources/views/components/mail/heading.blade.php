{{--
    The one h1 in the mail.

    `font-weight:400` on purpose — at 30px the serif reads as a headline
    without bolding, and Outlook's synthetic bold on Georgia is coarse.
--}}
@aware(['locale' => 'fr'])

@php
    $theme = config('mail_theme');
    $font = $locale === 'ar' ? $theme['font_rtl'] : $theme['font'];
    $align = $locale === 'ar' ? 'right' : 'left';
@endphp

<tr>
    <td align="{{ $align }}" style="padding:14px 40px 0 40px;">
        <h1 style="margin:0; font-family:{{ $font }}; font-size:30px; line-height:1.22; font-weight:400; color:{{ $theme['ink'] }};">
            {{ $slot }}
        </h1>
    </td>
</tr>
