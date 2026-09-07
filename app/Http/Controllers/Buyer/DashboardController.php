<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Services\BuyerAccountService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The buyer's account home — one of five sibling pages sharing the account
 * sidebar (see resources/js/layouts/buyer/account-layout.tsx).
 *
 * Holds who they are and where their orders go: name, contact details,
 * password and the one default delivery address. Account type lives on
 * /professional-account, orders and saved pieces on their own pages.
 */
class DashboardController extends Controller
{
    public function __construct(private BuyerAccountService $account) {}

    public function __invoke(Request $request)
    {
        $user = $request->user();

        return Inertia::render('buyer/account/index', [
            'buyer' => $this->account->identity($user),
            'defaultAddress' => $this->account->defaultAddress($user),
            'cities' => $this->account->cities(),
        ]);
    }
}
