<?php

namespace App\Http\Middleware;

use Closure;

class SetLocale
{
    public function handle($request, Closure $next)
    {
        $locale = $request->route('locale')
            ?? session('locale', config('app.locale', 'de'));

        app()->setLocale($locale);
        session(['locale' => $locale]);

        return $next($request);
    }
}
