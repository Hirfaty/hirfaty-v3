{{-- $product, $user --}}
@php
    // `translated_name`, not `name`: Category::$casts makes `name` a per-locale
    // array, so echoing it raised "htmlspecialchars(): array given" and threw
    // the queued mail job — the seller never received this at all.
    $category = $product->category?->translated_name ?? 'No category';
    $price = $product->sale_price ?? $product->base_price;
@endphp

<x-mail.layout
    locale="en"
    title="Your product is now live — Hirfaty"
    preheader="{{ $product->name }} is now visible to every buyer.">

    <x-mail.eyebrow>Product approved</x-mail.eyebrow>

    <x-mail.heading>Your product is now live.</x-mail.heading>

    <x-mail.text>
        Hello {{ $user->first_name }}, your product has been reviewed and approved
        by our team. It is now visible to all buyers on Hirfaty.
    </x-mail.text>

    <x-mail.panel>
        <x-mail.row label="Product" :value="$product->name" />
        <x-mail.row label="Category" :value="$category" />
        <x-mail.row label="Workshop" :value="$product->store?->store_name" />
        <x-mail.row label="Price" :value="number_format($price, 2).' MAD'" />
    </x-mail.panel>

    <x-mail.button :url="config('app.url').'/products/'.$product->slug">
        View my product online
    </x-mail.button>

    <x-mail.divider />

    <x-mail.note title="Tip">
        Share your product link on social media to maximize visibility. The more
        views your product gets, the higher it ranks in Hirfaty search results.
    </x-mail.note>

    <x-slot:footer>
        You're receiving this because you're a seller on Hirfaty.<br>
        <a href="{{ config('app.url') }}/seller/products" style="color:{{ config('mail_theme.soft') }};">My products</a>
        &nbsp;·&nbsp;
        <a href="{{ config('app.url') }}/seller/dashboard" style="color:{{ config('mail_theme.soft') }};">My dashboard</a>
    </x-slot:footer>
</x-mail.layout>
