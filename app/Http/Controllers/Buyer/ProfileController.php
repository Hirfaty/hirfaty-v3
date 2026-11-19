<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\ProfileUpdateRequest;
use App\Services\BuyerAccountService;
use Illuminate\Http\RedirectResponse;

/**
 * The identity form on /account.
 *
 * Its own endpoint rather than the shared Settings\ProfileController, which
 * validates and writes the email — the one field this form must not touch.
 * There is no password half: shoppers sign in with a code and have none.
 */
class ProfileController extends Controller
{
    public function __construct(private BuyerAccountService $account) {}

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $this->account->updateProfile($request->user(), $request->validated());

        return back()->with('success', __('buyer.account.profile_saved'));
    }
}
