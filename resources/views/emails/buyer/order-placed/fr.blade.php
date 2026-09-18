<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Commande confirmée — Hirfaty</title>
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
        .badge { display: inline-block; background: #D1FAE5; color: #065F46; font-size: 10px; letter-spacing: 0.15em; text-transform: uppercase; padding: 4px 10px; margin-bottom: 24px; }
        .order-meta { border: 1px solid rgba(28,25,23,0.12); padding: 20px; margin: 24px 0; background: #FAF7F2; }
        .order-meta-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(28,25,23,0.08); font-size: 13px; }
        .order-meta-row:last-child { border-bottom: none; }
        .order-meta-label { color: #78716C; }
        .order-meta-value { color: #1C1917; font-weight: 500; }
        .store-block { border: 1px solid rgba(28,25,23,0.12); margin: 24px 0; }
        .store-head { background: rgba(28,25,23,0.04); padding: 12px 16px; border-bottom: 1px solid rgba(28,25,23,0.1); display: flex; justify-content: space-between; align-items: baseline; gap: 10px; }
        .store-name { font-size: 14px; font-weight: 600; color: #1C1917; }
        .store-ref { font-size: 11px; color: #78716C; }
        .items-table { width: 100%; border-collapse: collapse; }
        .items-table th { font-size: 10px; letter-spacing: 0.15em; text-transform: uppercase; color: #78716C; text-align: left; padding: 10px 16px; border-bottom: 1px solid rgba(28,25,23,0.1); }
        .items-table td { font-size: 13px; padding: 12px 16px; border-bottom: 1px solid rgba(28,25,23,0.08); color: #1C1917; vertical-align: top; }
        .items-table tr:last-child td { border-bottom: none; }
        .items-table .amount { text-align: right; }
        .totals { margin: 0 0 28px; }
        .total-row { display: flex; justify-content: space-between; font-size: 13px; padding: 6px 0; color: rgba(28,25,23,0.65); }
        .total-row.final { border-top: 1px solid rgba(28,25,23,0.15); margin-top: 8px; padding-top: 12px; font-size: 15px; font-weight: 600; color: #1C1917; }
        .shipping-box { background: rgba(28,25,23,0.04); padding: 16px; margin: 24px 0; }
        .shipping-box p { font-size: 13px; line-height: 1.7; color: rgba(28,25,23,0.7); }
        .shipping-box .label { font-size: 10px; letter-spacing: 0.15em; text-transform: uppercase; color: #78716C; margin-bottom: 8px; }
        .cta-wrap { margin: 32px 0; }
        .cta-btn { display: inline-block; background-color: #C1522A; color: #FAF7F2 !important; text-decoration: none; font-size: 14px; font-family: 'Georgia', serif; letter-spacing: 0.04em; padding: 14px 32px; }
        .steps { margin: 0; padding: 0; list-style: none; }
        .step { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 14px; }
        .step-number { flex-shrink: 0; width: 22px; height: 22px; border: 1px solid #1C1917; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #1C1917; margin-top: 2px; }
        .step-text { font-size: 13px; line-height: 1.6; color: rgba(28,25,23,0.7); }
        .step-text strong { color: #1C1917; }
        .divider { border: none; border-top: 1px solid rgba(28,25,23,0.12); margin: 32px 0; }
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

        <span class="badge">✓ Commande confirmée</span>

        <p class="eyebrow">Merci pour votre achat</p>

        <h1 class="headline">
            Votre commande est<br>
            <em>en route</em>.
        </h1>

        @php
            // Un seul achat, même s'il touche plusieurs ateliers — les totaux
            // affichés sont donc la somme de toutes les commandes que
            // CheckoutService en a tirées.
            $subtotal = $orders->sum(fn ($o) => (float) $o->total_amount);
            $delivery = $orders->sum(fn ($o) => (float) $o->amana_fee);
            $first = $orders->first();
        @endphp

        <p class="body-text">
            Bonjour {{ $user->first_name }}, nous avons bien reçu votre commande.
            @if($orders->count() > 1)
                Elle concerne {{ $orders->count() }} ateliers différents, elle
                est donc détaillée ci-dessous boutique par boutique — chacune
                préparera et expédiera sa propre part.
            @else
                Le vendeur va la préparer et vous recevrez un email dès qu'elle sera expédiée.
            @endif
        </p>

        <div class="order-meta">
            <div class="order-meta-row">
                <span class="order-meta-label">Numéro de commande</span>
                <span class="order-meta-value">{{ $reference }}</span>
            </div>
            <div class="order-meta-row">
                <span class="order-meta-label">Date</span>
                <span class="order-meta-value">{{ $first->created_at->format('d/m/Y à H:i') }}</span>
            </div>
            <div class="order-meta-row">
                <span class="order-meta-label">Livraison à</span>
                <span class="order-meta-value">{{ $first->shipping_address }}</span>
            </div>
        </div>

        {{-- Un bloc par atelier, pour qu'un achat auprès de plusieurs
             vendeurs reste lisible comme un seul et même achat, et pas
             seulement le dernier traité. --}}
        @foreach($orders as $order)
        <div class="store-block">
            <div class="store-head">
                <span class="store-name">{{ $order->store->store_name }}</span>
                <span class="store-ref">{{ $order->reference }}</span>
            </div>
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
                        <td class="amount">{{ number_format($item->subtotal, 2) }} MAD TTC</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endforeach

        <div class="totals">
            <div class="total-row">
                <span>Sous-total</span>
                <span>{{ number_format($subtotal, 2) }} MAD TTC</span>
            </div>
            <div class="total-row">
                <span>Livraison Amana</span>
                <span>{{ number_format($delivery, 2) }} MAD TTC</span>
            </div>
            <div class="total-row final">
                <span>Total</span>
                <span>{{ number_format($subtotal + $delivery, 2) }} MAD TTC</span>
            </div>
        </div>

        <div class="shipping-box">
            <p class="label">Livraison via Amana</p>
            <p>
                Votre commande sera expédiée via Amana. Vous recevrez un numéro de suivi
                par email dès que le vendeur aura remis le colis.
            </p>
        </div>

        <div class="cta-wrap">
            <a href="{{ config('app.url') }}/orders" class="cta-btn">
                Suivre ma commande{{ $orders->count() > 1 ? 's' : '' }} →
            </a>
        </div>

        <hr class="divider" />

        <p class="eyebrow" style="margin-bottom:16px;">Ce qui se passe ensuite</p>
        <ul class="steps">
            <li class="step">
                <div class="step-number">1</div>
                <p class="step-text"><strong>Chaque vendeur prépare</strong> sa part de votre commande et la remet à Amana.</p>
            </li>
            <li class="step">
                <div class="step-number">2</div>
                <p class="step-text"><strong>Vous recevrez un email</strong> avec un numéro de suivi Amana pour chaque part.</p>
            </li>
            <li class="step">
                <div class="step-number">3</div>
                <p class="step-text"><strong>Amana livre</strong> votre commande à l'adresse indiquée.</p>
            </li>
        </ul>

        <div class="footer">
            <p>Vous avez une question ? Contactez le vendeur depuis votre espace commandes.</p>
            <p>
                © {{ date('Y') }} Hirfaty — La plateforme des artisans marocains.<br>
                <a href="{{ config('app.url') }}/orders">Mes commandes</a>
                &nbsp;·&nbsp;
                <a href="{{ config('app.url') }}/privacy">Politique de confidentialité</a>
            </p>
        </div>
    </div>
</body>
</html>
