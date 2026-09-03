<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CompressResponse
{
    /**
     * Handle an incoming request.
     *  Response compression - Gzip/Brotli ile dosya boyutunu küçültür
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Sadece text/html, application/json, text/css, application/javascript için compress
        $contentType = $response->headers->get('Content-Type', '');
        $compressible = [
            'text/html',
            'application/json',
            'text/css',
            'application/javascript',
            'text/javascript',
            'application/xml',
            'text/xml',
        ];

        $shouldCompress = false;
        foreach ($compressible as $type) {
            if (str_contains($contentType, $type)) {
                $shouldCompress = true;
                break;
            }
        }

        $acceptedEncodings = strtolower((string) $request->header('Accept-Encoding', ''));

        if ($shouldCompress && !$response->headers->has('Content-Encoding')) {
            $content = $response->getContent();
            
            // Brotli öncelikli (daha iyi sıkıştırma)
            if (str_contains($acceptedEncodings, 'br') && function_exists('brotli_compress') && strlen($content) > 1024) {
                $compressed = brotli_compress($content, 4);
                if ($compressed !== false) {
                    $response->setContent($compressed);
                    $response->headers->set('Content-Encoding', 'br');
                    $response->headers->set('Vary', 'Accept-Encoding');
                }
            }
            // Gzip fallback
            elseif (str_contains($acceptedEncodings, 'gzip') && function_exists('gzencode') && strlen($content) > 1024) {
                $compressed = gzencode($content, 6);
                if ($compressed !== false) {
                    $response->setContent($compressed);
                    $response->headers->set('Content-Encoding', 'gzip');
                    $response->headers->set('Vary', 'Accept-Encoding');
                }
            }
        }

        return $response;
    }
}
