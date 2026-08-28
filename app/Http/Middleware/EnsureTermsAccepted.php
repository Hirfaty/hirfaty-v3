<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTermsAccepted
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->terms_accepted) {
            if (! $request->routeIs('terms.accept*')) {
                $request->session()->put('url.intended', $request->fullUrl());

                return redirect()->route('terms.accept');
            }
        }

        return $next($request);
    }
}
