<?php

namespace App\Http\Middleware;

use App\Services\MediaMirrorService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MirrorRemoteMedia
{
    public function __construct(private MediaMirrorService $mirror) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->mirror->enabled()) {
            return $response;
        }

        if ($request->header('X-Inertia')) {
            return $this->transformInertiaJson($response);
        }

        return $this->transformHtml($response);
    }

    private function transformInertiaJson(Response $response): Response
    {
        $content = $response->getContent();

        if (! is_string($content) || $content === '') {
            return $response;
        }

        $data = json_decode($content, true);

        if (! is_array($data) || ! isset($data['props'])) {
            return $response;
        }

        $data['props'] = $this->mirror->transform($data['props'], false);
        $response->setContent(json_encode($data));

        return $response;
    }

    private function transformHtml(Response $response): Response
    {
        $content = $response->getContent();

        if (! is_string($content) || ! str_contains($content, 'data-page=')) {
            return $response;
        }

        $content = (string) preg_replace_callback(
            '/data-page="([^"]+)"/',
            function (array $matches) {
                $json = html_entity_decode($matches[1], ENT_QUOTES);
                $data = json_decode($json, true);

                if (! is_array($data) || ! isset($data['props'])) {
                    return $matches[0];
                }

                $data['props'] = $this->mirror->transform($data['props'], false);

                return 'data-page="'.e(json_encode($data)).'"';
            },
            $content
        );

        $content = $this->mirror->rewriteHtml($content, false);
        $response->setContent($content);

        return $response;
    }
}
