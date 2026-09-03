<?php

namespace App\Inertia;

use Illuminate\Support\Facades\Http;
use Inertia\Ssr\BundleDetector;
use Inertia\Ssr\Gateway;
use Inertia\Ssr\Response;

class TimeoutSsrGateway implements Gateway
{
    public function dispatch(array $page): ?Response
    {
        if (! config('inertia.ssr.enabled', true) || ! (new BundleDetector)->detect()) {
            return null;
        }

        $url = str_replace('/render', '', config('inertia.ssr.url', 'http://127.0.0.1:13714')).'/render';

        try {
            $response = Http::connectTimeout(5)
                ->timeout(20)
                ->post($url, $page)
                ->throw()
                ->json();
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($response)) {
            return null;
        }

        return new Response(
            implode("\n", $response['head'] ?? []),
            (string) ($response['body'] ?? '')
        );
    }
}
