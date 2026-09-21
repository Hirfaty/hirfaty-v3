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

        <span class="badge">✓ Commande confirmée</span>

        <p class="eyebrow">Ça avance</p>

        @if($orders->count() > 1)
        <h1 class="headline">
            Vos artisans préparent<br>
            <em>votre commande</em>.
        </h1>

        <p class="body-text">
            Bonjour {{ $user->first_name }}, bonne nouvelle : les {{ $orders->count() }}
            ateliers de votre commande {{ $reference }} l'ont confirmée et
            commencent à la préparer. Vous recevrez un email avec votre numéro
            de suivi dès qu'elle partira en livraison.
        </p>
        @else
        <h1 class="headline">
            {{ $orders->first()->store->store_name }} prépare<br>
            <em>votre commande</em>.
        </h1>

        <p class="body-text">
            Bonjour {{ $user->first_name }}, bonne nouvelle : l'atelier a confirmé
            votre commande {{ $reference }} et commence à la préparer.
            Vous recevrez un email avec votre numéro de suivi dès qu'elle
            partira en livraison.
        </p>
        @endif

        <div class="order-meta">
            <div class="order-meta-row">
                <span class="order-meta-label">Numéro de commande</span>
                <span class="order-meta-value">{{ $reference }}</span>
            </div>
            <div class="order-meta-row">
                <span class="order-meta-label">Livraison à</span>
                <span class="order-meta-value">{{ $orders->first()->shipping_address }}</span>
            </div>
        </div>

        {{-- Un bloc par atelier, pour qu'une commande confirmée auprès de
             plusieurs vendeurs reste lisible comme un seul et même achat —
             et pas seulement le dernier atelier traité. --}}
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

        <div class="cta-wrap">
            <a href="{{ config('app.url') }}/orders" class="cta-btn">
                Suivre ma commande →
            </a>
        </div>

        <hr class="divider" />

        <p class="eyebrow" style="margin-bottom:16px;">Où en est votre commande</p>
        <ul class="steps">
            <li class="step">
                <div class="step-number done">✓</div>
                <p class="step-text"><strong>Confirmée</strong> par {{ $orders->count() > 1 ? 'les ateliers' : "l'atelier" }}.</p>
            </li>
            <li class="step">
                <div class="step-number">2</div>
                <p class="step-text"><strong>Vous recevrez un email</strong> avec votre numéro de suivi dès le départ en livraison.</p>
            </li>
            <li class="step">
                <div class="step-number">3</div>
                <p class="step-text"><strong>Votre commande est livrée</strong> à l'adresse indiquée.</p>
            </li>
        </ul>

        <div class="footer">
            <p>Vous avez une question ? Contactez-nous depuis votre espace commandes.</p>
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
