{{-- $product, $user --}}
@php
    // `translated_name`, not `name`: Category::$casts makes `name` a per-locale
    // array, so echoing it raised "htmlspecialchars(): array given" and threw
    // the queued mail job — the seller never received this at all.
    $category = $product->category?->translated_name ?? 'Sans catégorie';
    $price = $product->sale_price ?? $product->base_price;
@endphp

<x-mail.layout
    locale="fr"
    title="Votre produit est en ligne — Hirfaty"
    preheader="{{ $product->name }} est désormais visible par tous les acheteurs.">

    <x-mail.eyebrow>Produit approuvé</x-mail.eyebrow>

    <x-mail.heading>Votre produit est en ligne.</x-mail.heading>

    <x-mail.text>
        Bonjour {{ $user->first_name }}, votre produit a été examiné et approuvé
        par notre équipe. Il est maintenant visible par tous les acheteurs sur Hirfaty.
    </x-mail.text>

    <x-mail.panel>
        <x-mail.row label="Produit" :value="$product->name" />
        <x-mail.row label="Catégorie" :value="$category" />
        <x-mail.row label="Atelier" :value="$product->store?->store_name" />
        <x-mail.row label="Prix" :value="number_format($price, 2).' MAD'" />
    </x-mail.panel>

    <x-mail.button :url="route('products.show', ['locale' => 'fr', 'slug' => $product->slug])">
        Voir mon produit en ligne
    </x-mail.button>

    <x-mail.divider />

    <x-mail.note title="Conseil">
        Partagez le lien de votre produit sur vos réseaux sociaux pour maximiser
        votre visibilité. Plus votre produit est vu, plus il remonte dans les
        résultats de recherche Hirfaty.
    </x-mail.note>

    <x-slot:footer>
        Vous recevez cet email car vous êtes vendeur sur Hirfaty.<br>
        <a href="{{ config('app.url') }}/seller/products" style="color:{{ config('mail_theme.soft') }};">Mes produits</a>
        &nbsp;·&nbsp;
        <a href="{{ config('app.url') }}/seller/dashboard" style="color:{{ config('mail_theme.soft') }};">Mon tableau de bord</a>
    </x-slot:footer>
</x-mail.layout>
