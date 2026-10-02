<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="light" style="color-scheme: light;">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <style>
            html {
                background-color: oklch(1 0 0);
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        {{-- Didot is self-hosted (see the @font-face block in app.css). Preloaded
             because the browser would otherwise only discover it after parsing
             the stylesheet, and it still sets every large heading on the site —
             the titling cut (`--font-display` / `.font-display` / `h-editorial`)
             for big titles, the text cut wherever a component opts into it
             directly. Body copy itself now runs on the sans-serif below; see
             the `--font-sans` note in app.css for why the split. The bold and
             italic cuts are left to normal discovery: they appear far less
             often and preloading all four would just contend for bandwidth.

             Via Vite::asset, not a literal /fonts path: the files live under
             resources/ so Vite can fingerprint them, which means the built
             filename carries a hash. --}}
        @foreach (['didot-regular', 'didot-title'] as $cut)
            <link rel="preload" href="{{ Vite::asset("resources/fonts/didot/{$cut}.woff2") }}" as="font" type="font/woff2" crossorigin>
        @endforeach

        <link rel="preconnect" href="https://fonts.bunny.net">
        {{-- The body/UI sans (`--font-sans`) and, for the ar locale, Naskh
             (`--font-arabic` — Didot carries no Arabic glyphs at all, so ar is
             routed to Naskh everywhere in app.css, headings included).
             Loaded from Bunny rather than self-hosted, same as Naskh already
             was: neither is a licensed cut the way Didot is, so there is
             nothing to gain from vendoring the files.
             Bunny's own `family:400,500,700i` list syntax, NOT Google's
             `ital,wght@0,400;1,700` axis syntax: bunny accepts the Google form
             without erroring but silently serves only a couple of the requested
             faces, leaving the rest to be synthesised by the browser. --}}
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,400i&family=noto-naskh-arabic:400,500,600,700" rel="stylesheet" />

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @php
            // `$seo` is the closure a controller passed via withViewData();
            // pages that pass nothing get route defaults.
            $seoTags = app(\App\Services\SeoService::class)->resolve(request(), value($seo ?? []));
        @endphp
        <x-inertia::head>
            <title>{{ $seoTags['title'] }}</title>
        </x-inertia::head>
        @include('partials.seo', ['seo' => $seoTags])
        @include('partials.analytics')

        @routes
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
