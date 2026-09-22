{{--
    A body paragraph.

    Props:
      tight — reduce the top gap, for a second paragraph following a first
--}}
@props(['tight' => false])
@aware(['locale' => 'fr'])

@php
    $theme = config('mail_theme');
    $font = $locale === 'ar' ? $theme['font_rtl'] : $theme['font'];
    $align = $locale === 'ar' ? 'right' : 'left';
    $top = $tight ? 10 : 18;
@endphp

<tr>
    <td align="{{ $align }}" style="padding:{{ $top }}px 40px 0 40px;">
        <p style="margin:0; font-family:{{ $font }}; font-size:15px; line-height:1.7; color:{{ $theme['soft'] }};">
            {{ $slot }}
        </p>
    </td>
</tr>
