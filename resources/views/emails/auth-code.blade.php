@php
    /*
     * The 6-digit code email — one template for both purposes and all three
     * locales, copy from lang/<locale>/emails.php.
     *
     * `$copy` selects the block: 'registration_code' when the address has no
     * account yet, 'login_code' when it does and the visitor chose a code over
     * their password. One template rather than two because the layout is
     * identical and only the words differ.
     *
     * This used to carry its own copy of the whole table shell. It now
     * composes <x-mail.layout> like every other email, so there is one shell
     * to keep email-safe instead of three that drift.
     *
     * $url is optional and only the signup code sends one: a one-click
     * "confirm my email" button above the code, so nobody has to switch back
     * to the tab they came from and type six digits. The sign-in code has no
     * button — a link that signs straight into an existing account is a
     * different security bargain from one that confirms an address. The
     * `button` key therefore exists in the registration_code block only.
     *
     * $code, $locale, $expiresInMinutes, $copy, $url
     */
    $t = fn (string $key, array $replace = []) => __("emails.{$copy}.{$key}", $replace, $locale);
    $url ??= null;
@endphp

<x-mail.layout
    :locale="$locale"
    :title="$t('subject')"
    {{-- The preview line carries the code itself: most people can finish the
         step straight from the notification without opening the mail. --}}
    :preheader="$t('preheader', ['code' => $code])">

    <x-mail.eyebrow>{{ $t('eyebrow') }}</x-mail.eyebrow>

    <x-mail.heading>{{ $t('headline') }}</x-mail.heading>

    <x-mail.text>{{ $t('body') }}</x-mail.text>

    @if ($url)
        <x-mail.button :url="$url">{{ $t('button') }}</x-mail.button>
    @endif

    <x-mail.code
        :code="$code"
        :note="$t('expires', ['minutes' => $expiresInMinutes])" />

    <x-mail.divider />

    <x-mail.text tight>{{ $t('ignore') }}</x-mail.text>

    <x-slot:footer>
        <a href="{{ route('privacy', ['locale' => $locale]) }}" style="color:{{ config('mail_theme.soft') }};">{{ $t('privacy') }}</a>
        &nbsp;·&nbsp;
        <a href="{{ route('terms', ['locale' => $locale]) }}" style="color:{{ config('mail_theme.soft') }};">{{ $t('terms') }}</a>
    </x-slot:footer>
</x-mail.layout>
