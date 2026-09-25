{{-- $user --}}
<x-mail.layout
    locale="en"
    title="Professional account active — Hirfaty"
    preheader="Your account now gets wholesale pricing.">

    <x-mail.eyebrow>Request approved</x-mail.eyebrow>

    <x-mail.heading>Your account is now professional.</x-mail.heading>

    <x-mail.text>
        Hello {{ $user->first_name }}, your request has been reviewed and approved by our team.
        Your account now gets wholesale pricing across Hirfaty.
    </x-mail.text>

    <x-mail.note title="What changes">
        Professional prices now show automatically on the products that have
        them. Some items carry a minimum order quantity — it's shown on each
        product page.
    </x-mail.note>

    <x-mail.button :url="route('products.index', ['locale' => 'en'])">
        Browse trade pricing
    </x-mail.button>

    <x-slot:footer>
        You're receiving this email because you requested a professional account on Hirfaty.<br>
        <a href="{{ route('products.index', ['locale' => 'en']) }}" style="color:{{ config('mail_theme.soft') }};">The market</a>
        &nbsp;·&nbsp;
        <a href="{{ config('app.url') }}/orders" style="color:{{ config('mail_theme.soft') }};">My orders</a>
    </x-slot:footer>
</x-mail.layout>
