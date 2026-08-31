<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Nouvelle commande — Hirfaty</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-color: #F5F0E8; font-family: 'Georgia', serif; color: #1C1917; -webkit-font-smoothing: antialiased; }
        .wrapper { max-width: 580px; margin: 40px auto; padding: 0 16px 60px; }
        .header { padding: 32px 0 28px; border-bottom: 1px solid rgba(28,25,23,0.12); margin-bottom: 40px; }
        .logo { font-size: 22px; font-weight: 700; color: #1C1917; text-decoration: none; }
        .logo span { color: #C1522A; }
        .eyebrow { font-size: 10px; letter-spacing: 0.18em; text-transform: uppercase; color: #78716C; margin-bottom: 16px; }
        .headline { font-size: 32px; line-height: 1.2; color: #1C1917; margin-bottom: 20px; }
        .headline em { color: #C1522A; font-style: italic; }
        .body-text { font-size: 15px; line-height: 1.7; color: rgba(28,25,23,0.75); margin-bottom: 16px; }
        .badge { display: inline-block; background: #DBEAFE; color: #1E40AF; font-size: 10px; letter-spacing: 0.15em; text-transform: uppercase; padding: 4px 10px; margin-bottom: 24px; }
        .order-meta { border: 1px solid rgba(28,25,23,0.12); padding: 20px; margin: 24px 0; background: #FAF7F2; }
        .order-meta-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(28,25,23,0.08); font-size: 13px; }
        .order-meta-row:last-child { border-bottom: none; }
        .order-meta-label { color: #78716C; }
        .order-meta-value { color: #1C1917; font-weight: 500; }
        .items-table { width: 100%; border-collapse: collapse; margin: 24px 0; }
        .items-table th { font-size: 10px; letter-spacing: 0.15em; text-transform: uppercase; color: #78716C; text-align: left; padding: 8px 0; border-bottom: 1px solid rgba(28,25,23,0.15); }
        .items-table td { font-size: 13px; padding: 12px 0; border-bottom: 1px solid rgba(28,25,23,0.08); color: #1C1917; }
        .amount { text-align: right; }
        .total-row { display: flex; justify-content: space-between; font-size: 13px; padding: 6px 0; color: rgba(28,25,23,0.65); }
        .total-row.final { border-top: 1px solid rgba(28,25,23,0.15); margin-top: 8px; padding-top: 12px; font-size: 15px; font-weight: 600; color: #1C1917; }
        .earnings-box { background: rgba(193,82,42,0.08); border-left: 3px solid #C1522A; padding: 16px; margin: 24px 0; }
        .earnings-box p { font-size: 13px; line-height: 1.7; color: #1C1917; }
        .earnings-box .amount { font-size: 22px; font-weight: 700; color: #C1522A; margin-top: 4px; }
        .urgent-note { background: #FEF3C7; border-left: 3px solid #F59E0B; padding: 14px 16px; margin: 24px 0; }
        .urgent-note p { font-size: 13px; line-height: 1.7; color: #92400E; }
        .cta-btn { display: inline-block; background-color: #C1522A; color: #FAF7F2 !important; text-decoration: none; font-size: 14px; font-family: 'Georgia', serif; letter-spacing: 0.04em; padding: 14px 32px; margin: 28px 0; }
        .footer { border-top: 1px solid rgba(28,25,23,0.12); padding-top: 28px; margin-top: 40px; }
        .footer p { font-size: 11px; color: #A8A29E; line-height: 1.7; margin-bottom: 6px; }
        .footer a { color: #78716C; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <a href="{{ config('app.url') }}" class="logo">Hirfaty<span>.</span></a>
        </div>

        <span class="badge">🛍 Nouvelle commande</span>

        <p class="eyebrow">{{ $order->store->store_name }}</p>

        <h1 class="headline">
            Vous avez une<br>
            <em>nouvelle commande</em> !
        </h1>

        <p class="body-text">
            Bonjour {{ $user->first_name }}, vous venez de recevoir une nouvelle commande.
            Confirmez-la rapidement pour assurer une bonne expérience à l'acheteur.
        </p>

        {{-- Order meta --}}
        <div class="order-meta">
            <div class="order-meta-row">
                <span class="order-meta-label">Commande</span>
                <span class="order-meta-value">{{ $order->reference }}</span>
            </div>
            <div class="order-meta-row">
                <span class="order-meta-label">Acheteur</span>
                <span class="order-meta-value">{{ $order->buyer->first_name }} {{ $order->buyer->last_name }}</span>
            </div>
            <div class="order-meta-row">
                <span class="order-meta-label">Date</span>
                <span class="order-meta-value">{{ $order->created_at->format('d/m/Y à H:i') }}</span>
            </div>
            <div class="order-meta-row">
                <span class="order-meta-label">Livraison à</span>
                <span class="order-meta-value">{{ $order->shipping_address }}</span>
            </div>
        </div>

        {{-- Items --}}
        <table class="items-table">
            <thead>
                <tr>
                    <th>Produit</th>
                    <th style="text-align:center">Qté</th>
                    <th style="text-align:right">Prix</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                <tr>
                    <td>{{ $item->product->name }}</td>
                    <td style="text-align:center;color:#78716C">× {{ $item->quantity }}</td>
                    <td class="amount">{{ number_format($item->subtotal, 2) }} MAD</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Totals --}}
        <div class="total-row">
            <span>Total commande</span>
            <span>{{ number_format($order->total_amount, 2) }} MAD</span>
        </div>

        {{-- Earnings --}}
        <div class="earnings-box">
            <p>Vos gains sur cette commande (après commission 20% + Amana)</p>
            <p class="amount">{{ number_format($order->seller_earnings, 2) }} MAD</p>
        </div>

        {{-- Urgency --}}
        <div class="urgent-note">
            <p>
                ⏱ Confirmez cette commande dès que possible. Les acheteurs apprécient
                une réponse rapide et cela améliore votre classement sur Hirfaty.
            </p>
        </div>

        <a href="{{ config('app.url') }}/seller/orders/{{ $order->reference }}" class="cta-btn">
            Voir et confirmer la commande →
        </a>

        <div class="footer">
            <p>Vous recevez cet email car vous êtes vendeur sur Hirfaty.</p>
            <p>
                © {{ date('Y') }} Hirfaty — La plateforme des artisans marocains.<br>
                <a href="{{ config('app.url') }}/seller/orders">Mes commandes</a>
                &nbsp;·&nbsp;
                <a href="{{ config('app.url') }}/seller/dashboard">Tableau de bord</a>
            </p>
        </div>
    </div>
</body>
</html>