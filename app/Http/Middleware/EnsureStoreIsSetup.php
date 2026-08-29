<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStoreIsSetup
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->role === 'seller' && ! $user->store) {
            if (! $request->routeIs('seller.setup.store')) {
                return redirect()->route('seller.setup.store');
            }
        }

        return $next($request);
    }
}
