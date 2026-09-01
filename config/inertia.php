<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Server Side Rendering
    |--------------------------------------------------------------------------
    |
    | These options configures if and how Inertia uses Server Side Rendering
    | to pre-render each initial request made to your application's pages
    | so that server rendered HTML is delivered for the user's browser.
    |
    | See: https://inertiajs.com/server-side-rendering
    |
    */

    // SSR is NOT set up in this project — there is no resources/js/ssr.tsx
    // entry, vite.config.ts declares no ssr input, and nothing runs an SSR
    // server (neither supervisord.conf nor any compose file). Leaving this
    // `true` was actively harmful:
    //
    //  - Local dev: Vite runs hot, and HttpGateway::dispatch() short-circuits
    //    its "does a bundle exist?" guard whenever hot (`! $isHot && ...`).
    //    So every full page load POSTed to the Vite dev server's
    //    /__inertia_ssr, which has no SSR entry to answer with, and hung
    //    until PHP hit max_execution_time — a 30s wait then a 500.
    //  - Production: harmless only by accident, because no bundle exists so
    //    dispatch() returns early. Switching the Dockerfile to
    //    `npm run build:ssr` would have silently armed the same trap there.
    //
    // Turn this back on only alongside an actual SSR entry point AND a
    // process running `php artisan inertia:start-ssr`.
    'ssr' => [
        'enabled' => false,
        'url' => 'http://127.0.0.1:13714',
        // 'bundle' => base_path('bootstrap/ssr/ssr.mjs'),

    ],

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | These options configure how Inertia discovers page components on the
    | filesystem. The paths and extensions are used to locate components
    | when rendering responses and during testing assertions.
    |
    */

    'pages' => [

        'paths' => [
            resource_path('js/pages'),
        ],

        'extensions' => [
            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Testing
    |--------------------------------------------------------------------------
    |
    | The values described here are used to locate Inertia components on the
    | filesystem. For instance, when using `assertInertia`, the assertion
    | attempts to locate the component as a file relative to the paths.
    |
    */

    'testing' => [

        'ensure_pages_exist' => true,

    ],

];
