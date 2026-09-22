{{-- $product, $user --}}
@php
    // `translated_name`, not `name`: Category::$casts makes `name` a per-locale
    // array, so echoing it raised "htmlspecialchars(): array given" and threw
    // the queued mail job — the seller never received this at all.
    $category = $product->category?->translated_name ?? 'No category';

    /*
     * Sellers neither file nor edit their own listings — the Hirfaty team does
     * it for them — so this asks them to get in touch rather than to go and
     * edit something they cannot edit.
     */
    $steps = [
        ['title' => 'Read carefully', 'body' => 'the rejection reason above.'],
        ['title' => 'Get in touch with our team', 'body' => 'with the corrected photos, price or description.'],
        ['title' => 'We republish the listing', 'body' => 'on your behalf once the points are settled.'],
    ];
@endphp

<x-mail.layout
    locale="en"
    title="Your product needs changes — Hirfaty"
    preheader="{{ $product->name }} cannot be published as it stands.">

    <x-mail.eyebrow tone="danger">Changes required</x-mail.eyebrow>

    <x-mail.heading>A few changes are needed.</x-mail.heading>

    <x-mail.text>
        Hello {{ $user->first_name }}, your product has been reviewed by our team.
        It cannot be published as it stands. Send us what's missing and
        we'll correct the listing for you.
    </x-mail.text>

    <x-mail.panel>
        <x-mail.row label="Product" :value="$product->name" />
        <x-mail.row label="Category" :value="$category" />
        <x-mail.row label="Workshop" :value="$product->store?->store_name" />
    </x-mail.panel>

    <x-mail.note tone="danger" title="Reason for rejection">
        {{ $product->rejection_reason }}
    </x-mail.note>

    <x-mail.steps title="How to fix this" :steps="$steps" />

    {{-- Points at the read-only record: the artisan cannot edit it themselves. --}}
    <x-mail.button tone="danger" :url="config('app.url').'/seller/products/'.$product->id">
        View the product
    </x-mail.button>

    <x-slot:footer>
        You're receiving this because you're a seller on Hirfaty.<br>
        <a href="{{ config('app.url') }}/seller/products" style="color:{{ config('mail_theme.soft') }};">My products</a>
        &nbsp;·&nbsp;
        <a href="{{ config('app.url') }}/seller/dashboard" style="color:{{ config('mail_theme.soft') }};">My dashboard</a>
    </x-slot:footer>
</x-mail.layout>
