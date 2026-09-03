<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;

/**
 * Where a user lands right after clicking the link in their verification
 * email. Mirrors LoginResponse's per-role routing so the two "where do I end
 * up" moments stay consistent — a buyer's first real screen is the market,
 * a seller's is their shop (or its setup wizard, via the store.setup
 * middleware on seller.products.index if they haven't finished it yet).
 *
 * This previously lived in a file named VerifiedEmailResponse.php with a
 * class named VerifyEmailResponse — a filename/class mismatch that broke PSR-4
 * autoloading, so the class could never actually be loaded. On top of that,
 * FortifyServiceProvider bound the wrong container key (this class to itself,
 * rather than to Laravel\Fortify\Contracts\VerifyEmailResponse), so even a
 * working class would never have been consulted. Both are fixed together:
 * this file/class now match, and the binding targets the real contract.
 */
class VerifyEmailResponse implements VerifyEmailResponseContract
{
    public function toResponse($request)
    {
        $user = $request->user();

        if ($user->role === 'super_admin') {
            return redirect()->route('admin.products.index');
        }

        if ($user->role === 'seller') {
            return redirect()->route('seller.products.index');
        }

        return redirect()->route('products.index');
    }
}
