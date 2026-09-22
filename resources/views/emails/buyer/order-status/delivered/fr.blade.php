{{-- $orders (Collection<Order>), $reference, $user --}}
@php
    $first = $orders->first();
    $many = $orders->count() > 1;

    $steps = [
        ['title' => 'Confirmée', 'body' => 'par '.($many ? 'les ateliers' : "l'atelier").'.'],
        ['title' => 'Partie en livraison', 'body' => ''],
        ['title' => 'Livrée', 'body' => "à l'adresse indiquée."],
    ];

    /*
     * Les avis se déposent depuis la page d'une commande
     * (buyer.orders.reviews.store) : un achat auprès d'un seul atelier y
     * renvoie directement, un achat groupé retombe sur la liste.
     */
    $reviewUrl = config('app.url').'/orders'.($many ? '' : '/'.$first->reference);
@endphp

<x-mail.layout
    locale="fr"
    title="Commande livrée — Hirfaty"
    preheader="Votre commande {{ $reference }} est bien arrivée.">

    <x-mail.eyebrow>C'est arrivé</x-mail.eyebrow>

    <x-mail.heading>Votre commande est bien arrivée.</x-mail.heading>

    <x-mail.text>
        @if ($many)
            Bonjour {{ $user->first_name }}, votre commande {{ $reference }},
            réunissant {{ $orders->count() }} ateliers, a été livrée. Nous espérons
            qu'elle vous plaira autant que nous avons aimé vous la faire découvrir.
        @else
            Bonjour {{ $user->first_name }}, votre commande {{ $reference }} de
            {{ $first->store->store_name }} a été livrée. Nous espérons qu'elle vous
            plaira autant que nous avons aimé vous la faire découvrir.
        @endif
    </x-mail.text>

    <x-mail.panel>
        <x-mail.row label="Numéro de commande" :value="$reference" />
        <x-mail.row label="Livrée le" :value="optional($first->delivered_at)->format('d/m/Y')" />
    </x-mail.panel>

    {{-- Un bloc par atelier, pour qu'une commande livrée par plusieurs
         vendeurs reste lisible comme un seul et même achat. --}}
    @foreach ($orders as $order)
        <x-mail.items
            :store="$order->store->store_name"
            :reference="$order->reference"
            :items="$order->items"
            :labels="['product' => 'Produit', 'qty' => 'Qté', 'price' => 'Prix']" />
    @endforeach

    <x-mail.button :url="$reviewUrl">
        Laisser un avis
    </x-mail.button>

    <x-mail.divider />

    <x-mail.steps title="Où en est votre commande" :steps="$steps" />

    <x-slot:footer>
        Un souci avec cette commande ? Contactez-nous depuis votre espace commandes.<br>
        <a href="{{ config('app.url') }}/orders" style="color:{{ config('mail_theme.soft') }};">Mes commandes</a>
        &nbsp;·&nbsp;
        <a href="{{ config('app.url') }}/privacy" style="color:{{ config('mail_theme.soft') }};">Politique de confidentialité</a>
    </x-slot:footer>
</x-mail.layout>
