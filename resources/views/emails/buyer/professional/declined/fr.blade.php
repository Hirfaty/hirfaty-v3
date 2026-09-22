{{-- $user, $reason --}}
<x-mail.layout
    locale="fr"
    title="Votre demande de compte professionnel — Hirfaty"
    preheader="Votre demande a été examinée par notre équipe.">

    <x-mail.eyebrow tone="danger">Demande examinée</x-mail.eyebrow>

    <x-mail.heading>Nous ne pouvons pas l’accepter pour l’instant.</x-mail.heading>

    <x-mail.text>
        Bonjour {{ $user->first_name }}, notre équipe a examiné votre demande de compte
        professionnel. Elle n’a pas pu être acceptée en l’état.
    </x-mail.text>

    <x-mail.note tone="danger" title="Motif">
        {{ $reason }}
    </x-mail.note>

    <x-mail.text>
        Votre compte reste pleinement actif et vous pouvez continuer à commander normalement.
        Vous pouvez soumettre une nouvelle demande dès que la situation évolue.
    </x-mail.text>

    <x-mail.button :url="config('app.url').'/products'">
        Retour au marché
    </x-mail.button>

    <x-slot:footer>
        Vous recevez cet email suite à votre demande de compte professionnel sur Hirfaty.<br>
        <a href="{{ config('app.url') }}/products" style="color:{{ config('mail_theme.soft') }};">Le marché</a>
        &nbsp;·&nbsp;
        <a href="{{ config('app.url') }}/orders" style="color:{{ config('mail_theme.soft') }};">Mes commandes</a>
    </x-slot:footer>
</x-mail.layout>
