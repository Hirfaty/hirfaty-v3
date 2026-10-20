<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turning a blocked account away (`users.is_banned`, toggled by
 * Admin\UserController::ban).
 *
 * Enforced on every request by the EnsureAccountNotBanned middleware, so a
 * block takes effect on the blocked person's very next click — whatever door
 * they came in by (password, emailed code, Google/Facebook) and even if they
 * were already signed in when the admin pressed the button.
 *
 * The person is signed out and sent to `/account-blocked`, which tells them
 * they cannot use Hirfaty and how to reach the desk. That page is only shown
 * to a session carrying SESSION_KEY, so nobody lands on it by accident.
 */
class BlockedAccountService
{
    /** Set on the (fresh, signed-out) session of someone just turned away. */
    public const SESSION_KEY = 'account_blocked';

    /**
     * Sign the current visitor out and send them to the notice.
     *
     * 303 rather than 302: this can answer a PUT/PATCH/DELETE (an Inertia form
     * submitted from an open tab), and only a 303 makes the browser follow it
     * with a GET.
     */
    public function lockOut(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put(self::SESSION_KEY, true);

        if ($request->expectsJson()) {
            return response()->json(['message' => __('common.account_blocked.title')], 403);
        }

        return redirect()->route('account.blocked', status: 303);
    }

    /** Whether this session belongs to someone who was just turned away. */
    public function wasTurnedAway(Request $request): bool
    {
        return $request->user() === null
            && $request->session()->get(self::SESSION_KEY) === true;
    }
}
