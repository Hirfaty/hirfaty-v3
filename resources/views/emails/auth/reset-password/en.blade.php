{{-- $user, $url --}}
@php
    // The link's real TTL, rather than a hardcoded "60 minutes" that went
    // stale the moment the configuration changed.
    $expires = config('auth.passwords.users.expire', 60);
@endphp

<x-mail.layout
    locale="en"
    title="Reset your password — Hirfaty"
    preheader="The reset link expires in {{ $expires }} minutes.">

    <x-mail.eyebrow>Account security</x-mail.eyebrow>

    <x-mail.heading>Reset your password.</x-mail.heading>

    <x-mail.text>
        Hello {{ $user->first_name }}, we received a request to reset the
        password for your Hirfaty account.
        Click the button below to create a new password.
    </x-mail.text>

    <x-mail.button :url="$url">
        Reset my password
    </x-mail.button>

    <x-mail.text tight>
        This link expires in {{ $expires }} minutes.
    </x-mail.text>

    <x-mail.note>
        If you didn't request a password reset, ignore this email.
        Your password will remain unchanged and your account stays secure.
    </x-mail.note>

    <x-mail.fallback-link
        :url="$url"
        label="If the button doesn't work, copy this link into your browser:" />

    <x-slot:footer>
        <a href="{{ route('privacy', ['locale' => 'en']) }}" style="color:{{ config('mail_theme.soft') }};">Privacy Policy</a>
        &nbsp;·&nbsp;
        <a href="{{ route('terms', ['locale' => 'en']) }}" style="color:{{ config('mail_theme.soft') }};">Terms of Service</a>
    </x-slot:footer>
</x-mail.layout>
