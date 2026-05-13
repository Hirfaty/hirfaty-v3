<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Vérifiez votre email — Hirfaty</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background-color: #F5F0E8;
            font-family: 'Georgia', serif;
            color: #1C1917;
            -webkit-font-smoothing: antialiased;
        }

        .wrapper {
            max-width: 580px;
            margin: 40px auto;
            padding: 0 16px 60px;
        }

        /* Header */
        .header {
            padding: 32px 0 28px;
            border-bottom: 1px solid rgba(28, 25, 23, 0.12);
            margin-bottom: 40px;
        }

        .logo {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.3px;
            color: #1C1917;
            text-decoration: none;
        }

        .logo span {
            color: #C1522A;
        }

        /* Body */
        .eyebrow {
            font-size: 10px;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: #78716C;
            margin-bottom: 16px;
        }

        .headline {
            font-size: 32px;
            line-height: 1.2;
            color: #1C1917;
            margin-bottom: 20px;
        }

        .headline em {
            color: #C1522A;
            font-style: italic;
        }

        .body-text {
            font-size: 15px;
            line-height: 1.7;
            color: rgba(28, 25, 23, 0.75);
            margin-bottom: 16px;
        }

        /* CTA Button */
        .cta-wrap {
            margin: 36px 0;
        }

        .cta-btn {
            display: inline-block;
            background-color: #C1522A;
            color: #FAF7F2 !important;
            text-decoration: none;
            font-size: 14px;
            font-family: 'Georgia', serif;
            letter-spacing: 0.04em;
            padding: 16px 36px;
        }

        /* Divider */
        .divider {
            border: none;
            border-top: 1px solid rgba(28, 25, 23, 0.12);
            margin: 36px 0;
        }

        /* Steps */
        .steps {
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .step {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 16px;
        }

        .step-number {
            flex-shrink: 0;
            width: 24px;
            height: 24px;
            border: 1px solid #1C1917;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            color: #1C1917;
            margin-top: 2px;
        }

        .step-text {
            font-size: 13px;
            line-height: 1.6;
            color: rgba(28, 25, 23, 0.7);
        }

        .step-text strong {
            color: #1C1917;
            font-weight: 600;
        }

        /* Fallback URL */
        .fallback {
            background-color: rgba(28, 25, 23, 0.04);
            border-left: 3px solid #C1522A;
            padding: 14px 16px;
            margin: 24px 0;
        }

        .fallback p {
            font-size: 11px;
            color: #78716C;
            margin-bottom: 6px;
        }

        .fallback a {
            font-size: 11px;
            color: #C1522A;
            word-break: break-all;
        }

        /* Footer */
        .footer {
            border-top: 1px solid rgba(28, 25, 23, 0.12);
            padding-top: 28px;
            margin-top: 40px;
        }

        .footer p {
            font-size: 11px;
            color: #A8A29E;
            line-height: 1.7;
            margin-bottom: 6px;
        }

        .footer a {
            color: #78716C;
            text-decoration: underline;
        }

        .expire-note {
            display: inline-block;
            background: rgba(193, 82, 42, 0.08);
            color: #C1522A;
            font-size: 11px;
            padding: 6px 12px;
            margin-top: 8px;
            letter-spacing: 0.05em;
        }
    </style>
</head>
<body>
    <div class="wrapper">

        {{-- Logo --}}
        <div class="header">
            <a href="{{ config('app.url') }}" class="logo">
                Hirfaty<span>.</span>
            </a>
        </div>

        {{-- Eyebrow --}}
        <p class="eyebrow">Vérification du compte vendeur</p>

        {{-- Headline --}}
        <h1 class="headline">
            Bonjour {{ $user->first_name }},<br>
            activez votre <em>atelier</em>.
        </h1>

        {{-- Body --}}
        <p class="body-text">
            Vous êtes à un clic de rejoindre la communauté des artisans sur Hirfaty.
            Cliquez sur le bouton ci-dessous pour vérifier votre adresse email
            et commencer à configurer votre boutique.
        </p>

        {{-- CTA --}}
        <div class="cta-wrap">
            <a href="{{ $url }}" class="cta-btn">
                Vérifier mon email →
            </a>
        </div>

        <span class="expire-note">⏱ Ce lien expire dans 60 minutes</span>

        <hr class="divider" />

        {{-- Steps --}}
        <p class="eyebrow" style="margin-bottom: 20px;">Ce qui vous attend ensuite</p>
        <ul class="steps">
            <li class="step">
                <div class="step-number">1</div>
                <p class="step-text">
                    <strong>Configurez votre boutique</strong> — nom, catégorie,
                    ville et description de votre activité artisanale.
                </p>
            </li>
            <li class="step">
                <div class="step-number">2</div>
                <p class="step-text">
                    <strong>Ajoutez vos produits</strong> — photos, prix,
                    description. Chaque produit est examiné avant publication.
                </p>
            </li>
            <li class="step">
                <div class="step-number">3</div>
                <p class="step-text">
                    <strong>Commencez à vendre</strong> — recevez des commandes,
                    gérez vos livraisons via Amana et suivez vos revenus.
                </p>
            </li>
        </ul>

        {{-- Fallback URL --}}
        <div class="fallback">
            <p>Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :</p>
            <a href="{{ $url }}">{{ $url }}</a>
        </div>

        {{-- Footer --}}
        <div class="footer">
            <p>
                Si vous n'avez pas créé de compte sur Hirfaty, ignorez cet email.
                Aucune action n'est requise.
            </p>
            <p>
                © {{ date('Y') }} Hirfaty — La plateforme des artisans marocains.<br>
                <a href="{{ config('app.url') }}/privacy">Politique de confidentialité</a>
                &nbsp;·&nbsp;
                <a href="{{ config('app.url') }}/terms">Conditions d'utilisation</a>
            </p>
        </div>

    </div>
</body>
</html>