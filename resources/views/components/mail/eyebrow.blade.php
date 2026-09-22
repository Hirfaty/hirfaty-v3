{{--
    The small uppercase line above a headline.

    Replaces the old `.badge` / `.badge-ok` / `.badge-info` trio, which carried
    its own green and amber fills. Tone is carried by the words now, not by a
    background colour that Outlook rendered as a grey block anyway.

    Props:
      tone — 'default' (accent) | 'danger' (cancelled, rejected)
--}}
@props(['tone' => 'default'])
@aware(['locale' => 'fr'])

@php
    $theme = config('mail_theme');
    $font = $locale === 'ar' ? $theme['font_rtl'] : $theme['font'];
    $align = $locale === 'ar' ? 'right' : 'left';
    $colour = $tone === 'danger' ? $theme['danger'] : $theme['accent'];
@endphp

<tr>
    <td align="{{ $align }}" style="padding:26px 40px 0 40px;">
        <p style="margin:0; font-family:{{ $font }}; font-size:11px; letter-spacing:0.18em; text-transform:uppercase; color:{{ $colour }};">
            {{ $slot }}
        </p>
    </td>
</tr>
