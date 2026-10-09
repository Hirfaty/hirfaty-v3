{{--
    The internal "an artisan just signed up" notice to Hirfaty's onboarding inbox.

    $seller (User), $sellerName, $store (?Store, with category and city)
--}}
@php
    $typeLabels = [
        'artist' => 'Artiste',
        'company' => 'Entreprise',
        'cooperative' => 'Coopérative',
    ];
@endphp

<x-mail.layout
    locale="fr"
    title="Nouvel artisan inscrit — Hirfaty"
    preheader="{{ $sellerName }} vient d’ouvrir un atelier sur Hirfaty.">

    <x-mail.eyebrow>À traiter · Nouvel artisan</x-mail.eyebrow>

    <x-mail.heading>{{ $sellerName }} vient de s’inscrire comme artisan.</x-mail.heading>

    <x-mail.text>
        Son atelier attend la vérification de l’équipe. Contactez-le pour
        valider la boutique et préparer ses premières fiches produits.
    </x-mail.text>

    <x-mail.panel>
        <x-mail.row label="Artisan" :value="$sellerName" />
        <x-mail.row label="E-mail" :value="$seller->email" />
        @if ($seller->phone_number)
            <x-mail.row label="Téléphone" :value="$seller->phone_number" />
        @endif
        @if ($store)
            <x-mail.row label="Atelier" :value="$store->store_name" />
            <x-mail.row label="Type" :value="$typeLabels[$store->type] ?? $store->type" />
            @if ($store->category)
                <x-mail.row label="Catégorie" :value="$store->category->nameFor('fr')" />
            @endif
            @if ($store->city)
                <x-mail.row label="Ville" :value="$store->city->name" />
            @endif
            @if ($store->description)
                <x-mail.row label="Présentation" :value="$store->description" />
            @endif
        @endif
        <x-mail.row label="Inscrit le" :value="$seller->created_at?->timezone('Africa/Casablanca')->format('d/m/Y à H:i')" />
    </x-mail.panel>

    <x-mail.button :url="route('admin.users.show', $seller)">
        Ouvrir dans l’admin
    </x-mail.button>

    <x-slot:footer>
        Notification interne Hirfaty — envoyée à l’équipe qui accompagne les artisans.
    </x-slot:footer>
</x-mail.layout>
