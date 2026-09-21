<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Commande annulée — Hirfaty</title>
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
        .badge { display: inline-block; background: #FEE2E2; color: #991B1B; font-size: 10px; letter-spacing: 0.15em; text-transform: uppercase; padding: 4px 10px; margin-bottom: 24px; }
        .order-meta { border: 1px solid rgba(28,25,23,0.12); padding: 20px; margin: 24px 0; background: #FAF7F2; }
        .order-meta-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(28,25,23,0.08); font-size: 13px; }
        .order-meta-row:last-child { border-bottom: none; }
        .order-meta-label { color: #78716C; }
        .order-meta-value { color: #1C1917; font-weight: 500; }
        .store-block { border: 1px solid rgba(28,25,23,0.12); margin: 24px 0; padding: 14px 16px; display: flex; justify-content: space-between; align-items: baseline; gap: 10px; }
        .store-name { font-size: 14px; font-weight: 600; color: #1C1917; }
        .store-ref { font-size: 11px; color: #78716C; }
        .reason-box { border: 1px solid rgba(153,27,27,0.2); background: rgba(153,27,27,0.05); padding: 16px; margin: 24px 0; }
        .reason-box .label { font-size: 10px; letter-spacing: 0.15em; text-transform: uppercase; color: #991B1B; margin-bottom: 8px; }
        .reason-box p { font-size: 13px; line-height: 1.7; color: rgba(28,25,23,0.75); }
        .cta-wrap { margin: 32px 0; }
        .cta-btn { display: inline-block; background-color: #C1522A; color: #FAF7F2 !important; text-decoration: none; font-size: 14px; font-family: 'Georgia', serif; letter-spacing: 0.04em; padding: 14px 32px; }
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

        <span class="badge">✕ Commande annulée</span>

        <p class="eyebrow">Nous sommes désolés</p>

        <h1 class="headline">
            Votre commande a été<br>
            <em>annulée</em>.
        </h1>

        <p class="body-text">
            {{-- Volontairement sans nommer l'auteur de l'annulation : ce même
                 email part aussi quand c'est l'acheteur qui annule depuis son
                 espace commandes (Buyer\OrderController::cancel), où « l'atelier
                 a annulé votre commande » serait tout simplement faux. --}}
            @if($orders->count() > 1)
                Bonjour {{ $user->first_name }}, votre commande {{ $reference }}, concernant
                {{ $orders->count() }} ateliers, a été annulée. Rien ne vous a été
                débité — le paiement se fait uniquement à la livraison.
            @else
                Bonjour {{ $user->first_name }}, votre commande {{ $reference }} de
                {{ $orders->first()->store->store_name }} a été annulée. Rien ne vous a
                été débité — le paiement se fait uniquement à la livraison.
            @endif
        </p>

        <div class="order-meta">
            <div class="order-meta-row">
                <span class="order-meta-label">Numéro de commande</span>
                <span class="order-meta-value">{{ $reference }}</span>
            </div>
        </div>

        @if($orders->count() > 1)
        {{-- Un bloc par atelier concerné, pour qu'une annulation touchant
             plusieurs vendeurs reste lisible comme un seul et même achat. --}}
        @foreach($orders as $order)
        <div class="store-block">
            <span class="store-name">{{ $order->store->store_name }}</span>
            <span class="store-ref">{{ $order->reference }}</span>
        </div>
        @endforeach
        @endif

        @if($orders->first()->cancellation_reason)
        <div class="reason-box">
            <p class="label">Raison indiquée</p>
            <p>{{ $orders->first()->cancellation_reason }}</p>
        </div>
        @endif

        <div class="cta-wrap">
            <a href="{{ config('app.url') }}/products" class="cta-btn">
                Continuer mes achats →
            </a>
        </div>

        <hr class="divider" />

        <div class="footer">
            <p>Une question sur cette annulation ? Contactez-nous depuis votre espace commandes.</p>
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
