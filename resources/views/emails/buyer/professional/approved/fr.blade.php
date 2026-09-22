{{-- $user --}}
<x-mail.layout
    locale="fr"
    title="Compte professionnel activé — Hirfaty"
    preheader="Votre compte bénéficie désormais des tarifs de gros.">

    <x-mail.eyebrow>Demande acceptée</x-mail.eyebrow>

    <x-mail.heading>Votre compte est professionnel.</x-mail.heading>

    <x-mail.text>
        Bonjour {{ $user->first_name }}, votre demande a été examinée et acceptée par notre équipe.
        Votre compte bénéficie désormais des tarifs de gros sur Hirfaty.
    </x-mail.text>

    <x-mail.note title="Ce qui change">
        Les prix professionnels s’affichent automatiquement sur les fiches
        produits concernées. Certains articles ont une quantité minimale de
        commande — elle est indiquée sur chaque fiche.
    </x-mail.note>

    <x-mail.button :url="config('app.url').'/products'">
        Découvrir les tarifs professionnels
    </x-mail.button>

    <x-slot:footer>
        Vous recevez cet email suite à votre demande de compte professionnel sur Hirfaty.<br>
        <a href="{{ config('app.url') }}/products" style="color:{{ config('mail_theme.soft') }};">Le marché</a>
        &nbsp;·&nbsp;
        <a href="{{ config('app.url') }}/orders" style="color:{{ config('mail_theme.soft') }};">Mes commandes</a>
    </x-slot:footer>
</x-mail.layout>
