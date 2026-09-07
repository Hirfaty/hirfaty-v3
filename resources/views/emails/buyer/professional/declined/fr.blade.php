@component('emails.layout', ['title' => 'Votre demande de compte professionnel — Hirfaty', 'lang' => 'fr', 'dir' => 'ltr'])
    <span class="badge badge-info">Demande examinée</span>

    <p class="eyebrow">Votre demande de compte professionnel</p>

    <h1 class="headline">
        Nous ne pouvons pas<br>
        <em>l’accepter</em> pour l’instant.
    </h1>

    <p class="body-text">
        Bonjour {{ $user->first_name }}, notre équipe a examiné votre demande de compte
        professionnel. Elle n’a pas pu être acceptée en l’état.
    </p>

    <div class="tip-box">
        <p>
            <strong>Motif :</strong> {{ $reason }}
        </p>
    </div>

    <p class="body-text">
        Votre compte reste pleinement actif et vous pouvez continuer à commander normalement.
        Vous pouvez soumettre une nouvelle demande dès que la situation évolue.
    </p>

    <div class="cta-wrap">
        <a href="{{ config('app.url') }}/products" class="cta-btn">
            Retour au marché →
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
