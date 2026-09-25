<?php

namespace App\Http\Responses;

use App\Services\PostLoginRedirectService;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $user = $request->user();

        // The page that sent them to sign in (a product they tried to add to
        // the cart, /cart itself…) beats the role's usual landing page.
        $intended = app(PostLoginRedirectService::class)->pull($request, $user);

        if ($intended !== null) {
            return redirect($intended);
        }

        if ($user->role === 'super_admin') {
            return redirect()->route('admin.products.index');
        }

        if ($user->role === 'seller') {
            return redirect()->route('seller.products.index');
        }

        return redirect()->route('products.index');
    }
}
