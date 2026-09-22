{{-- $order, $user --}}
<x-mail.layout
    locale="en"
    title="Order cancelled — Hirfaty"
    preheader="Order {{ $order->reference }} was cancelled by the buyer.">

    <x-mail.eyebrow tone="danger">Order cancelled</x-mail.eyebrow>

    <x-mail.heading>The buyer has cancelled order {{ $order->reference }}.</x-mail.heading>

    <x-mail.text>
        Hello {{ $user->first_name }}, the buyer
        {{ $order->buyer->first_name }} {{ $order->buyer->last_name }}
        has cancelled their order. The inventory has been automatically restored.
    </x-mail.text>

    <x-mail.panel>
        <x-mail.row label="Order" :value="$order->reference" />
        <x-mail.row label="Buyer" :value="$order->buyer->first_name.' '.$order->buyer->last_name" />
        <x-mail.row label="Amount" :value="number_format($order->total_amount, 2).' MAD'" />
    </x-mail.panel>

    <x-mail.note tone="danger" title="Cancellation reason">
        {{ $order->cancellation_reason }}
    </x-mail.note>

    <x-mail.note>
        The inventory for the affected products has been automatically updated.
        No further action is required from you.
    </x-mail.note>

    <x-mail.button :url="config('app.url').'/seller/orders'">
        View my orders
    </x-mail.button>

    <x-slot:footer>
        <a href="{{ config('app.url') }}/seller/orders" style="color:{{ config('mail_theme.soft') }};">My orders</a>
    </x-slot:footer>
</x-mail.layout>
