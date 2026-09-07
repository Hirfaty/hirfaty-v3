<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Accepts one or more roles (`role:buyer,professional_buyer`) so routes open
     * to several roles don't need to be duplicated.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user()) {
            abort(403);
        }

        if (! in_array($request->user()->role, $roles, true)) {
            return redirect()->route('products.index')
                ->with('error', __('You are not authorized to access that page.'));
        }

        return $next($request);
    }
}
