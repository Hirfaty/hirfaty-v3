<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Nouvelle commande — Hirfaty</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-color: #F5F0E8; font-family: 'Georgia', serif; color: #1C1917; -webkit-font-smoothing: antialiased; }
        .wrapper { max-width: 620px; margin: 40px auto; padding: 0 16px 60px; }
        .header { padding: 32px 0 28px; border-bottom: 1px solid rgba(28,25,23,0.12); margin-bottom: 36px; }
        .logo { font-size: 22px; font-weight: 700; color: #1C1917; text-decoration: none; }
        .logo span { color: #C1522A; }
        .eyebrow { font-size: 10px; letter-spacing: 0.18em; text-transform: uppercase; color: #78716C; margin-bottom: 14px; }
        .headline { font-size: 28px; line-height: 1.25; color: #1C1917; margin-bottom: 18px; }
        .headline em { color: #C1522A; font-style: italic; }
        .body-text { font-size: 15px; line-height: 1.7; color: rgba(28,25,23,0.75); margin-bottom: 16px; }
        .badge { display: inline-block; background: #FEF3C7; color: #92400E; font-size: 10px; letter-spacing: 0.15em; text-transform: uppercase; padding: 4px 10px; margin-bottom: 22px; }
        .meta { border: 1px solid rgba(28,25,23,0.12); padding: 20px; margin: 22px 0; background: #FAF7F2; }
        .meta-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(28,25,23,0.08); font-size: 13px; }
        .meta-row:last-child { border-bottom: none; }
        .meta-label { color: #78716C; }
        .meta-value { color: #1C1917; font-weight: 500; text-align: right; }
        .store-block { border: 1px solid rgba(28,25,23,0.12); margin: 18px 0; }
        .store-head { background: rgba(28,25,23,0.04); padding: 12px 16px; border-bottom: 1px solid rgba(28,25,23,0.1); }
        .store-name { font-size: 14px; font-weight: 600; color: #1C1917; }
        .store-sub { font-size: 11px; color: #78716C; margin-top: 3px; }
        .item { display: flex; align-items: center; gap: 14px; padding: 14px 16px; border-bottom: 1px solid rgba(28,25,23,0.07); }
        .item:last-child { border-bottom: none; }
        .thumb { width: 64px; height: 64px; flex-shrink: 0; border: 1px solid rgba(28,25,23,0.1); background: #EFE9E0; object-fit: cover; }
        .thumb-empty { width: 64px; height: 64px; flex-shrink: 0; border: 1px solid rgba(28,25,23,0.1); background: #EFE9E0; }
        .item-body { flex: 1; min-width: 0; }
        .item-name { font-size: 14px; color: #1C1917; }
        .item-variant { font-size: 12px; color: #78716C; margin-top: 2px; }
        .item-qty { font-size: 12px; color: #78716C; margin-top: 4px; }
        .item-amount { font-size: 13px; color: #1C1917; white-space: nowrap; }
        .totals { margin: 22px 0 28px; }
        .total-row { display: flex; justify-content: space-between; font-size: 13px; padding: 6px 0; color: rgba(28,25,23,0.65); }
        .total-row.final { border-top: 1px solid rgba(28,25,23,0.15); margin-top: 8px; padding-top: 12px; font-size: 15px; font-weight: 600; color: #1C1917; }
        .cta-wrap { margin: 30px 0; }
        .cta-btn { display: inline-block; background-color: #C1522A; color: #FAF7F2 !important; text-decoration: none; font-size: 14px; font-family: 'Georgia', serif; letter-spacing: 0.04em; padding: 14px 30px; }
        .footer { border-top: 1px solid rgba(28,25,23,0.12); padding-top: 26px; margin-top: 36px; }
        .footer p { font-size: 11px; color: #A8A29E; line-height: 1.7; margin-bottom: 6px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <a href="{{ config('app.url') }}" class="logo">Hirfaty<span>.</span></a>
        </div>

        <span class="badge">● À traiter</span>

        <p class="eyebrow">Nouvelle commande</p>

        <h1 class="headline">
            {{ $buyerName }} vient de<br>
            <em>passer commande</em>.
        </h1>

        @php
            // One checkout can span several workshops; the desk collects from
            // each of them, so the totals are summed across all of its orders.
            $grandTotal = $orders->sum(fn ($o) => (float) $o->total_amount);
            $deliveryTotal = $orders->sum(fn ($o) => (float) $o->delivery_fee);
            $itemCount = $orders->sum(fn ($o) => $o->items->sum('quantity'));
            $first = $orders->first();
            $carrier = $first?->delivery_service_name ?? 'Amana';
        @endphp

        <p class="body-text">
            {{ $itemCount }} article{{ $itemCount > 1 ? 's' : '' }}
            auprès de {{ $orders->count() }} atelier{{ $orders->count() > 1 ? 's' : '' }}.
            À collecter puis livrer à l’adresse ci-dessous.
        </p>

        {{-- Who and where --}}
        <div class="meta">
            <div class="meta-row">
                <span class="meta-label">Référence</span>
                <span class="meta-value">{{ $reference }}</span>
            </div>
            <div class="meta-row">
                <span class="meta-label">Client</span>
                <span class="meta-value">{{ $buyerName }}</span>
            </div>
            @if($buyer?->email)
            <div class="meta-row">
                <span class="meta-label">E-mail</span>
                <span class="meta-value">{{ $buyer->email }}</span>
            </div>
            @endif
            @if($first?->shipping_phone)
            <div class="meta-row">
                <span class="meta-label">Téléphone</span>
                <span class="meta-value">{{ $first->shipping_phone }}</span>
            </div>
            @endif
            <div class="meta-row">
                <span class="meta-label">Livraison à</span>
                <span class="meta-value">{{ $first?->shipping_address }}</span>
            </div>
            @if($first?->notes)
            <div class="meta-row">
                <span class="meta-label">Note du client</span>
                <span class="meta-value">{{ $first->notes }}</span>
            </div>
            @endif
        </div>

        {{-- One block per workshop, so the desk knows where to collect what --}}
        <p class="eyebrow" style="margin-top:30px;">À collecter</p>

        @foreach($orders as $order)
        <div class="store-block">
            <div class="store-head">
                <p class="store-name">{{ $order->store?->store_name ?? 'Atelier inconnu' }}</p>
                <p class="store-sub">
                    Commande {{ $order->reference }}
                    @if($order->store?->city?->name) · {{ $order->store->city->name }} @endif
                </p>
            </div>

            @foreach($order->items as $item)
            @php $image = $item->product?->images->first(); @endphp
            <div class="item">
                @if($image)
                    <img src="{{ $image->url }}" alt="" class="thumb" width="64" height="64" />
                @else
                    <div class="thumb-empty"></div>
                @endif
                <div class="item-body">
                    <p class="item-name">{{ $item->product?->name ?? 'Produit supprimé' }}</p>
                    @if($item->variant_label)
                        <p class="item-variant">{{ $item->variant_label }}</p>
                    @endif
                    <p class="item-qty">Quantité : {{ $item->quantity }}</p>
                </div>
                <span class="item-amount">{{ number_format($item->subtotal, 2) }} MAD TTC</span>
            </div>
            @endforeach
        </div>
        @endforeach

        <div class="totals">
            <div class="total-row">
                <span>Produits</span>
                <span>{{ number_format($grandTotal, 2) }} MAD TTC</span>
            </div>
            <div class="total-row">
                <span>Livraison {{ $carrier }}</span>
                <span>{{ number_format($deliveryTotal, 2) }} MAD TTC</span>
            </div>
            <div class="total-row final">
                <span>Total</span>
                <span>{{ number_format($grandTotal + $deliveryTotal, 2) }} MAD TTC</span>
            </div>
        </div>

        <div class="cta-wrap">
            <a href="{{ config('app.url') }}/admin/orders/{{ $first?->reference }}" class="cta-btn">
                Ouvrir dans l’admin →
            </a>
        </div>

        <div class="footer">
            <p>Notification interne Hirfaty — envoyée à l’équipe qui gère les livraisons.</p>
            <p>© {{ date('Y') }} Hirfaty</p>
        </div>
    </div>
</body>
</html>
