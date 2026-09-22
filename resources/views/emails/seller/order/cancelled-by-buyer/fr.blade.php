{{-- $order, $user --}}
<x-mail.layout
    locale="fr"
    title="Commande annulée — Hirfaty"
    preheader="La commande {{ $order->reference }} a été annulée par l'acheteur.">

    <x-mail.eyebrow tone="danger">Commande annulée</x-mail.eyebrow>

    <x-mail.heading>L'acheteur a annulé la commande {{ $order->reference }}.</x-mail.heading>

    <x-mail.text>
        Bonjour {{ $user->first_name }}, l'acheteur
        {{ $order->buyer->first_name }} {{ $order->buyer->last_name }}
        a annulé sa commande. Le stock a été automatiquement restauré.
    </x-mail.text>

    <x-mail.panel>
        <x-mail.row label="Commande" :value="$order->reference" />
        <x-mail.row label="Acheteur" :value="$order->buyer->first_name.' '.$order->buyer->last_name" />
        <x-mail.row label="Montant" :value="number_format($order->total_amount, 2).' MAD'" />
    </x-mail.panel>

    <x-mail.note tone="danger" title="Raison de l'annulation">
        {{ $order->cancellation_reason }}
    </x-mail.note>

    <x-mail.note>
        Le stock des produits concernés a été automatiquement remis à jour.
        Aucune action supplémentaire n'est requise de votre part.
    </x-mail.note>

    <x-mail.button :url="config('app.url').'/seller/orders'">
        Voir mes commandes
    </x-mail.button>

    <x-slot:footer>
        <a href="{{ config('app.url') }}/seller/orders" style="color:{{ config('mail_theme.soft') }};">Mes commandes</a>
    </x-slot:footer>
</x-mail.layout>
