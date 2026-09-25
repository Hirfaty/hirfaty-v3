{{-- $orders (Collection<Order>), $reference, $user --}}
@php
    $first = $orders->first();
    $many = $orders->count() > 1;
@endphp

<x-mail.layout
    locale="fr"
    title="Commande annulée — Hirfaty"
    preheader="Votre commande {{ $reference }} a été annulée.">

    <x-mail.eyebrow tone="danger">Nous sommes désolés</x-mail.eyebrow>

    <x-mail.heading>Votre commande a été annulée.</x-mail.heading>

    <x-mail.text>
        {{-- Volontairement sans nommer l'auteur de l'annulation : ce même
             email part aussi quand c'est l'acheteur qui annule depuis son
             espace commandes (Buyer\OrderController::cancel), où « l'atelier
             a annulé votre commande » serait tout simplement faux. --}}
        @if ($many)
            Bonjour {{ $user->first_name }}, votre commande {{ $reference }}, concernant
            {{ $orders->count() }} ateliers, a été annulée. Rien ne vous a été
            débité — le paiement se fait uniquement à la livraison.
        @else
            Bonjour {{ $user->first_name }}, votre commande {{ $reference }} de
            {{ $first->store->store_name }} a été annulée. Rien ne vous a
            été débité — le paiement se fait uniquement à la livraison.
        @endif
    </x-mail.text>

    <x-mail.panel>
        <x-mail.row label="Numéro de commande" :value="$reference" />
    </x-mail.panel>

    {{-- Un bloc par atelier concerné, pour qu'une annulation touchant
         plusieurs vendeurs reste lisible comme un seul et même achat. --}}
    @if ($many)
        @foreach ($orders as $order)
            <x-mail.items
                :store="$order->store->store_name"
                :reference="$order->reference"
                :items="$order->items"
                :labels="['product' => 'Produit', 'qty' => 'Qté', 'price' => 'Prix']" />
        @endforeach
    @endif

    @if ($first->cancellation_reason)
        <x-mail.note tone="danger" title="Raison indiquée">
            {{ $first->cancellation_reason }}
        </x-mail.note>
    @endif

    <x-mail.button :url="route('products.index', ['locale' => 'fr'])">
        Continuer mes achats
    </x-mail.button>

    <x-slot:footer>
        Une question sur cette annulation ? Contactez-nous depuis votre espace commandes.<br>
        <a href="{{ config('app.url') }}/orders" style="color:{{ config('mail_theme.soft') }};">Mes commandes</a>
        &nbsp;·&nbsp;
        <a href="{{ route('privacy', ['locale' => 'fr']) }}" style="color:{{ config('mail_theme.soft') }};">Politique de confidentialité</a>
    </x-slot:footer>
</x-mail.layout>
