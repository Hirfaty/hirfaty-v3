{{-- $order, $user --}}
@php
    /*
     * Le détail que porte réellement la commande, plutôt qu'un seul montant
     * net à prendre au mot. La livraison n'apparaît que si c'est cette
     * commande qui porte les frais du panier — voir CheckoutService : un
     * panier réparti entre plusieurs ateliers les rattache à une seule
     * commande, les autres restant à 0.
     */
    $lines = [
        ['label' => 'Total commande', 'amount' => (float) $order->total_amount],
        ['label' => 'Commission Hirfaty (20%)', 'amount' => -(float) $order->platform_fee],
    ];

    if ((float) $order->delivery_fee > 0) {
        $lines[] = ['label' => 'Livraison', 'amount' => (float) $order->delivery_fee];
    }
@endphp

<x-mail.layout
    locale="fr"
    title="Nouvelle commande — Hirfaty"
    preheader="Commande {{ $order->reference }} à préparer.">

    <x-mail.eyebrow>Nouvelle commande · {{ $order->store->store_name }}</x-mail.eyebrow>

    <x-mail.heading>Vous avez une nouvelle commande.</x-mail.heading>

    <x-mail.text>
        Bonjour {{ $user->first_name }}, vous venez de recevoir une nouvelle commande.
        Préparez les pièces et confirmez-la — nous nous chargeons ensuite
        de la livraison à l'acheteur.
    </x-mail.text>

    <x-mail.panel>
        <x-mail.row label="Commande" :value="$order->reference" />
        <x-mail.row label="Acheteur" :value="$order->buyer->first_name.' '.$order->buyer->last_name" />
        <x-mail.row label="Date" :value="$order->created_at->format('d/m/Y à H:i')" />
        <x-mail.row label="Livraison à" :value="$order->shipping_address" />
    </x-mail.panel>

    <x-mail.items
        :items="$order->items"
        :labels="['product' => 'Produit', 'qty' => 'Qté', 'price' => 'Prix']" />

    <x-mail.totals
        :lines="$lines"
        :total="['label' => 'Vos gains sur cette commande', 'amount' => (float) $order->seller_earnings]" />

    <x-mail.note>
        Confirmez cette commande dès que possible pour que nous puissions la
        récupérer à l'atelier. Vos gains deviennent disponibles une fois la
        commande livrée.
    </x-mail.note>

    <x-mail.button :url="config('app.url').'/seller/orders/'.$order->reference">
        Voir et confirmer la commande
    </x-mail.button>

    <x-slot:footer>
        Vous recevez cet email car vous êtes vendeur sur Hirfaty.<br>
        <a href="{{ config('app.url') }}/seller/orders" style="color:{{ config('mail_theme.soft') }};">Mes commandes</a>
        &nbsp;·&nbsp;
        <a href="{{ config('app.url') }}/seller/dashboard" style="color:{{ config('mail_theme.soft') }};">Tableau de bord</a>
    </x-slot:footer>
</x-mail.layout>
