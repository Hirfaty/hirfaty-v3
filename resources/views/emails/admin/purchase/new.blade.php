{{--
    The internal "a basket was bought" notice to Hirfaty's own desk.

    $orders (Collection<Order>), $buyer, $buyerName, $reference
--}}
@php
    /*
     * One checkout can span several workshops; the desk collects from each of
     * them, so the totals are summed across all of its orders.
     */
    $grandTotal = $orders->sum(fn ($o) => (float) $o->total_amount);
    $deliveryTotal = $orders->sum(fn ($o) => (float) $o->delivery_fee);
    $itemCount = $orders->sum(fn ($o) => $o->items->sum('quantity'));
    $first = $orders->first();
    $carrier = $first?->delivery_service_name ?? 'Amana';
@endphp

<x-mail.layout
    locale="fr"
    title="Nouvelle commande {{ $reference }} — Hirfaty"
    preheader="{{ $itemCount }} article(s) à collecter pour {{ $buyerName }}.">

    <x-mail.eyebrow>À traiter · Nouvelle commande</x-mail.eyebrow>

    <x-mail.heading>{{ $buyerName }} vient de passer commande.</x-mail.heading>

    <x-mail.text>
        {{ $itemCount }} article{{ $itemCount > 1 ? 's' : '' }}
        auprès de {{ $orders->count() }} atelier{{ $orders->count() > 1 ? 's' : '' }}.
        À collecter puis livrer à l’adresse ci-dessous.
    </x-mail.text>

    {{-- Qui et où. --}}
    <x-mail.panel>
        <x-mail.row label="Référence" :value="$reference" />
        <x-mail.row label="Client" :value="$buyerName" />
        @if ($buyer?->email)
            <x-mail.row label="E-mail" :value="$buyer->email" />
        @endif
        @if ($first?->shipping_phone)
            <x-mail.row label="Téléphone" :value="$first->shipping_phone" />
        @endif
        <x-mail.row label="Livraison à" :value="$first?->shipping_address" />
        @if ($first?->notes)
            <x-mail.row label="Note du client" :value="$first->notes" />
        @endif
    </x-mail.panel>

    {{-- Un bloc par atelier, pour que le bureau sache où collecter quoi. --}}
    @foreach ($orders as $order)
        <x-mail.items
            :store="$order->store?->store_name ?? 'Atelier inconnu'"
            :reference="$order->reference.($order->store?->city?->name ? ' · '.$order->store->city->name : '')"
            :items="$order->items"
            :labels="['product' => 'Produit', 'qty' => 'Qté', 'price' => 'Prix']" />
    @endforeach

    <x-mail.totals
        :lines="[
            ['label' => 'Produits', 'amount' => $grandTotal],
            ['label' => 'Livraison '.$carrier, 'amount' => $deliveryTotal],
        ]"
        :total="['label' => 'Total', 'amount' => $grandTotal + $deliveryTotal]" />

    <x-mail.button :url="config('app.url').'/admin/orders/'.$first?->reference">
        Ouvrir dans l’admin
    </x-mail.button>

    <x-slot:footer>
        Notification interne Hirfaty — envoyée à l’équipe qui gère les livraisons.
    </x-slot:footer>
</x-mail.layout>
