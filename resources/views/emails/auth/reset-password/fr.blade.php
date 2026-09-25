{{-- $user, $url --}}
@php
    // Le TTL réel du lien, plutôt qu'un « 60 minutes » codé en dur qui mentait
    // dès que la configuration changeait.
    $expires = config('auth.passwords.users.expire', 60);
@endphp

<x-mail.layout
    locale="fr"
    title="Réinitialisez votre mot de passe — Hirfaty"
    preheader="Le lien de réinitialisation expire dans {{ $expires }} minutes.">

    <x-mail.eyebrow>Sécurité du compte</x-mail.eyebrow>

    <x-mail.heading>Réinitialisez votre mot de passe.</x-mail.heading>

    <x-mail.text>
        Bonjour {{ $user->first_name }}, nous avons reçu une demande de
        réinitialisation du mot de passe de votre compte Hirfaty.
        Cliquez sur le bouton ci-dessous pour créer un nouveau mot de passe.
    </x-mail.text>

    <x-mail.button :url="$url">
        Réinitialiser mon mot de passe
    </x-mail.button>

    <x-mail.text tight>
        Ce lien expire dans {{ $expires }} minutes.
    </x-mail.text>

    <x-mail.note>
        Si vous n'avez pas demandé de réinitialisation, ignorez cet email.
        Votre mot de passe restera inchangé et votre compte reste sécurisé.
    </x-mail.note>

    <x-mail.fallback-link
        :url="$url"
        label="Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :" />

    <x-slot:footer>
        <a href="{{ route('privacy', ['locale' => 'fr']) }}" style="color:{{ config('mail_theme.soft') }};">Politique de confidentialité</a>
        &nbsp;·&nbsp;
        <a href="{{ route('terms', ['locale' => 'fr']) }}" style="color:{{ config('mail_theme.soft') }};">Conditions d'utilisation</a>
    </x-slot:footer>
</x-mail.layout>
