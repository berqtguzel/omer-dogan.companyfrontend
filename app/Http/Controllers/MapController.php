<?php

namespace App\Http\Controllers;

use App\Support\OmrConfig;
use App\Support\Omr\OmrCachedClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class MapController extends Controller
{
    private const DEFAULT_GEOJSON_URL = 'https://cdn.jsdelivr.net/gh/isellsoap/deutschlandGeoJSON@master/2_bundeslaender/4_niedrig.geo.json';

    private static function getGeoJsonUrl(): string
    {
        $tenant = strtolower(OmrConfig::mainTenantId() ?: OmrConfig::tenantId());

        $response = OmrCachedClient::get(
            'settings_map',
            'settings/map',
            ['tenant' => $tenant],
            $tenant,
            [
                'success_ttl' => now()->addDays(7),
                'stale_ttl' => now()->addDays(14),
                'failure_ttl' => now()->addMinutes(10),
                'rate_limit_ttl' => now()->addMinutes(15),
            ]
        );

        $url = data_get($response['json'] ?? [], 'data.value');

        return is_string($url) && filter_var($url, FILTER_VALIDATE_URL)
            ? $url
            : self::DEFAULT_GEOJSON_URL;
    }

    private static function makeSlug(string $text): string
    {
        $text = str_replace(['/', '\\'], '-', $text);
        $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        $text = preg_replace('/[^A-Za-z0-9]+/', '-', $text);

        return trim(strtolower($text), '-');
    }

    private static function normalizeRegion(?string $value): string
    {
        $value = strtolower(trim((string) $value));
        $value = str_replace(['-', '_', '/'], ' ', $value);

        return preg_replace('/\s+/', ' ', $value);
    }

    private static function normalizeSlug(?string $value): string
    {
        return self::makeSlug((string) $value);
    }

    private static function regionMatchesCategory(array $region, string $categorySlug): bool
    {
        if ($categorySlug === '') {
            return true;
        }

        $serviceSlug = self::normalizeSlug($region['service_slug'] ?? '');

        return $serviceSlug === $categorySlug
            || str_starts_with($serviceSlug, "{$categorySlug}-")
            || str_starts_with($serviceSlug, "{$categorySlug}-in-");
    }

    public static function getMaps($tenant, $locale = 'de')
    {
        if (!$tenant) {
            Log::error("❌ Tenant ID Eksik!");
            return [];
        }

        // Uygulamanın tek TENANT_ID değerini kullan.
        $mainTenant = OmrConfig::mainTenantId() ?: $tenant;
        $mainTenant = strtolower($mainTenant);
        $locale = strtolower($locale);
        $tenantDistrictRaw = (string) config('services.omr.tenant_district', '');
        $tenantCityRaw = (string) config('services.omr.tenant_city', '');
        $tenantDistrictKey = strtolower(trim($tenantDistrictRaw)) ?: 'all';
        $tenantCityKey = strtolower(trim($tenantCityRaw)) ?: 'all';
        $categorySlug = self::normalizeSlug(OmrConfig::locationCategorySlug() ?: 'gebaudereinigung');
        $locationParentId = OmrConfig::locationParentId();
        $locationParentKey = $locationParentId !== null ? preg_replace('/[^0-9A-Za-z_-]+/', '_', $locationParentId) : 'all';

        $cacheKey = "maps_v4_{$mainTenant}_{$locale}_{$categorySlug}_parent_{$locationParentKey}_{$tenantDistrictKey}_{$tenantCityKey}";

        return Cache::remember($cacheKey, now()->addHours(3), function () use ($mainTenant, $locale, $tenantDistrictRaw, $tenantCityRaw, $categorySlug, $locationParentId) {

            try {
                $query = [
                    'tenant' => $mainTenant,
                    'locale' => $locale,
                ];

                if ($locationParentId !== null) {
                    $query['parent_id'] = $locationParentId;
                }

                $response = Http::withoutVerifying()
                    ->connectTimeout(5)
                    ->timeout(20)
                    ->withHeaders([
                        'Accept' => 'application/json',
                        'X-Tenant-ID' => $mainTenant,
                    ])
                    ->get(OmrConfig::apiUrl('maps'), $query);

                if ($response->failed()) {
                    Log::error('❌ Maps API failed');
                    return [];
                }

                $data = $response->json('data', []);
                $map  = $data[0] ?? null;

                if (!$map) {
                    Log::warning('⚠️ Map boş döndü');
                    return [];
                }

                $regions = $map['map_data']['regions'] ?? [];

                $markers = collect($regions)
                    ->filter(fn ($r) => !empty($r['latitude']) && !empty($r['longitude']))
                    ->filter(fn ($r) => self::regionMatchesCategory($r, $categorySlug))
                    ->filter(function ($r) use ($locationParentId) {
                        if ($locationParentId === null || ! array_key_exists('parent_id', $r)) {
                            return true;
                        }

                        return (string) $r['parent_id'] === (string) $locationParentId;
                    })
                    ->map(fn ($r) => [
                        'id'        => $r['service_id'] ?? null,
                        'name'      => $r['city'] ?? null,
                        'slug'      => ! empty($r['city']) ? self::makeSlug((string) $r['city']) : ($r['service_slug'] ?? null),
                        'latitude'  => (float) $r['latitude'],
                        'longitude' => (float) $r['longitude'],
                        'district'  => self::normalizeRegion($r['district'] ?? ''),
                        'order'     => $r['order'] ?? 0,
                    ])
                    ->values();


                $tenantDistrict = self::normalizeRegion($tenantDistrictRaw);
                $tenantCity = !empty($tenantCityRaw) ? strtolower(trim($tenantCityRaw)) : null;

                // District filtresi
                if ($tenantDistrict !== '') {
                    $markers = $markers
                        ->filter(fn ($m) => $m['district'] === $tenantDistrict)
                        ->values();
                }

                // City filtresi (hem district hem city varsa ikisine de uymalı)
                if ($tenantCity !== null) {
                    $markers = $markers
                        ->filter(function ($m) use ($tenantCity) {
                            $markerCity = strtolower(trim($m['name'] ?? ''));
                            return $markerCity === $tenantCity;
                        })
                        ->values();
                }

                if (config('app.debug')) {
                    Log::info('🧪 MAP DEBUG', [
                        'tenant' => $mainTenant,
                        'district' => $tenantDistrict,
                        'city' => $tenantCity,
                        'marker_count' => $markers->count(),
                    ]);
                }

                return [
                    'map_type' => 'country',
                    'center' => [
                        (float) config('services.omr.center_lng', 9.5),
                        (float) config('services.omr.center_lat', 51.5),
                    ],
                    'scale' => (int) config('services.omr.scale_desktop', 2400),
                    'markers' => $markers->sortBy('order')->values(),
                ];

            } catch (\Throwable $e) {
                Log::error("❌ MapController ERROR: {$e->getMessage()}");
                return [];
            }
        });
    }

    public static function getFrontendMaps($tenant, $locale = 'de'): array
    {
        $maps = self::getMaps($tenant, $locale);
        $markers = $maps['markers'] ?? [];

        return [
            'map_type' => $maps['map_type'] ?? 'country',
            'geojson_url' => self::getGeoJsonUrl(),
            'center' => $maps['center'] ?? [
                (float) config('services.omr.center_lng', 9.5),
                (float) config('services.omr.center_lat', 51.5),
            ],
            'scale' => $maps['scale'] ?? (int) config('services.omr.scale_desktop', 2400),
            'markers' => collect($markers)
                ->map(fn ($marker) => [
                    'id' => $marker['id'] ?? null,
                    'name' => $marker['name'] ?? null,
                    'slug' => $marker['slug'] ?? self::makeSlug((string) ($marker['name'] ?? '')),
                    'latitude' => isset($marker['latitude']) ? (float) $marker['latitude'] : null,
                    'longitude' => isset($marker['longitude']) ? (float) $marker['longitude'] : null,
                ])
                ->filter(fn ($marker) => $marker['latitude'] !== null && $marker['longitude'] !== null)
                ->values()
                ->all(),
        ];
    }
}
