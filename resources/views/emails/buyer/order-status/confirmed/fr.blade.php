{{-- $orders (Collection<Order>), $reference, $user --}}
@php
    $first = $orders->first();
    $many = $orders->count() > 1;

    $steps = [
        ['title' => 'Confirmée', 'body' => 'par '.($many ? 'les ateliers' : "l'atelier").'.'],
        ['title' => 'Vous recevrez un email', 'body' => 'avec votre numéro de suivi dès le départ en livraison.'],
        ['title' => 'Votre commande est livrée', 'body' => "à l'adresse indiquée."],
    ];
@endphp

<x-mail.layout
    locale="fr"
    title="Commande confirmée — Hirfaty"
    preheader="Votre commande {{ $reference }} est en préparation.">

    <x-mail.eyebrow>Ça avance</x-mail.eyebrow>

    {{-- Générique plutôt que d'ouvrir sur le nom de l'atelier : le sujet de
         cet email est la commande, pas l'identité du vendeur. --}}
    <x-mail.heading>Votre commande est en préparation.</x-mail.heading>

    @if ($many)
        <x-mail.text>
            Bonjour {{ $user->first_name }}, bonne nouvelle : les {{ $orders->count() }}
            ateliers de votre commande {{ $reference }} l'ont confirmée et
            commencent à la préparer. Vous recevrez un email avec votre numéro
            de suivi dès qu'elle partira en livraison.
        </x-mail.text>
    @else
        <x-mail.text>
            Bonjour {{ $user->first_name }}, bonne nouvelle : l'atelier a confirmé
            votre commande {{ $reference }} et commence à la préparer.
            Vous recevrez un email avec votre numéro de suivi dès qu'elle
            partira en livraison.
        </x-mail.text>
    @endif

    <x-mail.panel>
        <x-mail.row label="Numéro de commande" :value="$reference" />
        <x-mail.row label="Livraison à" :value="$first->shipping_address" />
    </x-mail.panel>

    {{-- Un bloc par atelier, pour qu'une commande confirmée auprès de
         plusieurs vendeurs reste lisible comme un seul et même achat. --}}
    @foreach ($orders as $order)
        <x-mail.items
            :store="$order->store->store_name"
            :reference="$order->reference"
            :items="$order->items"
            :labels="['product' => 'Produit', 'qty' => 'Qté', 'price' => 'Prix']" />
    @endforeach

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
