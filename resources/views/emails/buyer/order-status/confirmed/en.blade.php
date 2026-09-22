{{-- $orders (Collection<Order>), $reference, $user --}}
@php
    $first = $orders->first();
    $many = $orders->count() > 1;

    $steps = [
        ['title' => 'Confirmed', 'body' => 'by '.($many ? 'the workshops' : 'the workshop').'.'],
        ['title' => "You'll receive an email", 'body' => 'with a tracking number as soon as it leaves for delivery.'],
        ['title' => 'Your order is delivered', 'body' => 'to the address provided.'],
    ];
@endphp

<x-mail.layout
    locale="en"
    title="Order confirmed — Hirfaty"
    preheader="Your order {{ $reference }} is being prepared.">

    <x-mail.eyebrow>Progress on your order</x-mail.eyebrow>

    {{-- Generic rather than opening on the workshop's name: this email is
         about the order, not the seller's identity. --}}
    <x-mail.heading>Your order is being prepared.</x-mail.heading>

    @if ($many)
        <x-mail.text>
            Hello {{ $user->first_name }}, good news: all {{ $orders->count() }}
            workshops in your order {{ $reference }} have confirmed it and are
            getting it ready. You'll get another email, with a tracking number,
            as soon as it leaves for delivery.
        </x-mail.text>
    @else
        <x-mail.text>
            Hello {{ $user->first_name }}, good news: the workshop has confirmed
            your order {{ $reference }} and is getting it ready.
            You'll get another email, with a tracking number, as soon as it
            leaves for delivery.
        </x-mail.text>
    @endif

    <x-mail.panel>
        <x-mail.row label="Order number" :value="$reference" />
        <x-mail.row label="Delivery to" :value="$first->shipping_address" />
    </x-mail.panel>

    {{-- One block per workshop, so an order confirmed across several sellers
         still reads as one purchase. --}}
    @foreach ($orders as $order)
        <x-mail.items
            :store="$order->store->store_name"
            :reference="$order->reference"
            :items="$order->items"
            :labels="['product' => 'Product', 'qty' => 'Qty', 'price' => 'Price']" />
    @endforeach

    <x-mail.button :url="config('app.url').'/orders'">
        Track my order
    </x-mail.button>

    <x-mail.divider />

    <x-mail.steps title="Where your order stands" :steps="$steps" />

    <x-slot:footer>
        Have a question? Contact us from your orders page.<br>
        <a href="{{ config('app.url') }}/orders" style="color:{{ config('mail_theme.soft') }};">My orders</a>
        &nbsp;·&nbsp;
        <a href="{{ config('app.url') }}/privacy" style="color:{{ config('mail_theme.soft') }};">Privacy Policy</a>
    </x-slot:footer>
</x-mail.layout>
