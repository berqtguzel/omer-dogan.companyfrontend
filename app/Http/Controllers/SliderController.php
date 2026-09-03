<?php

namespace App\Http\Controllers;

use App\Support\MediaUrl;
use App\Support\Omr\OmrCachedClient;
use App\Support\OmrConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SliderController extends Controller
{
    public static function getSliders($tenant, string $locale = 'de', bool $mirror = true): array
    {
        if (!$tenant) {
            return [
                'sliders' => [],
                'meta'    => [],
            ];
        }

        $locale = strtolower($locale);
        $mainTenant = OmrConfig::mainTenantId() ?: $tenant;
        $cacheKey = "sliders_frontend_v4_media_{$tenant}_{$locale}";

        $sliders = Cache::remember($cacheKey, now()->addDays(30), function () use ($tenant, $mainTenant, $locale) {
            try {
                $response = OmrCachedClient::get('sliders', 'sliders', [
                    'lang' => $locale,
                    'locale' => $locale,
                ], $mainTenant, [
                    'connect_timeout' => 1,
                    'timeout' => 3,
                    'success_ttl' => now()->addDays(30),
                    'stale_ttl' => now()->addDays(120),
                    'failure_ttl' => now()->addMinutes(10),
                    'rate_limit_ttl' => now()->addMinutes(30),
                    'cooldown_minutes' => 30,
                ]);

                if (! ($response['ok'] ?? false)) {
                    Log::warning('Slider API non-200 response', [
                        'tenant'  => $tenant,
                        'status'  => $response['status'] ?? null,
                    ]);

                    return [
                        'sliders' => [],
                        'meta'    => ['status' => $response['status'] ?? null],
                    ];
                }

                $raw = data_get($response, 'json.data') ?? [];
                $data = is_array($raw) && isset($raw['data']) && is_array($raw['data'])
                    ? $raw['data']
                    : (is_array($raw) ? $raw : []);

                $sliders = collect($data)
                    ->map(function ($it, $i) {
                        $image = MediaUrl::resolve($it['image'] ?? null);
                        $videoUrl = MediaUrl::resolve($it['video_url'] ?? null);
                        $videoPoster = MediaUrl::resolve($it['video_poster'] ?? null);

                        if (!$videoPoster && $videoUrl && $image) {
                            $videoPoster = $image;
                        }

                        return [
                            'id'           => $it['id']             ?? $i,
                            'title'        => $it['title']          ?? '',
                            'description'  => $it['description']    ?? '',
                            'buttonLabel'  => $it['button_text']    ?? '',
                            'buttonUrl'    => $it['button_link']    ?? '#',
                            'order'        => $it['order']          ?? $i,
                            'image'        => $image,
                            'video_url'    => $videoUrl,
                            'video_poster' => $videoPoster,
                        ];
                    })
                    ->sortBy('order')
                    ->values();

                return [
                    'sliders' => $sliders,
                    'meta'    => [],
                ];
            } catch (\Throwable $e) {
                Log::error('❌ Slider API ERROR (Sessiz Fallback): ' . $e->getMessage());

                return [
                    'sliders' => [],
                    'meta'    => [
                        'error'   => true,
                        'message' => 'Slider data currently unavailable',
                    ],
                ];
            }
        });

        return $mirror ? mirror_media($sliders) : $sliders;
    }
}
