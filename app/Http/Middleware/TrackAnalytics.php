<?php

namespace App\Http\Middleware;

use Closure;

class TrackAnalytics
{
    public function handle($request, Closure $next)
    {
        return $next($request);
    }
}