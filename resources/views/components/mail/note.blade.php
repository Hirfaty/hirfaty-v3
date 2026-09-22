{{--
    A soft aside — the "what happens next" / "why this was refused" box.

    Replaces `.tip-box`, which used an rgba() fill and a left border. Outlook's
    Word renderer supports neither rgba() nor `border-left` on a div, so the
    box lost both its tint and its rule there and read as an ordinary
    paragraph. A filled table cell with a solid hex works everywhere.

    Props:
      title — optional bold lead-in
      tone  — 'default' | 'danger'
--}}
@props(['title' => null, 'tone' => 'default'])
@aware(['locale' => 'fr'])

@php
    $theme = config('mail_theme');
    $rtl = $locale === 'ar';
    $font = $rtl ? $theme['font_rtl'] : $theme['font'];
    $align = $rtl ? 'right' : 'left';
    $edge = $tone === 'danger' ? $theme['danger'] : $theme['accent'];
    // The accent rule opens the box, so it swaps sides in Arabic.
    $border = $rtl ? "border-right:3px solid {$edge};" : "border-left:3px solid {$edge};";
@endphp

<tr>
    <td style="padding:24px 40px 0 40px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="background-color:{{ $theme['page'] }};">
            <tr>
                <td align="{{ $align }}" style="padding:16px 18px; {{ $border }}">
                    @if ($title)
                        <p style="margin:0 0 6px 0; font-family:{{ $font }}; font-size:13px; font-weight:700; color:{{ $theme['ink'] }};">
                            {{ $title }}
                        </p>
                    @endif
                    <p style="margin:0; font-family:{{ $font }}; font-size:13px; line-height:1.7; color:{{ $theme['soft'] }};">
                        {{ $slot }}
                    </p>
                </td>
            </tr>
        </table>
    </td>
</tr>
