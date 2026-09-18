<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Commande expédiée — Hirfaty</title>
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
        .tracking-box { border: 1px solid rgba(193,82,42,0.3); background: rgba(193,82,42,0.06); padding: 20px; margin: 24px 0; text-align: center; }
        .tracking-box .label { font-size: 10px; letter-spacing: 0.15em; text-transform: uppercase; color: #78716C; margin-bottom: 8px; }
        .tracking-box .number { font-size: 22px; font-weight: 700; color: #C1522A; letter-spacing: 0.06em; }
        .shipping-box { background: rgba(28,25,23,0.04); padding: 16px; margin: 24px 0; }
        .shipping-box p { font-size: 13px; line-height: 1.7; color: rgba(28,25,23,0.7); }
        .shipping-box .label { font-size: 10px; letter-spacing: 0.15em; text-transform: uppercase; color: #78716C; margin-bottom: 8px; }
        .cta-wrap { margin: 32px 0; }
        .cta-btn { display: inline-block; background-color: #C1522A; color: #FAF7F2 !important; text-decoration: none; font-size: 14px; font-family: 'Georgia', serif; letter-spacing: 0.04em; padding: 14px 32px; }
        .steps { margin: 0; padding: 0; list-style: none; }
        .step { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 14px; }
        .step-number { flex-shrink: 0; width: 22px; height: 22px; border: 1px solid #1C1917; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #1C1917; margin-top: 2px; }
        .step-number.done { background: #1C1917; color: #FAF7F2; }
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

        <span class="badge">🚚 Commande expédiée</span>

        <p class="eyebrow">En chemin vers vous</p>

        <h1 class="headline">
            Votre commande est<br>
            <em>en route</em>.
        </h1>

        <p class="body-text">
            Bonjour {{ $user->first_name }}, {{ $order->store->store_name }} vient de
            remettre votre commande {{ $order->reference }} à Amana.
        </p>

        <div class="tracking-box">
            <p class="label">Numéro de suivi Amana</p>
            <p class="number">{{ $order->tracking_number }}</p>
        </div>

        <div class="order-meta">
            <div class="order-meta-row">
                <span class="order-meta-label">Numéro de commande</span>
                <span class="order-meta-value">{{ $order->reference }}</span>
            </div>
            <div class="order-meta-row">
                <span class="order-meta-label">Boutique</span>
                <span class="order-meta-value">{{ $order->store->store_name }}</span>
            </div>
            <div class="order-meta-row">
                <span class="order-meta-label">Livraison à</span>
                <span class="order-meta-value">{{ $order->shipping_address }}</span>
            </div>
        </div>

        <div class="shipping-box">
            <p class="label">Livraison via Amana</p>
            <p>
                Amana livre en général sous 3 à 7 jours partout au Maroc. Gardez
                votre numéro de suivi à portée de main si le livreur vous appelle.
            </p>
        </div>

        <div class="cta-wrap">
            <a href="{{ config('app.url') }}/orders/{{ $order->reference }}" class="cta-btn">
                Suivre ma commande →
            </a>
        </div>

        <hr class="divider" />

        <p class="eyebrow" style="margin-bottom:16px;">Où en est votre commande</p>
        <ul class="steps">
            <li class="step">
                <div class="step-number done">✓</div>
                <p class="step-text"><strong>Confirmée</strong> par le vendeur.</p>
            </li>
            <li class="step">
                <div class="step-number done">✓</div>
                <p class="step-text"><strong>Expédiée</strong> via Amana.</p>
            </li>
            <li class="step">
                <div class="step-number">3</div>
                <p class="step-text"><strong>En cours de livraison</strong> à l'adresse indiquée.</p>
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
