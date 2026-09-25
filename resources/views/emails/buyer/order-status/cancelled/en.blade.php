{{-- $orders (Collection<Order>), $reference, $user --}}
@php
    $first = $orders->first();
    $many = $orders->count() > 1;
@endphp

<x-mail.layout
    locale="en"
    title="Order cancelled — Hirfaty"
    preheader="Your order {{ $reference }} has been cancelled.">

    <x-mail.eyebrow tone="danger">We're sorry</x-mail.eyebrow>

    <x-mail.heading>Your order has been cancelled.</x-mail.heading>

    <x-mail.text>
        {{-- Deliberately does not name who cancelled: this same email goes out
             when the buyer cancels from their own orders page
             (Buyer\OrderController::cancel), where "the workshop has cancelled
             your order" would be plainly wrong. --}}
        @if ($many)
            Hello {{ $user->first_name }}, your order {{ $reference }}, covering
            {{ $orders->count() }} workshops, has been cancelled. Nothing has
            been charged — you only ever pay on delivery.
        @else
            Hello {{ $user->first_name }}, your order {{ $reference }} from
            {{ $first->store->store_name }} has been cancelled. Nothing
            has been charged — you only ever pay on delivery.
        @endif
    </x-mail.text>

    <x-mail.panel>
        <x-mail.row label="Order number" :value="$reference" />
    </x-mail.panel>

    {{-- One block per workshop involved, so a cancellation touching several
         sellers still reads as one purchase. --}}
    @if ($many)
        @foreach ($orders as $order)
            <x-mail.items
                :store="$order->store->store_name"
                :reference="$order->reference"
                :items="$order->items"
                :labels="['product' => 'Product', 'qty' => 'Qty', 'price' => 'Price']" />
        @endforeach
    @endif

    @if ($first->cancellation_reason)
        <x-mail.note tone="danger" title="Reason given">
            {{ $first->cancellation_reason }}
        </x-mail.note>
    @endif

    <x-mail.button :url="route('products.index', ['locale' => 'en'])">
        Continue shopping
    </x-mail.button>

    <x-slot:footer>
        Questions about this cancellation? Contact us from your orders page.<br>
        <a href="{{ config('app.url') }}/orders" style="color:{{ config('mail_theme.soft') }};">My orders</a>
        &nbsp;·&nbsp;
        <a href="{{ route('privacy', ['locale' => 'en']) }}" style="color:{{ config('mail_theme.soft') }};">Privacy Policy</a>
    </x-slot:footer>
</x-mail.layout>
