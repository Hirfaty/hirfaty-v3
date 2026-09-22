{{-- $product, $user --}}
@php
    // `translated_name`, not `name`: Category::$casts makes `name` a per-locale
    // array, so echoing it raised "htmlspecialchars(): array given" and threw
    // the queued mail job — the seller never received this at all.
    $category = $product->category?->translated_name ?? 'Sans catégorie';

    /*
     * Les artisans ne déposent ni ne modifient leurs fiches — c'est l'équipe
     * Hirfaty qui s'en charge — d'où l'invitation à nous écrire plutôt qu'à
     * modifier une fiche inaccessible.
     */
    $steps = [
        ['title' => 'Lisez attentivement', 'body' => 'la raison du refus ci-dessus.'],
        ['title' => 'Contactez notre équipe', 'body' => 'avec les éléments corrigés — photos, prix ou description.'],
        ['title' => 'Nous republions la fiche', 'body' => 'à votre place une fois les points réglés.'],
    ];
@endphp

<x-mail.layout
    locale="fr"
    title="Votre produit nécessite des modifications — Hirfaty"
    preheader="{{ $product->name }} ne peut pas être publié en l'état.">

    <x-mail.eyebrow tone="danger">Modifications requises</x-mail.eyebrow>

    <x-mail.heading>Quelques modifications sont nécessaires.</x-mail.heading>

    <x-mail.text>
        Bonjour {{ $user->first_name }}, votre produit a été examiné par notre équipe.
        Il ne peut pas être publié en l'état. Envoyez-nous les éléments
        manquants et nous corrigeons la fiche pour vous.
    </x-mail.text>

    <x-mail.panel>
        <x-mail.row label="Produit" :value="$product->name" />
        <x-mail.row label="Catégorie" :value="$category" />
        <x-mail.row label="Atelier" :value="$product->store?->store_name" />
    </x-mail.panel>

    <x-mail.note tone="danger" title="Raison du refus">
        {{ $product->rejection_reason }}
    </x-mail.note>

    <x-mail.steps title="Comment corriger cela" :steps="$steps" />

    {{-- Pointe vers la fiche en lecture seule : l'artisan ne peut pas la
         modifier lui-même. --}}
    <x-mail.button tone="danger" :url="config('app.url').'/seller/products/'.$product->id">
        Voir le produit
    </x-mail.button>

    <x-slot:footer>
        Vous recevez cet email car vous êtes vendeur sur Hirfaty.<br>
        <a href="{{ config('app.url') }}/seller/products" style="color:{{ config('mail_theme.soft') }};">Mes produits</a>
        &nbsp;·&nbsp;
        <a href="{{ config('app.url') }}/seller/dashboard" style="color:{{ config('mail_theme.soft') }};">Mon tableau de bord</a>
    </x-slot:footer>
</x-mail.layout>
