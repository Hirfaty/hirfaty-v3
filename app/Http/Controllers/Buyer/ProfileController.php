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
 * The password half of the page posts to `user-password.update` instead,
 * which already returns back() and needs nothing here.
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
