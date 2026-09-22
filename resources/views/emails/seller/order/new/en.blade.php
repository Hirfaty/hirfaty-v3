{{-- $order, $user --}}
@php
    /*
     * The figures the order actually carries, rather than one net number the
     * seller has to take on trust. Delivery is listed only when this order is
     * the one the checkout's delivery fee landed on — see CheckoutService: a
     * basket split across workshops attaches it to a single order and leaves
     * the others at 0.
     */
    $lines = [
        ['label' => 'Order total', 'amount' => (float) $order->total_amount],
        ['label' => 'Hirfaty commission (20%)', 'amount' => -(float) $order->platform_fee],
    ];

    if ((float) $order->delivery_fee > 0) {
        $lines[] = ['label' => 'Delivery', 'amount' => (float) $order->delivery_fee];
    }
@endphp

<x-mail.layout
    locale="en"
    title="New order — Hirfaty"
    preheader="Order {{ $order->reference }} is waiting to be prepared.">

    <x-mail.eyebrow>New order · {{ $order->store->store_name }}</x-mail.eyebrow>

    <x-mail.heading>You have a new order.</x-mail.heading>

    <x-mail.text>
        Hello {{ $user->first_name }}, you just received a new order.
        Prepare the pieces and confirm it — we take care of the delivery
        to the buyer from there.
    </x-mail.text>

    <x-mail.panel>
        <x-mail.row label="Order" :value="$order->reference" />
        <x-mail.row label="Buyer" :value="$order->buyer->first_name.' '.$order->buyer->last_name" />
        <x-mail.row label="Date" :value="$order->created_at->format('d/m/Y H:i')" />
        <x-mail.row label="Deliver to" :value="$order->shipping_address" />
    </x-mail.panel>

    <x-mail.items
        :items="$order->items"
        :labels="['product' => 'Product', 'qty' => 'Qty', 'price' => 'Price']" />

    <x-mail.totals
        :lines="$lines"
        :total="['label' => 'Your earnings on this order', 'amount' => (float) $order->seller_earnings]" />

    <x-mail.note>
        Confirm this order as soon as possible so we can collect it from your
        workshop. Your earnings become available once it has been delivered.
    </x-mail.note>

    <x-mail.button :url="config('app.url').'/seller/orders/'.$order->reference">
        View and confirm order
    </x-mail.button>

    <x-slot:footer>
        You're receiving this because you're a seller on Hirfaty.<br>
        <a href="{{ config('app.url') }}/seller/orders" style="color:{{ config('mail_theme.soft') }};">My orders</a>
        &nbsp;·&nbsp;
        <a href="{{ config('app.url') }}/seller/dashboard" style="color:{{ config('mail_theme.soft') }};">Dashboard</a>
    </x-slot:footer>
</x-mail.layout>
