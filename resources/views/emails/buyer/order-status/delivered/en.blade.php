{{-- $orders (Collection<Order>), $reference, $user --}}
@php
    $first = $orders->first();
    $many = $orders->count() > 1;

    $steps = [
        ['title' => 'Confirmed', 'body' => 'by '.($many ? 'the workshops' : 'the workshop').'.'],
        ['title' => 'Out for delivery', 'body' => ''],
        ['title' => 'Delivered', 'body' => 'to the address provided.'],
    ];

    /*
     * Reviews are submitted from one order's own page
     * (buyer.orders.reviews.store), so a single-workshop purchase links
     * straight there. A grouped purchase has no single page to send the buyer
     * to, so it falls back to the list.
     */
    $reviewUrl = config('app.url').'/orders'.($many ? '' : '/'.$first->reference);
@endphp

<x-mail.layout
    locale="en"
    title="Order delivered — Hirfaty"
    preheader="Your order {{ $reference }} has arrived.">

    <x-mail.eyebrow>It has arrived</x-mail.eyebrow>

    <x-mail.heading>Your order has arrived.</x-mail.heading>

    <x-mail.text>
        @if ($many)
            Hello {{ $user->first_name }}, your order {{ $reference }}, bringing
            together {{ $orders->count() }} workshops, has been delivered. We hope
            you love it as much as we enjoyed bringing it to you.
        @else
            Hello {{ $user->first_name }}, your order {{ $reference }} from
            {{ $first->store->store_name }} has been delivered. We hope you love it
            as much as we enjoyed bringing it to you.
        @endif
    </x-mail.text>

    <x-mail.panel>
        <x-mail.row label="Order number" :value="$reference" />
        <x-mail.row label="Delivered on" :value="optional($first->delivered_at)->format('d/m/Y')" />
    </x-mail.panel>

    {{-- One block per workshop, so an order delivered from several sellers
         still reads as one purchase. --}}
    @foreach ($orders as $order)
        <x-mail.items
            :store="$order->store->store_name"
            :reference="$order->reference"
            :items="$order->items"
            :labels="['product' => 'Product', 'qty' => 'Qty', 'price' => 'Price']" />
    @endforeach

    <x-mail.button :url="$reviewUrl">
        Leave a review
    </x-mail.button>

    <x-mail.divider />

    <x-mail.steps title="Where your order stands" :steps="$steps" />

    <x-slot:footer>
        Any issue with this order? Contact us from your orders page.<br>
        <a href="{{ config('app.url') }}/orders" style="color:{{ config('mail_theme.soft') }};">My orders</a>
        &nbsp;·&nbsp;
        <a href="{{ route('privacy', ['locale' => 'en']) }}" style="color:{{ config('mail_theme.soft') }};">Privacy Policy</a>
    </x-slot:footer>
</x-mail.layout>
