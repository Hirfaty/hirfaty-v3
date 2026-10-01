@php
    /*
     * The buyer's invoice for one purchase — rendered to PDF by
     * PurchaseInvoiceService. dompdf supports CSS 2.1 and little else, so the
     * layout is tables and floats: no flexbox, no grid, no custom properties.
     * DejaVu Sans ships with dompdf and covers every accented Latin letter.
     */
    $t = fn (string $key, array $replace = []) => __('buyer.invoice.'.$key, $replace, $locale);
    $first = $orders->first();
    $buyerName = trim(($buyer->first_name ?? '').' '.($buyer->last_name ?? ''));
    $placedAt = $first->created_at?->locale($locale)->translatedFormat('j F Y, H:i');
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <title>{{ $t('title') }} {{ $reference }}</title>
    <style>
        @page { margin: 36px 40px; }
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { color: #3b2a20; font-size: 10.5px; line-height: 1.45; }
        .muted { color: #8a7a6e; }
        .brand { font-size: 24px; letter-spacing: 3px; color: #5a3d2b; font-weight: bold; }
        .title { font-size: 18px; color: #5a3d2b; text-align: right; }
        .ref { font-size: 11px; text-align: right; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; }
        .parties { margin-top: 28px; }
        .parties td { vertical-align: top; width: 50%; }
        .label { font-size: 8.5px; letter-spacing: 1.2px; text-transform: uppercase; color: #8a7a6e; margin-bottom: 4px; }
        .lines { margin-top: 28px; }
        .lines th { font-size: 8.5px; letter-spacing: 1px; text-transform: uppercase; color: #8a7a6e; text-align: left; border-bottom: 1px solid #d9cfc6; padding: 6px 4px; font-weight: normal; }
        .lines td { border-bottom: 1px solid #efe8e1; padding: 8px 4px; vertical-align: top; }
        .num, .lines th.num { text-align: right; white-space: nowrap; }
        .variant, .store { font-size: 9px; color: #8a7a6e; }
        .totals { margin-top: 16px; width: 45%; margin-left: 55%; }
        .totals td { padding: 4px 4px; }
        .totals .grand td { border-top: 1px solid #d9cfc6; padding-top: 8px; font-size: 12.5px; font-weight: bold; color: #5a3d2b; }
        .footer { position: fixed; bottom: -12px; left: 0; right: 0; text-align: center; font-size: 8.5px; color: #8a7a6e; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <div class="brand">HIRFATY</div>
                <div class="muted">{{ __('welcome.contact.address', [], $locale) }}</div>
                <div class="muted">{{ __('welcome.contact.email', [], $locale) }} · {{ __('welcome.contact.phone', [], $locale) }}</div>
            </td>
            <td>
                <div class="title">{{ $t('title') }}</div>
                <div class="ref">{{ $t('number') }} {{ $reference }}</div>
                <div class="ref muted">{{ $t('date') }} {{ $placedAt }}</div>
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <div class="label">{{ $t('billed_to') }}</div>
                @if ($buyerName !== '')
                    <div><strong>{{ $buyerName }}</strong></div>
                @endif
                <div>{{ $buyer->email }}</div>
                @if ($buyer->phone_number)
                    <div>{{ $buyer->phone_number }}</div>
                @endif
            </td>
            <td>
                <div class="label">{{ $t('delivered_to') }}</div>
                <div>{{ $first->shipping_address }}</div>
                @if ($first->city)
                    <div>{{ $first->city->name }}</div>
                @endif
                @if ($first->shipping_phone)
                    <div>{{ $first->shipping_phone }}</div>
                @endif
                @if ($first->delivery_service_name)
                    <div class="muted">{{ $t('carrier') }} {{ $first->delivery_service_name }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>{{ $t('item') }}</th>
                <th class="num">{{ $t('quantity') }}</th>
                <th class="num">{{ $t('unit_price') }}</th>
                <th class="num">{{ $t('subtotal') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                <tr>
                    <td>
                        {{ $line['name'] }}
                        @if ($line['variant'])
                            <div class="variant">{{ $line['variant'] }}</div>
                        @endif
                        @if ($line['store'])
                            <div class="store">{{ $t('workshop') }} {{ $line['store'] }}</div>
                        @endif
                    </td>
                    <td class="num">{{ $line['quantity'] }}</td>
                    <td class="num">{{ $money($line['unit_price']) }}</td>
                    <td class="num">{{ $money($line['subtotal']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="muted">{{ $t('items_total') }}</td>
            <td class="num">{{ $money($itemsTotal) }}</td>
        </tr>
        <tr>
            <td class="muted">{{ $t('delivery') }}</td>
            <td class="num">{{ $money($deliveryFee) }}</td>
        </tr>
        <tr class="grand">
            <td>{{ $t('total') }}</td>
            <td class="num">{{ $money($itemsTotal + $deliveryFee) }}</td>
        </tr>
    </table>

    <p class="muted" style="margin-top: 28px;">{{ $t('payment') }}</p>

    <div class="footer">{{ $t('footer') }}</div>
</body>
</html>
