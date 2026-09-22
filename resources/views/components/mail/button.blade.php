{{--
    The call to action.

    A table wrapping the anchor, not a styled <a>: Outlook drops padding on
    inline anchors, which collapses a padded link into bare underlined text.
    The background sits on the <td> (as both a `bgcolor` attribute and a
    style) so it survives clients that strip one or the other.

    Props:
      url  — href
      tone — 'default' (accent) | 'danger'
--}}
@props(['url', 'tone' => 'default'])
@aware(['locale' => 'fr'])

@php
    $theme = config('mail_theme');
    $font = $locale === 'ar' ? $theme['font_rtl'] : $theme['font'];
    $align = $locale === 'ar' ? 'right' : 'left';
    $background = $tone === 'danger' ? $theme['danger'] : $theme['accent'];
@endphp

<tr>
    <td align="{{ $align }}" style="padding:28px 40px 0 40px;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
            <tr>
                <td align="center" bgcolor="{{ $background }}" style="background-color:{{ $background }};">
                    <a href="{{ $url }}"
                       style="display:inline-block; padding:15px 32px; font-family:{{ $font }}; font-size:15px; letter-spacing:0.03em; color:#FFFFFF; text-decoration:none;">
                        {{ $slot }}
                    </a>
                </td>
            </tr>
        </table>
    </td>
</tr>
