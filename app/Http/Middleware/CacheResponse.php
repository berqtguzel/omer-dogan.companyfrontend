<?php

namespace App\Http\Middleware;

use App\Services\TenantCanonicalUrlResolver;
use App\Support\OmrConfig;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CacheResponse
{
    /**
     * Handle an incoming request.
     *  Response cache - Sayfa cache'leme için
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        //  Inertia request'leri için cache yapma - Inertia kendi cache mekanizmasını kullanır
        if ($request->header('X-Inertia') || $request->header('X-Inertia-Version')) {
            return $next($request);
        }

        // Sadece GET istekleri için cache
        if ($request->method() !== 'GET') {
            return $next($request);
        }

        // Authenticated kullanıcılar için cache yapma
        if ($request->user()) {
            return $next($request);
        }

        //  Sadece HTML response'ları cache'le (Inertia JSON response'ları değil)
        // İlk request HTML döner, sonraki request'ler JSON döner
        // Cache key oluştur
        $canonicalScope = app(TenantCanonicalUrlResolver::class)->cacheScope();
        $locale = strtolower((string) ($request->route('locale') ?: session('locale', config('app.locale', 'de'))));
        $route = (string) optional($request->route())->getName();
        $cacheKey = 'response_' . hash('sha256', implode('|', [
            OmrConfig::tenantId(),
            $canonicalScope,
            $locale,
            $route,
            $request->getPathInfo(),
            $request->getQueryString() ?: '',
            $request->header('Accept-Language', 'de'),
        ]));

        // Response'u al
        $response = $next($request);

        // Sadece HTML response'ları cache'le (Inertia'nın ilk HTML response'u)
        if ($response->getStatusCode() === 200 && 
            $response instanceof Response && 
            !$request->expectsJson() &&
            !$request->header('X-Inertia')) {
            
            $content = $response->getContent();
            
            //  Sadece HTML içeren response'ları cache'le (Inertia JSON değil)
            if (str_contains($content, '<div id="app"') || 
                str_contains($content, '@inertia') ||
                str_contains($content, '<!DOCTYPE html>')) {
                
                //  Cache süresi: Home page için 10 dakika, diğer sayfalar için 5 dakika
                $ttl = $request->is('/') ? 600 : 300;
                
                Cache::put($cacheKey, [
                    'content' => $content,
                    'status' => $response->getStatusCode(),
                    'headers' => $response->headers->all(),
                ], now()->addSeconds($ttl));
            }
        }

        return $response;
    }
}
