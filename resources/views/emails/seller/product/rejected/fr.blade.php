<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Produit non approuvé — Hirfaty</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-color: #F5F0E8; font-family: 'Georgia', serif; color: #1C1917; -webkit-font-smoothing: antialiased; }
        .wrapper { max-width: 580px; margin: 40px auto; padding: 0 16px 60px; }
        .header { padding: 32px 0 28px; border-bottom: 1px solid rgba(28,25,23,0.12); margin-bottom: 40px; }
        .logo { font-size: 22px; font-weight: 700; color: #1C1917; text-decoration: none; }
        .logo span { color: #C1522A; }
        .eyebrow { font-size: 10px; letter-spacing: 0.18em; text-transform: uppercase; color: #78716C; margin-bottom: 16px; }
        .headline { font-size: 32px; line-height: 1.2; color: #1C1917; margin-bottom: 20px; }
        .body-text { font-size: 15px; line-height: 1.7; color: rgba(28,25,23,0.75); margin-bottom: 16px; }
        .product-card { border: 1px solid rgba(28,25,23,0.12); padding: 20px; margin: 28px 0; background: #FAF7F2; }
        .product-name { font-size: 18px; font-weight: 600; color: #1C1917; margin-bottom: 6px; }
        .product-meta { font-size: 12px; color: #78716C; }
        .badge { display: inline-block; background: #FEE2E2; color: #991B1B; font-size: 10px; letter-spacing: 0.15em; text-transform: uppercase; padding: 4px 10px; margin-bottom: 24px; }
        .reason-box { background: #FEF2F2; border-left: 3px solid #EF4444; padding: 16px; margin: 24px 0; }
        .reason-box p { font-size: 13px; line-height: 1.7; color: #7F1D1D; }
        .reason-box .label { font-size: 10px; letter-spacing: 0.15em; text-transform: uppercase; color: #EF4444; margin-bottom: 8px; font-weight: 600; }
        .steps { margin: 20px 0 0; padding: 0; list-style: none; }
        .step { display: flex; align-items: flex-start; gap: 14px; margin-bottom: 14px; }
        .step-number { flex-shrink: 0; width: 24px; height: 24px; border: 1px solid #1C1917; display: flex; align-items: center; justify-content: center; font-size: 11px; color: #1C1917; margin-top: 2px; }
        .step-text { font-size: 13px; line-height: 1.6; color: rgba(28,25,23,0.7); }
        .step-text strong { color: #1C1917; }
        .cta-wrap { margin: 36px 0; }
        .cta-btn { display: inline-block; background-color: #C1522A; color: #FAF7F2 !important; text-decoration: none; font-size: 14px; font-family: 'Georgia', serif; letter-spacing: 0.04em; padding: 16px 36px; }
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

        <span class="badge">⚠ Modifications requises</span>

        <p class="eyebrow">Votre produit nécessite des ajustements</p>

        <h1 class="headline">
            Quelques modifications<br>sont nécessaires.
        </h1>

        <p class="body-text">
            Bonjour {{ $user->first_name }}, votre produit a été examiné par notre équipe.
            Il ne peut pas être publié en l'état, mais vous pouvez le modifier et le soumettre à nouveau.
        </p>

        <div class="product-card">
            <p class="product-name">{{ $product->name }}</p>
            <p class="product-meta">{{ $product->category?->name ?? 'Sans catégorie' }} · {{ $product->store?->store_name }}</p>
        </div>

        <div class="reason-box">
            <p class="label">Raison du refus</p>
            <p>{{ $product->rejection_reason }}</p>
        </div>

        <p class="eyebrow" style="margin-top:32px;margin-bottom:16px;">Comment corriger cela</p>
        <ul class="steps">
            <li class="step">
                <div class="step-number">1</div>
                <p class="step-text"><strong>Lisez attentivement</strong> la raison du refus ci-dessus.</p>
            </li>
            <li class="step">
                <div class="step-number">2</div>
                <p class="step-text"><strong>Modifiez votre produit</strong> en corrigeant les points mentionnés.</p>
            </li>
            <li class="step">
                <div class="step-number">3</div>
                <p class="step-text"><strong>Soumettez à nouveau</strong> — notre équipe le réexaminera rapidement.</p>
            </li>
        </ul>

        <div class="cta-wrap">
            <a href="{{ config('app.url') }}/seller/products/{{ $product->id }}/edit" class="cta-btn">
                Modifier et resoumettre →
            </a>
        </div>

        <div class="footer">
            <p>Vous recevez cet email car vous êtes vendeur sur Hirfaty.</p>
            <p>
                © {{ date('Y') }} Hirfaty — La plateforme des artisans marocains.<br>
                <a href="{{ config('app.url') }}/seller/products">Gérer mes produits</a>
                &nbsp;·&nbsp;
                <a href="{{ config('app.url') }}/seller/dashboard">Mon tableau de bord</a>
            </p>
        </div>
    </div>
</body>
</html>