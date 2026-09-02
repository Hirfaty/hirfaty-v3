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

        <link rel="preconnect" href="https://fonts.bunny.net">
        {{-- Bunny's own `family:400,500,700i` list syntax, NOT Google's
             `ital,wght@0,400;1,700` axis syntax: bunny accepts the Google form
             without erroring but silently serves only a couple of the requested
             faces, leaving the rest to be synthesised by the browser. --}}
        <link href="https://fonts.bunny.net/css?family=bodoni-moda:400,500,600,700,400i,500i,600i,700i|noto-naskh-arabic:400,500,600,700" rel="stylesheet" />

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>

        @routes
        @vite(['resources/js/app.tsx'])
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
