<?php

use App\Http\Middleware\EnsureStoreCanSell;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The app sits behind the shared nginx proxy (docker-compose.proxy.yml)
        // and never binds a public port itself, so the only client it can ever
        // see is the proxy's container IP — trusting '*' is safe here and
        // avoids hardcoding a docker subnet that changes between networks.
        // Without this, TLS terminates at the proxy and the app would believe
        // every request is plain HTTP: assets, redirects and password-reset
        // links would all be generated as http://.
        $middleware->trustProxies(at: '*');

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'store.sellable' => EnsureStoreCanSell::class,
        ]);

        $middleware->web(append: [
            SetLocale::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * Render error pages through Inertia so a wrong URL lands on something
         * that looks like the rest of the site — and, more usefully, still
         * carries the header, the locale switcher and a way back to the
         * market. Laravel's stock page is an unstyled dead end, in English, on
         * an otherwise trilingual storefront.
         */
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            $status = $response->getStatusCode();

            /*
             * Inertia renders a *browser page*. A JSON caller asked for data
             * and must keep the plain response, or it gets an HTML document
             * it cannot parse.
             */
            if ($request->expectsJson()) {
                return $response;
            }

            /*
             * 404 and 403 render in every environment, `local` included.
             * Neither carries a stack trace worth reading, and hiding them
             * while developing would leave the page impossible to work on —
             * you would only ever see it in production.
             */
            if (in_array($status, [403, 404], true)) {
                return Inertia::render('error', ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            /*
             * 500/503 are the opposite case: the stack trace *is* the point
             * while developing, so Laravel's own detailed page wins locally
             * and under test. Only production gets the tidy version.
             */
            if (in_array($status, [500, 503], true) && ! app()->environment(['local', 'testing'])) {
                return Inertia::render('error', ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            return $response;
        });
    })->create();
