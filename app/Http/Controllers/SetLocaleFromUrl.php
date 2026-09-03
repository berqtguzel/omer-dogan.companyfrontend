<?php

namespace App\Http\Middleware;

use App\Support\LocaleMapper;
use Closure;
use Illuminate\Http\Request;

class SetLocaleFromUrl
{
    public function handle(Request $request, Closure $next)
    {
        $locale = LocaleMapper::toWeb($request->route('locale'));

        if (! LocaleMapper::isSupportedWeb($locale)) {
            $locale = 'de';
        }

        session(['locale' => $locale]);

        return $next($request);
    }
}
