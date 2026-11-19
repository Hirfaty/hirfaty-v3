<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The basket and checkout pages: open to a visitor who has not signed in, and
 * to a shopper — but not to an artisan or an admin, who are turned away the
 * same way RoleMiddleware turns away a wrong role.
 *
 * Paying still needs an account: `checkout.store` stays behind `auth` and
 * `role:buyer`, and a visitor gets one by confirming their email or phone in
 * the checkout's contact box.
 */
class EnsureShopper
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isBuyer()) {
            return redirect()->route('products.index')
                ->with('error', __('You are not authorized to access that page.'));
        }

        return $next($request);
    }
}
