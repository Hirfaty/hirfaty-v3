<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
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
