{{-- $orders (Collection<Order>), $reference, $user --}}
@php
    /*
     * A shared tracking number usually means one physical parcel: Hirfaty's
     * desk collects the purchase from every workshop it touched and hands the
     * whole thing over for delivery as one job (see Admin\OrderController).
     * Listed in case the group's history carries more than one.
     */
    $trackingNumbers = $orders->pluck('tracking_number')->filter()->unique()->values();
    $first = $orders->first();
    $many = $orders->count() > 1;

    $steps = [
        ['title' => 'Confirmed', 'body' => 'by '.($many ? 'the workshops' : 'the workshop').'.'],
        ['title' => 'Out for delivery', 'body' => ''],
        ['title' => 'Being delivered', 'body' => 'to the address provided.'],
    ];
@endphp

<x-mail.layout
    locale="en"
    title="Order shipped — Hirfaty"
    preheader="Your order {{ $reference }} is out for delivery.">

    <x-mail.eyebrow>On its way to you</x-mail.eyebrow>

    <x-mail.heading>Your order is on its way.</x-mail.heading>

    <x-mail.text>
        @if ($many)
            Hello {{ $user->first_name }}, your order {{ $reference }} is out
            for delivery — each of the {{ $orders->count() }} workshops it
            involves has prepared their share.
        @else
            Hello {{ $user->first_name }}, your order {{ $reference }} from
            {{ $first->store->store_name }} is out for delivery.
        @endif
    </x-mail.text>

    <x-mail.panel>
        <x-mail.row label="Order number" :value="$reference" />
        @if ($trackingNumbers->count() === 1)
            <x-mail.row label="Tracking number" :value="$trackingNumbers->first()" />
        @endif
        <x-mail.row label="Delivery to" :value="$first->shipping_address" />
    </x-mail.panel>

    {{-- One block per workshop, so a grouped parcel still reads as one
         purchase. --}}
    @if ($many)
        @foreach ($orders as $order)
            <x-mail.items
                :store="$order->store->store_name"
                :reference="$order->reference.($order->tracking_number && $trackingNumbers->count() > 1 ? ' · '.$order->tracking_number : '')"
                :items="$order->items"
                :labels="['product' => 'Product', 'qty' => 'Qty', 'price' => 'Price']" />
        @endforeach
    @endif

    <x-mail.note title="Delivery">
        Delivery usually takes 3 to 7 days anywhere in Morocco. Keep your phone
        to hand — the courier calls before dropping the parcel off.
    </x-mail.note>

    <x-mail.button :url="config('app.url').'/orders'">
        Track my order
    </x-mail.button>

    <x-mail.divider />

    <x-mail.steps title="Where your order stands" :steps="$steps" />

    <x-slot:footer>
        Have a question? Contact us from your orders page.<br>
        <a href="{{ config('app.url') }}/orders" style="color:{{ config('mail_theme.soft') }};">My orders</a>
        &nbsp;·&nbsp;
        <a href="{{ route('privacy', ['locale' => 'en']) }}" style="color:{{ config('mail_theme.soft') }};">Privacy Policy</a>
    </x-slot:footer>
</x-mail.layout>
