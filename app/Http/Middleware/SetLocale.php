<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class SetLocale
{
    public function handle(Request $request, Closure $next): mixed
    {
        $locale = Session::get('locale', config('app.locale'));

        if (in_array($locale, ['ar', 'fr', 'en'])) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
