<?php

namespace App\Http\Middleware;

use App\Services\SeoService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends `X-Robots-Tag: noindex, nofollow` on every response from a copy of
 * the site that must not be indexed (local, staging).
 *
 * The <meta name="robots"> tag only reaches HTML pages; this header also
 * covers JSON, downloads and anything else Laravel serves, and is honoured
 * even when a crawler ignores robots.txt.
 */
class NoIndexOutsideProduction
{
    public function __construct(private SeoService $seo) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->seo->indexingAllowed($request)) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
