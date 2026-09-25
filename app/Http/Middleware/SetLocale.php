<?php

namespace App\Http\Middleware;

use App\Services\LocaleService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;

/**
 * Settles the request's language.
 *
 * On a public page the URL decides (/ar/products → Arabic), and that language
 * is remembered in the session so the unprefixed private pages that follow —
 * cart, checkout, account — stay in the same language. Anywhere else the
 * remembered language decides, then the visitor's explicit choice, then the
 * browser's Accept-Language (LocaleService).
 */
class SetLocale
{
    public function __construct(private LocaleService $locales) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $route = $request->route();
        $fromUrl = $route?->parameter('locale');

        if ($this->locales->isSupported($fromUrl)) {
            $locale = $fromUrl;
            // Remembered for the private pages that follow — not a choice,
            // so it never overrides the browser at `/` (see LocaleService).
            $this->locales->remember($request, $locale);

            /*
             * Controllers never see the segment. Laravel passes route
             * parameters to controller methods positionally, so leaving it
             * in would hand "fr" to show(string $slug). URL::defaults below
             * puts it back into every URL generated from here on.
             */
            $route->forgetParameter('locale');
        } else {
            $locale = $this->locales->current($request);
        }

        App::setLocale($locale);
        URL::defaults(['locale' => $locale]);

        return $next($request);
    }
}
