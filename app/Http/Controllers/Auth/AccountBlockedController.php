<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\BlockedAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Your account cannot use Hirfaty" — where a blocked account is sent after
 * being signed out. Anyone else (a signed-in account, a visitor who typed the
 * URL) has no business here and goes to the market.
 */
class AccountBlockedController extends Controller
{
    public function __invoke(Request $request, BlockedAccountService $accounts): Response|RedirectResponse
    {
        if (! $accounts->wasTurnedAway($request)) {
            return to_route('products.index');
        }

        return Inertia::render('auth/account-blocked');
    }
}
