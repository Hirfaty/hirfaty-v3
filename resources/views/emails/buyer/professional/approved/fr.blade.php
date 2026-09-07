@component('emails.layout', ['title' => 'Compte professionnel activé — Hirfaty', 'lang' => 'fr', 'dir' => 'ltr'])
    <span class="badge badge-ok">✓ Demande acceptée</span>

    <p class="eyebrow">Bienvenue parmi les professionnels</p>

    <h1 class="headline">
        Votre compte est<br>
        <em>professionnel</em>.
    </h1>

    <p class="body-text">
        Bonjour {{ $user->first_name }}, votre demande a été examinée et acceptée par notre équipe.
        Votre compte bénéficie désormais des tarifs de gros sur Hirfaty.
    </p>

    <div class="tip-box">
        <p>
            <strong>Ce qui change :</strong> les prix professionnels s’affichent automatiquement
            sur les fiches produits concernées. Certains articles ont une quantité minimale
            de commande — elle est indiquée sur chaque fiche.
        </p>
    </div>

    <div class="cta-wrap">
        <a href="{{ config('app.url') }}/products" class="cta-btn">
            Découvrir les tarifs professionnels →
        </a>
    </div>

    @slot('footer')
        <p>Vous recevez cet email suite à votre demande de compte professionnel sur Hirfaty.</p>
        <p>
            © {{ date('Y') }} Hirfaty — La plateforme des artisans marocains.<br>
            <a href="{{ config('app.url') }}/products">Le marché</a>
            &nbsp;·&nbsp;
            <a href="{{ config('app.url') }}/orders">Mes commandes</a>
        </p>
    @endslot
@endcomponent
