<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
    <meta charset="UTF-8" />
    <title>Commande annulée — Hirfaty</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-color: #F5F0E8; font-family: 'Georgia', serif; color: #1C1917; }
        .wrapper { max-width: 580px; margin: 40px auto; padding: 0 16px 60px; }
        .header { padding: 32px 0 28px; border-bottom: 1px solid rgba(28,25,23,0.12); margin-bottom: 40px; }
        .logo { font-size: 22px; font-weight: 700; color: #1C1917; text-decoration: none; }
        .logo span { color: #C1522A; }
        .badge { display: inline-block; background: #FEE2E2; color: #991B1B; font-size: 10px; letter-spacing: 0.15em; text-transform: uppercase; padding: 4px 10px; margin-bottom: 24px; }
        .headline { font-size: 28px; line-height: 1.2; color: #1C1917; margin-bottom: 16px; }
        .body-text { font-size: 15px; line-height: 1.7; color: rgba(28,25,23,0.75); margin-bottom: 16px; }
        .order-meta { border: 1px solid rgba(28,25,23,0.12); padding: 20px; margin: 24px 0; background: #FAF7F2; }
        .order-meta-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(28,25,23,0.08); font-size: 13px; }
        .order-meta-row:last-child { border-bottom: none; }
        .order-meta-label { color: #78716C; }
        .order-meta-value { color: #1C1917; font-weight: 500; }
        .reason-box { background: #FEF2F2; border-left: 3px solid #EF4444; padding: 16px; margin: 24px 0; }
        .reason-box .label { font-size: 10px; letter-spacing: 0.15em; text-transform: uppercase; color: #EF4444; margin-bottom: 8px; }
        .reason-box p { font-size: 13px; line-height: 1.7; color: #7F1D1D; }
        .tip-box { background: rgba(28,25,23,0.04); border-left: 3px solid #78716C; padding: 14px 16px; margin: 24px 0; }
        .tip-box p { font-size: 13px; line-height: 1.7; color: rgba(28,25,23,0.7); }
        .cta-btn { display: inline-block; background-color: #1C1917; color: #FAF7F2 !important; text-decoration: none; font-size: 14px; padding: 14px 32px; margin: 20px 0; }
        .footer { border-top: 1px solid rgba(28,25,23,0.12); padding-top: 28px; margin-top: 40px; }
        .footer p { font-size: 11px; color: #A8A29E; line-height: 1.7; }
        .footer a { color: #78716C; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <a href="{{ config('app.url') }}" class="logo">Hirfaty<span>.</span></a>
        </div>

        <span class="badge">✕ Commande annulée</span>

        <h1 class="headline">
            L'acheteur a annulé<br>la commande {{ $order->reference }}.
        </h1>

        <p class="body-text">
            Bonjour {{ $user->first_name }}, l'acheteur
            <strong>{{ $order->buyer->first_name }} {{ $order->buyer->last_name }}</strong>
            a annulé sa commande. Le stock a été automatiquement restauré.
        </p>

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
                <span class="order-meta-label">Montant</span>
                <span class="order-meta-value">{{ number_format($order->total_amount, 2) }} MAD</span>
            </div>
        </div>

        <div class="reason-box">
            <p class="label">Raison de l'annulation</p>
            <p>{{ $order->cancellation_reason }}</p>
        </div>

        <div class="tip-box">
            <p>
                Le stock des produits concernés a été automatiquement remis à jour.
                Aucune action supplémentaire n'est requise de votre part.
            </p>
        </div>

        <a href="{{ config('app.url') }}/seller/orders" class="cta-btn">
            Voir mes commandes →
        </a>

        <div class="footer">
            <p>© {{ date('Y') }} Hirfaty · <a href="{{ config('app.url') }}/seller/orders">Mes commandes</a></p>
        </div>
    </div>
</body>
</html>