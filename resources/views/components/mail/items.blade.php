{{--
    One workshop's lines in an order.

    The old version wrapped this in a `.store-head` built with
    `display:flex; justify-content:space-between` to push the reference away
    from the shop name — which, in Outlook and Gmail, simply printed the two
    jammed together. Here the head is a two-cell table row like everything else.

    Props:
      store     — shop name shown in the head
      reference — that order's own reference, opposite the name (optional)
      items     — iterable of OrderItem
      labels    — ['product' => …, 'qty' => …, 'price' => …] column headings
--}}
@props(['store' => null, 'reference' => null, 'items', 'labels'])
@aware(['locale' => 'fr'])

@php
    $theme = config('mail_theme');
    $rtl = $locale === 'ar';
    $font = $rtl ? $theme['font_rtl'] : $theme['font'];
    $start = $rtl ? 'right' : 'left';
    $end = $rtl ? 'left' : 'right';

    $head = "font-family:{$font}; font-size:10px; letter-spacing:0.14em; text-transform:uppercase; color:{$theme['muted']}; padding:10px 16px; border-bottom:1px solid {$theme['line']};";
    $cell = "font-family:{$font}; font-size:13px; color:{$theme['ink']}; padding:12px 16px; border-bottom:1px solid {$theme['line']}; vertical-align:top;";
@endphp

<tr>
    <td style="padding:24px 40px 0 40px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="border:1px solid {{ $theme['line'] }};">

            @if ($store)
                <tr>
                    <td colspan="3" style="padding:0;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                               style="background-color:{{ $theme['page'] }};">
                            <tr>
                                <td align="{{ $start }}"
                                    style="padding:12px 16px; border-bottom:1px solid {{ $theme['line'] }}; font-family:{{ $font }}; font-size:14px; font-weight:700; color:{{ $theme['ink'] }};">
                                    {{ $store }}
                                </td>
                                @if ($reference)
                                    <td align="{{ $end }}"
                                        style="padding:12px 16px; border-bottom:1px solid {{ $theme['line'] }}; font-family:{{ $font }}; font-size:11px; color:{{ $theme['muted'] }};">
                                        {{ $reference }}
                                    </td>
                                @endif
                            </tr>
                        </table>
                    </td>
                </tr>
            @endif

            <tr>
                <td align="{{ $start }}" style="{{ $head }}">{{ $labels['product'] }}</td>
                <td align="center" style="{{ $head }}">{{ $labels['qty'] }}</td>
                <td align="{{ $end }}" style="{{ $head }}">{{ $labels['price'] }}</td>
            </tr>

            @foreach ($items as $item)
                <tr>
                    <td align="{{ $start }}" style="{{ $cell }}">
                        {{ $item->product->name ?? '—' }}
                        @if ($item->variant_label)
                            <br><span style="font-size:12px; color:{{ $theme['muted'] }};">{{ $item->variant_label }}</span>
                        @endif
                    </td>
                    <td align="center" style="{{ $cell }} color:{{ $theme['muted'] }};" dir="ltr">&times; {{ $item->quantity }}</td>
                    <td align="{{ $end }}" style="{{ $cell }}" dir="ltr">{{ number_format($item->subtotal, 2) }} MAD</td>
                </tr>
            @endforeach
        </table>
    </td>
</tr>
