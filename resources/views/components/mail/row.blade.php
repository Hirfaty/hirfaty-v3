{{--
    One label/value line inside <x-mail.panel>.

    In RTL the two cells swap: the label has to open the row on the right, so
    the order is reversed in the markup rather than left to `direction`, which
    several clients ignore on table cells.

    Props:
      label — the muted left-hand caption
      value — the emphasised right-hand value (or use the slot for markup)
--}}
@props(['label', 'value' => null])
@aware(['locale' => 'fr'])

@php
    $theme = config('mail_theme');
    $rtl = $locale === 'ar';
    $font = $rtl ? $theme['font_rtl'] : $theme['font'];

    $labelStyle = "padding:11px 16px; font-family:{$font}; font-size:13px; color:{$theme['muted']}; vertical-align:top;";
    $valueStyle = "padding:11px 16px; font-family:{$font}; font-size:13px; color:{$theme['ink']}; vertical-align:top;";
@endphp

{{--
    `dir="auto"` on the value, not on the label: values are user-entered and
    may be in either script. In an RTL mail a Latin address like
    "12 rue des Potiers, Safi" is reordered by the bidi algorithm into
    "rue des Potiers, Safi 12" — the leading number is treated as trailing
    neutral text. `dir="auto"` resolves direction from the value's own first
    strong character, so a Latin address stays LTR and an Arabic one stays
    RTL. An attribute rather than `unicode-bidi:isolate` because Outlook's
    Word renderer honours the attribute and ignores the CSS.
--}}
<tr>
    @if ($rtl)
        <td align="right" style="{{ $labelStyle }}">{{ $label }}</td>
        <td align="left" dir="auto" style="{{ $valueStyle }}">{{ $value ?? $slot }}</td>
    @else
        <td align="left" style="{{ $labelStyle }}">{{ $label }}</td>
        <td align="right" dir="auto" style="{{ $valueStyle }}">{{ $value ?? $slot }}</td>
    @endif
</tr>
