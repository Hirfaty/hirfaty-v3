{{-- $orders (Collection<Order>), $reference, $user --}}
@php
    /*
     * One checkout, however many workshops it touched — the buyer placed a
     * single order, so the totals shown are summed across every one
     * CheckoutService split it into.
     */
    $subtotal = $orders->sum(fn ($o) => (float) $o->total_amount);
    $delivery = $orders->sum(fn ($o) => (float) $o->delivery_fee);
    $first = $orders->first();
    $many = $orders->count() > 1;

    $steps = [
        ['title' => $many ? 'Each workshop prepares' : 'The workshop prepares', 'body' => 'your order and hands it over to us.'],
        ['title' => "You'll receive an email", 'body' => "with a tracking number as soon as it's out for delivery."],
        ['title' => 'Your order is delivered', 'body' => 'to the address provided.'],
    ];
@endphp

<x-mail.layout
    locale="en"
    title="Order confirmed — Hirfaty"
    preheader="Your order {{ $reference }} has been received.">

    <x-mail.eyebrow>Order confirmed</x-mail.eyebrow>

    <x-mail.heading>Your order is on its way.</x-mail.heading>

    <x-mail.text>
        Hello {{ $user->first_name }}, we've received your order.
        @if ($many)
            It touches {{ $orders->count() }} different workshops, so it's
            shown below workshop by workshop — each prepares its own part
            and we bring the whole order together before it goes out.
        @else
            The workshop will prepare it and you'll get an email as soon as
            it's on its way to you.
        @endif
    </x-mail.text>

    <x-mail.panel>
        <x-mail.row label="Order number" :value="$reference" />
        <x-mail.row label="Date" :value="$first->created_at->format('d/m/Y H:i')" />
        <x-mail.row label="Delivery to" :value="$first->shipping_address" />
        {{-- Cash on delivery is the only method checkout accepts
             (CheckoutRequest::PAYMENT_METHODS), so the buyer is told plainly
             that nothing has been charged yet. --}}
        <x-mail.row label="Payment" value="Cash on delivery" />
    </x-mail.panel>

    {{-- One block per workshop, so a purchase spanning several sellers still
         reads as everything the buyer bought. --}}
    @foreach ($orders as $order)
        <x-mail.items
            :store="$order->store->store_name"
            :reference="$order->reference"
            :items="$order->items"
            :labels="['product' => 'Product', 'qty' => 'Qty', 'price' => 'Price']" />
    @endforeach

    <x-mail.totals
        :lines="[
            ['label' => 'Subtotal', 'amount' => $subtotal],
            ['label' => 'Delivery', 'amount' => $delivery],
        ]"
        :total="['label' => 'Total to pay on delivery', 'amount' => $subtotal + $delivery]" />

    <x-mail.note title="Delivery">
        Your order will be delivered to the address above, usually
        within 3 to 7 days anywhere in Morocco. You'll receive a
        tracking number by email as soon as the parcel leaves.
    </x-mail.note>

    <x-mail.button :url="config('app.url').'/orders'">
        Track my order{{ $many ? 's' : '' }}
    </x-mail.button>

    <x-mail.divider />

    <x-mail.steps title="What happens next" :steps="$steps" />

    <x-slot:footer>
        Have a question? Contact us and we'll follow it up with the workshop.<br>
        <a href="{{ config('app.url') }}/orders" style="color:{{ config('mail_theme.soft') }};">My orders</a>
        &nbsp;·&nbsp;
        <a href="{{ route('privacy', ['locale' => 'en']) }}" style="color:{{ config('mail_theme.soft') }};">Privacy Policy</a>
    </x-slot:footer>
</x-mail.layout>
