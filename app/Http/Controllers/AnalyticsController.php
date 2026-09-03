<?php

namespace App\Http\Controllers;

use App\Support\OmrConfig;
use App\Support\Omr\OmrCachedClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AnalyticsController extends Controller
{
    private const ENDPOINTS = [
        'track',
        'page-timing',
        'track-conversion',
    ];

    public function store(Request $request, string $endpoint): JsonResponse
    {
        $endpoint = strtolower(trim($endpoint));

        abort_unless(in_array($endpoint, self::ENDPOINTS, true), 404);

        if (! OmrConfig::analyticsEnabled()) {
            return response()->json(['ok' => true, 'skipped' => true, 'drop' => true], 202);
        }

        if ($endpoint === 'page-timing' && ! OmrConfig::pageTimingEnabled()) {
            return response()->json(['ok' => true, 'skipped' => true, 'drop' => true], 202);
        }

        if ($this->isBot($request)) {
            return response()->json(['ok' => true, 'skipped' => true, 'drop' => true], 202);
        }

        $tenant = strtolower(OmrConfig::tenantId());

        if (OmrCachedClient::isCoolingDown($tenant)) {
            return response()->json(['ok' => false, 'retry' => true], 503);
        }

        $events = collect($request->input('events', []))
            ->filter(fn ($event) => is_array($event) && is_array($event['payload'] ?? null))
            ->take(100)
            ->values();

        if ($events->isEmpty()) {
            return response()->json([
                'ok' => false,
                'message' => 'At least one analytics event is required.',
            ], 422);
        }

        $batchId = Str::limit((string) $request->input('batch_id', ''), 100, '');
        $batchKey = $batchId !== '' ? 'omr_analytics_batch_'.sha1($tenant.'|'.$endpoint.'|'.$batchId) : null;

        if ($batchKey && Cache::has($batchKey)) {
            return response()->json([
                'ok' => true,
                'duplicate' => true,
                'accepted_ids' => $events->pluck('id')->filter()->values()->all(),
            ]);
        }

        $acceptedIds = [];
        $results = [];
        $visitorId = $request->input('visitor_id');
        $sessionId = $request->input('session_id');

        foreach ($events as $event) {
            $id = (string) ($event['id'] ?? '');
            $payload = $this->sanitizePayload($endpoint, $event['payload']);

            if ($visitorId && ! array_key_exists('visitor_id', $payload)) {
                $payload['visitor_id'] = $visitorId;
            }

            if ($sessionId && ! array_key_exists('session_id', $payload)) {
                $payload['session_id'] = $sessionId;
            }

            try {
                $response = Http::withoutVerifying()
                    ->connectTimeout(5)
                    ->timeout(20)
                    ->retry(OmrConfig::retryCount(), OmrConfig::retrySleep(), throw: false)
                    ->withHeaders([
                        'Accept' => 'application/json',
                        'X-Tenant-ID' => $tenant,
                        'User-Agent' => (string) $request->userAgent(),
                        'X-Forwarded-For' => (string) $request->ip(),
                        'X-Real-IP' => (string) $request->ip(),
                    ])
                    ->post(OmrConfig::apiUrl("analytics/{$endpoint}").'?'.http_build_query([
                        'tenant' => $tenant,
                    ]), $payload);

                $json = $response->json();
                $ok = $response->successful() && (bool) data_get($json, 'success', true);

                if ($ok) {
                    $acceptedIds[] = $id;
                    $visitorId = data_get($json, 'visitor_id', $visitorId);
                    $sessionId = data_get($json, 'session_id', $sessionId);
                }

                $results[] = [
                    'id' => $id,
                    'ok' => $ok,
                    'status' => $response->status(),
                ];
            } catch (\Throwable) {
                $results[] = [
                    'id' => $id,
                    'ok' => false,
                    'status' => null,
                ];
            }
        }

        if ($batchKey && count($acceptedIds) === $events->count()) {
            Cache::put($batchKey, true, now()->addDays(2));
        }

        return response()->json([
            'ok' => count($acceptedIds) === $events->count(),
            'accepted_ids' => $acceptedIds,
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
            'results' => $results,
        ]);
    }

    private function isBot(Request $request): bool
    {
        $userAgent = strtolower((string) $request->userAgent());

        return $userAgent !== ''
            && (bool) preg_match('/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|whatsapp|telegrambot|lighthouse/i', $userAgent);
    }

    private function sanitizePayload(string $endpoint, array $payload): array
    {
        $common = ['occurred_at', 'visitor_id', 'session_id'];
        $allowed = match ($endpoint) {
            'track' => [
                'event_type', 'event_name', 'page_url', 'url', 'page_title',
                'referrer', 'language',
            ],
            'page-timing' => [
                'page_url', 'load_time', 'dom_content_loaded', 'first_byte_time',
                'dns_time', 'connect_time', 'response_time',
            ],
            'track-conversion' => [
                'conversion_type', 'event_type', 'event_name', 'page_url',
                'metadata', 'value', 'currency',
            ],
            default => [],
        };

        return collect($payload)
            ->only([...$common, ...$allowed])
            ->map(function ($value) {
                if (is_string($value)) {
                    return Str::limit($value, 2000, '');
                }

                return $value;
            })
            ->all();
    }
}
