{{-- $orders (Collection<Order>), $reference, $user --}}
@php
    /*
     * D'une seule expédition groupée à un colis par atelier, un numéro de
     * suivi partagé signifie un seul colis en réalité — le bureau de Hirfaty
     * collecte puis expédie la commande comme un seul job (voir
     * Admin\OrderController). Listés au cas où l'historique du groupe en
     * porte plusieurs.
     */
    $trackingNumbers = $orders->pluck('tracking_number')->filter()->unique()->values();
    $first = $orders->first();
    $many = $orders->count() > 1;

    $steps = [
        ['title' => 'Confirmée', 'body' => 'par '.($many ? 'les ateliers' : "l'atelier").'.'],
        ['title' => 'Partie en livraison', 'body' => ''],
        ['title' => 'En cours de livraison', 'body' => "à l'adresse indiquée."],
    ];
@endphp

<x-mail.layout
    locale="fr"
    title="Commande expédiée — Hirfaty"
    preheader="Votre commande {{ $reference }} est partie en livraison.">

    <x-mail.eyebrow>En chemin vers vous</x-mail.eyebrow>

    <x-mail.heading>Votre commande est en route.</x-mail.heading>

    <x-mail.text>
        @if ($many)
            Bonjour {{ $user->first_name }}, votre commande {{ $reference }} est
            partie en livraison — les {{ $orders->count() }} ateliers
            qu'elle concerne ont chacun préparé leur part.
        @else
            Bonjour {{ $user->first_name }}, votre commande {{ $reference }} de
            {{ $first->store->store_name }} est partie en livraison.
        @endif
    </x-mail.text>

    <x-mail.panel>
        <x-mail.row label="Numéro de commande" :value="$reference" />
        @if ($trackingNumbers->count() === 1)
            <x-mail.row label="Numéro de suivi" :value="$trackingNumbers->first()" />
        @endif
        <x-mail.row label="Livraison à" :value="$first->shipping_address" />
    </x-mail.panel>

    {{-- Un bloc par atelier, pour que le colis groupé reste lisible comme un
         seul et même achat. --}}
    @if ($many)
        @foreach ($orders as $order)
            <x-mail.items
                :store="$order->store->store_name"
                :reference="$order->reference.($order->tracking_number && $trackingNumbers->count() > 1 ? ' · '.$order->tracking_number : '')"
                :items="$order->items"
                :labels="['product' => 'Produit', 'qty' => 'Qté', 'price' => 'Prix']" />
        @endforeach
    @endif

    <x-mail.note title="Livraison">
        La livraison prend en général 3 à 7 jours partout au Maroc.
        Gardez votre téléphone à portée de main : le livreur vous
        appelle avant de déposer le colis.
    </x-mail.note>

    <x-mail.button :url="config('app.url').'/orders'">
        Suivre ma commande
    </x-mail.button>

    <x-mail.divider />

    <x-mail.steps title="Où en est votre commande" :steps="$steps" />

    <x-slot:footer>
        Vous avez une question ? Contactez-nous depuis votre espace commandes.<br>
        <a href="{{ config('app.url') }}/orders" style="color:{{ config('mail_theme.soft') }};">Mes commandes</a>
        &nbsp;·&nbsp;
        <a href="{{ route('privacy', ['locale' => 'fr']) }}" style="color:{{ config('mail_theme.soft') }};">Politique de confidentialité</a>
    </x-slot:footer>
</x-mail.layout>
