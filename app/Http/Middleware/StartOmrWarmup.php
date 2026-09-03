<?php

namespace App\Http\Middleware;

use App\Services\OmrAutomaticWarmup;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StartOmrWarmup
{
    public function __construct(private OmrAutomaticWarmup $warmup) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (
            ! app()->environment('testing')
            && $request->isMethod('GET')
            && str_contains(strtolower((string) $request->header('Accept')), 'text/html')
            && $response->getStatusCode() < 500
        ) {
            $this->warmup->scheduleAfterResponse();
        }

        return $response;
    }
}
