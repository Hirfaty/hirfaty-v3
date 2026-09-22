{{-- $user, $reason --}}
<x-mail.layout
    locale="en"
    title="Your professional account request — Hirfaty"
    preheader="Your request has been reviewed by our team.">

    <x-mail.eyebrow tone="danger">Request reviewed</x-mail.eyebrow>

    <x-mail.heading>We can't approve it just yet.</x-mail.heading>

    <x-mail.text>
        Hello {{ $user->first_name }}, our team has reviewed your professional account
        request. We weren't able to approve it as it stands.
    </x-mail.text>

    <x-mail.note tone="danger" title="Reason">
        {{ $reason }}
    </x-mail.note>

    <x-mail.text>
        Your account stays fully active and you can carry on ordering as normal. You're
        welcome to submit a new request once things change.
    </x-mail.text>

    <x-mail.button :url="config('app.url').'/products'">
        Back to the market
    </x-mail.button>

    <x-slot:footer>
        You're receiving this email because you requested a professional account on Hirfaty.<br>
        <a href="{{ config('app.url') }}/products" style="color:{{ config('mail_theme.soft') }};">The market</a>
        &nbsp;·&nbsp;
        <a href="{{ config('app.url') }}/orders" style="color:{{ config('mail_theme.soft') }};">My orders</a>
    </x-slot:footer>
</x-mail.layout>
