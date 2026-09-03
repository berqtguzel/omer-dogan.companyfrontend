<?php

namespace App\Http\Controllers;

use App\Support\OmrConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ButtonTrackingController extends Controller
{
    public function track(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'button_key' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_\-]+$/'],
            'session_id' => ['required', 'string', 'max:255'],
            'metadata' => ['required', 'array'],
            'metadata.page' => ['nullable', 'string', 'max:2000'],
            'metadata.url' => ['nullable', 'url', 'max:2000'],
            'metadata.locale' => ['nullable', 'string', 'max:20'],
            'metadata.referrer' => ['nullable', 'url', 'max:2000'],
            'metadata.user_agent' => ['nullable', 'string', 'max:1000'],
            'metadata.timestamp' => ['nullable', 'date'],
            'metadata.tenant_id' => ['nullable', 'string', 'max:100'],
            'metadata.button_name' => ['required', 'string', 'max:255'],
            'metadata.action' => ['required', 'string', 'max:100'],
            'metadata.location' => ['required', 'string', 'max:100'],
            'metadata.form' => ['nullable', 'string', 'max:100'],
            'metadata.element_id' => ['nullable', 'string', 'max:255'],
            'metadata.element_class' => ['nullable', 'string', 'max:1000'],
            'metadata.element_text' => ['nullable', 'string', 'max:500'],
            'metadata.element_type' => ['nullable', 'string', 'max:50'],
            'metadata.click_x' => ['nullable', 'integer', 'between:-100000,100000'],
            'metadata.click_y' => ['nullable', 'integer', 'between:-100000,100000'],
        ]);

        $tenant = OmrConfig::tenantId();
        $payload['metadata']['tenant_id'] = $tenant;
        $url = OmrConfig::apiUrl('button-tracking/track').'?'.http_build_query([
            'tenant' => $tenant,
        ]);

        try {
            $response = Http::withoutVerifying()
                ->connectTimeout(5)
                ->timeout(20)
                ->retry(
                    OmrConfig::retryCount(),
                    OmrConfig::retrySleep(),
                    throw: false,
                )
                ->acceptJson()
                ->withHeaders([
                    'X-Tenant-ID' => $tenant,
                    'User-Agent' => (string) $request->userAgent(),
                ])
                ->post($url, $payload);
        } catch (\Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'Button tracking is temporarily unavailable.',
            ], 503);
        }

        $body = $response->json();

        if (! is_array($body)) {
            $body = [
                'success' => $response->successful(),
                'message' => $response->successful()
                    ? 'Button click tracked.'
                    : 'Button tracking request failed.',
            ];
        }

        return response()->json($body, $response->status());
    }
}
