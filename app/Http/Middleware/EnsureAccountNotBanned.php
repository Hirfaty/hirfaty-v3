<?php

namespace App\Http\Middleware;

use App\Services\BlockedAccountService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps blocked accounts off the platform; see BlockedAccountService.
 *
 * Checked twice. Before the request, so an already-open session cannot do
 * anything more (check out, edit a profile) once the admin has blocked it.
 * And after, for the requests that sign someone in — Fortify's POST /login,
 * the emailed code — so a blocked person is turned away by the sign-in itself
 * instead of being let in for one page first.
 */
class EnsureAccountNotBanned
{
    public function __construct(private BlockedAccountService $accounts) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->is_banned) {
            return $this->accounts->lockOut($request);
        }

        $response = $next($request);

        if ($request->user()?->is_banned) {
            return $this->accounts->lockOut($request);
        }

        return $response;
    }
}
