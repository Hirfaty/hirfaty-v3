@php
    /*
     * One template for every verification email — buyer or seller, in any of
     * the three locales. It replaced six near-identical files whose CSS had
     * already drifted apart; the copy now lives in lang/<locale>/emails.php.
     *
     * This used to carry its own copy of the whole table shell — it was the
     * first template rebuilt for Outlook, before there was anything to share.
     * It now composes <x-mail.layout> like every other email, so there is one
     * shell to keep email-safe instead of three that drift.
     *
     * $audience  'buyer' | 'seller'
     * $user, $url, $locale, $expiresInMinutes
     */
    // Prefixes `emails.` only — the keys below already carry their `verify.`
    // segment, so prefixing that here too yields emails.verify.verify.*, which
    // __() cannot resolve and renders as the raw key.
    $t = fn (string $key, array $replace = []) => __("emails.{$key}", $replace, $locale);
    $copy = "verify.{$audience}";

    // Resolved here rather than inline in the attributes below: a Blade
    // component attribute is raw PHP, so a nested double-quoted string cannot
    // be written there without escaping that Blade does not decode.
    $subject = $t("{$copy}.subject");
    $preheader = $t("{$copy}.preheader");
    $nextTitle = $t("{$copy}.next_title");

    $steps = __("emails.{$copy}.steps", [], $locale);
@endphp

<x-mail.layout :locale="$locale" :title="$subject" :preheader="$preheader">

    <x-mail.eyebrow>{{ $t("{$copy}.eyebrow") }}</x-mail.eyebrow>

    <x-mail.heading>
        {{ $t('verify.greeting', ['name' => $user->first_name]) }}
        {{ $t("{$copy}.headline") }}
    </x-mail.heading>

    <x-mail.text>{{ $t("{$copy}.body") }}</x-mail.text>

    <x-mail.button :url="$url">
        {{ $t('verify.cta') }}
    </x-mail.button>

    <x-mail.text tight>
        {{ $t('verify.expires', ['minutes' => $expiresInMinutes]) }}
    </x-mail.text>

    <x-mail.divider />

    <x-mail.steps :title="$nextTitle" :steps="$steps" />

    <x-mail.fallback-link :url="$url" :label="$t('verify.fallback')" />

    <x-slot:footer>
        {{ $t('verify.ignore') }}<br>
        <a href="{{ route('privacy', ['locale' => $locale]) }}" style="color:{{ config('mail_theme.soft') }};">{{ $t('verify.privacy') }}</a>
        &nbsp;·&nbsp;
        <a href="{{ route('terms', ['locale' => $locale]) }}" style="color:{{ config('mail_theme.soft') }};">{{ $t('verify.terms') }}</a>
    </x-slot:footer>
</x-mail.layout>
