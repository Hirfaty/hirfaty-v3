{{--
    The money summary under an order's lines.

    Replaces `.total-row { display:flex; justify-content:space-between }`,
    which put the amount immediately after its label instead of against the
    far edge in every client that does not do flexbox — i.e. most of them.

    Props:
      lines — [['label' => …, 'amount' => 12.0], …] in display order
      total — ['label' => …, 'amount' => 32.0]; emphasised, above a rule

    Amounts are forced `dir="ltr"`: "32,00 MAD" is a left-to-right token and
    Arabic must not reorder it.
--}}
@props(['lines' => [], 'total' => null])
@aware(['locale' => 'fr'])

@php
    $theme = config('mail_theme');
    $rtl = $locale === 'ar';
    $font = $rtl ? $theme['font_rtl'] : $theme['font'];
    $start = $rtl ? 'right' : 'left';
    $end = $rtl ? 'left' : 'right';
@endphp

<tr>
    <td style="padding:20px 40px 0 40px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
            @foreach ($lines as $line)
                <tr>
                    <td align="{{ $start }}" style="padding:5px 0; font-family:{{ $font }}; font-size:13px; color:{{ $theme['soft'] }};">
                        {{ $line['label'] }}
                    </td>
                    <td align="{{ $end }}" dir="ltr" style="padding:5px 0; font-family:{{ $font }}; font-size:13px; color:{{ $theme['soft'] }};">
                        {{ number_format($line['amount'], 2) }} MAD
                    </td>
                </tr>
            @endforeach

            @if ($total)
                <tr>
                    <td colspan="2" style="padding:8px 0 0 0;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td style="height:1px; line-height:1px; font-size:0; background-color:{{ $theme['line'] }};">&nbsp;</td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td align="{{ $start }}" style="padding:12px 0 0 0; font-family:{{ $font }}; font-size:15px; font-weight:700; color:{{ $theme['ink'] }};">
                        {{ $total['label'] }}
                    </td>
                    <td align="{{ $end }}" dir="ltr" style="padding:12px 0 0 0; font-family:{{ $font }}; font-size:15px; font-weight:700; color:{{ $theme['ink'] }};">
                        {{ number_format($total['amount'], 2) }} MAD
                    </td>
                </tr>
            @endif
        </table>
    </td>
</tr>
