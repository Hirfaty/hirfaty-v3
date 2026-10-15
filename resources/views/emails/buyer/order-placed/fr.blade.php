{{-- $orders (Collection<Order>), $reference, $user --}}
@php
    /*
     * Un seul achat, même s'il touche plusieurs ateliers — les totaux affichés
     * sont donc la somme de toutes les commandes que CheckoutService en a
     * tirées.
     */
    $subtotal = $orders->sum(fn ($o) => (float) $o->total_amount);
    $delivery = $orders->sum(fn ($o) => (float) $o->delivery_fee);
    $first = $orders->first();
    $many = $orders->count() > 1;

    $steps = [
        ['title' => $many ? 'Chaque atelier prépare' : "L'atelier prépare", 'body' => 'votre commande et nous la remet.'],
        ['title' => 'Vous recevrez un email', 'body' => 'avec un numéro de suivi dès que la commande part en livraison.'],
        ['title' => 'Votre commande est livrée', 'body' => "à l'adresse indiquée."],
    ];
@endphp

<x-mail.layout
    locale="fr"
    title="Commande confirmée — Hirfaty"
    preheader="Votre commande {{ $reference }} est bien enregistrée.">

    <x-mail.eyebrow>Commande confirmée</x-mail.eyebrow>

    <x-mail.heading>Votre commande est en route.</x-mail.heading>

    <x-mail.text>
        Bonjour {{ $user->first_name }}, nous avons bien reçu votre commande.
        @if ($many)
            Elle concerne {{ $orders->count() }} ateliers différents, elle
            est donc détaillée ci-dessous atelier par atelier — chacun
            prépare sa part et nous rassemblons l'ensemble avant le départ.
        @else
            L'atelier va la préparer et vous recevrez un email dès qu'elle
            sera en route vers vous.
        @endif
    </x-mail.text>

    <x-mail.panel>
        <x-mail.row label="Numéro de commande" :value="$reference" />
        <x-mail.row label="Date" :value="$first->created_at->format('d/m/Y à H:i')" />
        <x-mail.row label="Livraison à" :value="$first->shipping_address" />
        {{-- Envoyé seulement une fois le paiement en ligne accepté
             (PaymentService) : rien à régler à la porte. --}}
        <x-mail.row label="Paiement" :value="$first->payment_method === \App\Models\Order::PAYMENT_PAYPAL
            ? 'Payée avec PayPal'
            : 'Payée par carte bancaire'" />
    </x-mail.panel>

    {{-- Un bloc par atelier, pour qu'un achat auprès de plusieurs vendeurs
         reste lisible comme un seul et même achat. --}}
    @foreach ($orders as $order)
        <x-mail.items
            :store="$order->store->store_name"
            :reference="$order->reference"
            :items="$order->items"
            :labels="['product' => 'Produit', 'qty' => 'Qté', 'price' => 'Prix']" />
    @endforeach

    <x-mail.totals
        :lines="[
            ['label' => 'Sous-total', 'amount' => $subtotal],
            ['label' => 'Livraison', 'amount' => $delivery],
        ]"
        :total="['label' => 'Total payé', 'amount' => $subtotal + $delivery]" />

    <x-mail.note title="Livraison">
        Votre commande sera livrée à l'adresse indiquée ci-dessus,
        généralement sous 3 à 7 jours partout au Maroc. Vous recevrez un
        numéro de suivi par email dès le départ du colis.
    </x-mail.note>

    {{-- « mes commandes » au pluriel, pas « ma commandes » : l'ancienne
         version n'ajoutait qu'un s au nom en laissant le possessif au
         singulier. --}}
    <x-mail.button :url="config('app.url').'/orders'">
        {{ $many ? 'Suivre mes commandes' : 'Suivre ma commande' }}
    </x-mail.button>

    <x-mail.divider />

    <x-mail.steps title="Ce qui se passe ensuite" :steps="$steps" />

    <x-slot:footer>
        Vous avez une question ? Contactez-nous, nous faisons le lien avec l'atelier.<br>
        <a href="{{ config('app.url') }}/orders" style="color:{{ config('mail_theme.soft') }};">Mes commandes</a>
        &nbsp;·&nbsp;
        <a href="{{ route('privacy', ['locale' => 'fr']) }}" style="color:{{ config('mail_theme.soft') }};">Politique de confidentialité</a>
    </x-slot:footer>
</x-mail.layout>
