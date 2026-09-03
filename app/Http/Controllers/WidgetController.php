<?php

namespace App\Http\Controllers;

use App\Support\MediaUrl;
use App\Support\OmrConfig;
use App\Support\Omr\OmrCachedClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WidgetController extends Controller
{
    private const CACHE_TTL_SUCCESS_DAYS = 7;
    private const CACHE_TTL_ERROR_MINUTES = 5;

    private static function emptyWidgets(): array
    {
        return [
            'whatsapp' => [],
            'ratings' => [],
            'highlights' => [],
            'service_highlights' => [],
        ];
    }

    private static function cooldownKey(string $tenant): string
    {
        return 'omr_rate_limited_' . $tenant;
    }

    private static function isCoolingDown(string $tenant): bool
    {
        return OmrCachedClient::isCoolingDown($tenant);
    }

    private static function markCoolingDown(string $tenant, int $minutes = 5): void
    {
        OmrCachedClient::markCoolingDown($tenant, $minutes);
    }

    private static function failureTtlByStatus(?int $status)
    {
        return match ($status) {
            429 => now()->addMinutes(5),
            404 => now()->addSeconds(60),
            default => now()->addMinutes(2),
        };
    }

    /**
     * Resim URL'indeki tenant ID'sini ana tenant ile değiştirir.
     */
    private static function replaceImageTenant(?string $imageUrl): ?string
    {
        if (! $imageUrl) {
            return null;
        }

        $mainTenant = OmrConfig::mainTenantId();

        if (! $mainTenant) {
            return $imageUrl;
        }

        $pattern = '/(\/storage\/)([^\/]+)(\/media\/)/';

        if (preg_match($pattern, $imageUrl, $matches)) {
            $currentTenant = $matches[2];

            if ($currentTenant === $mainTenant) {
                return $imageUrl;
            }

            return preg_replace($pattern, '$1' . $mainTenant . '$3', $imageUrl);
        }

        return $imageUrl;
    }

    private static function normalizeHighlightImage(array $item): array
    {
        $imageValue = $item['image_url']
            ?? $item['image']
            ?? data_get($item, 'media.url')
            ?? data_get($item, 'media.path')
            ?? data_get($item, 'media.id');

        $resolvedImage = MediaUrl::resolve($imageValue);

        if ($resolvedImage) {
            $item['image_url'] = $resolvedImage;
            $item['image'] = $resolvedImage;

            return $item;
        }

        if (isset($item['image']) && is_string($item['image'])) {
            $item['image'] = self::replaceImageTenant($item['image']);
        }

        if (isset($item['image_url']) && is_string($item['image_url'])) {
            $item['image_url'] = self::replaceImageTenant($item['image_url']);
        }

        return $item;
    }

    private static function fetchWidgetEndpoint(
        string $endpoint,
        string $name,
        string $mainTenant,
        string $locale
    ): array {
        if (self::isCoolingDown($mainTenant)) {
            return [
                'data' => [],
                'failed' => true,
                'rate_limited' => false,
                'status' => null,
            ];
        }

        $response = OmrCachedClient::get('widgets', $endpoint, [
            'locale' => $locale,
        ], $mainTenant, [
            'connect_timeout' => 2,
            'timeout' => 5,
            'success_ttl' => now()->addDays(self::CACHE_TTL_SUCCESS_DAYS),
            'stale_ttl' => now()->addDays(14),
            'failure_ttl' => now()->addMinutes(self::CACHE_TTL_ERROR_MINUTES),
            'rate_limit_ttl' => now()->addMinutes(5),
            'cooldown_minutes' => 15,
        ]);

        if (! $response['ok']) {
            return [
                'data' => [],
                'failed' => true,
                'rate_limited' => ($response['status'] ?? null) === 429,
                'status' => $response['status'] ?? null,
            ];
        }

        $data = data_get($response['json'] ?? [], 'data', []);

        return [
            'data' => is_array($data) ? $data : [],
            'failed' => false,
            'rate_limited' => false,
            'status' => $response['status'] ?? null,
        ];
    }

    private static function buildWidgets(string $mainTenant, string $locale, bool $includeRatings): array
    {
        if (self::isCoolingDown($mainTenant)) {
            return [
                'widgets' => self::emptyWidgets(),
                'has_failure' => true,
                'failed_status' => 429,
            ];
        }

        $hasFailure = false;
        $failedStatus = null;

        $whatsappResponse = self::fetchWidgetEndpoint(
            'widgets/whatsapp',
            'whatsapp',
            $mainTenant,
            $locale
        );

        $whatsapp = $whatsappResponse['data'];
        $hasFailure = $hasFailure || $whatsappResponse['failed'];
        $failedStatus = $whatsappResponse['failed'] ? ($whatsappResponse['status'] ?? $failedStatus) : $failedStatus;

        if ($whatsappResponse['rate_limited']) {
            return [
                'widgets' => array_merge(self::emptyWidgets(), [
                    'whatsapp' => $whatsapp,
                ]),
                'has_failure' => true,
                'failed_status' => 429,
            ];
        }

        $ratings = [];

        if ($includeRatings) {
            $ratingsResponse = self::fetchWidgetEndpoint(
                'widgets/ratings',
                'ratings',
                $mainTenant,
                $locale
            );

            $ratings = $ratingsResponse['data'];
            $hasFailure = $hasFailure || $ratingsResponse['failed'];
            $failedStatus = $ratingsResponse['failed'] ? ($ratingsResponse['status'] ?? $failedStatus) : $failedStatus;

            if ($ratingsResponse['rate_limited']) {
                return [
                    'widgets' => array_merge(self::emptyWidgets(), [
                        'whatsapp' => $whatsapp,
                        'ratings' => $ratings,
                    ]),
                    'has_failure' => true,
                    'failed_status' => 429,
                ];
            }
        }

        if (self::isCoolingDown($mainTenant)) {
            return [
                'widgets' => array_merge(self::emptyWidgets(), [
                    'whatsapp' => $whatsapp,
                    'ratings' => $ratings,
                ]),
                'has_failure' => true,
                'failed_status' => 429,
            ];
        }

        $highlightsResponse = self::fetchWidgetEndpoint(
            'widgets/service-highlights',
            'service-highlights',
            $mainTenant,
            $locale
        );

        $highlights = $highlightsResponse['data'];
        $hasFailure = $hasFailure || $highlightsResponse['failed'];
        $failedStatus = $highlightsResponse['failed'] ? ($highlightsResponse['status'] ?? $failedStatus) : $failedStatus;

        if ($highlightsResponse['rate_limited']) {
            return [
                'widgets' => array_merge(self::emptyWidgets(), [
                    'whatsapp' => $whatsapp,
                    'ratings' => $ratings,
                ]),
                'has_failure' => true,
                'failed_status' => 429,
            ];
        }

        $highlights = array_map(function ($item) {
            return is_array($item)
                ? self::normalizeHighlightImage($item)
                : $item;
        }, $highlights);

        return [
            'widgets' => array_merge(self::emptyWidgets(), [
                'whatsapp' => $whatsapp,
                'ratings' => $ratings,
                'highlights' => $highlights,
                'service_highlights' => $highlights,
            ]),
            'has_failure' => $hasFailure,
            'failed_status' => $failedStatus,
        ];
    }

    public static function getWidgets($tenant, $locale = 'de', bool $mirror = true, bool $includeRatings = true): array
    {
        if (! $tenant) {
            Log::error('Tenant ID missing for widgets');

            return self::emptyWidgets();
        }

        $locale = strtolower((string) $locale);
        $mainTenant = OmrConfig::tenantForSharedContent()
            ?: OmrConfig::mainTenantId()
            ?: $tenant;

        $mode = $includeRatings ? 'full' : 'frontend';

        $cacheKey = "widgets_v4_{$mode}_{$mainTenant}_{$locale}";
        $missing = '__widgets_cache_missing__';

        $cached = Cache::get($cacheKey, $missing);

        if ($cached !== $missing) {
            $widgets = is_array($cached)
                ? array_merge(self::emptyWidgets(), $cached)
                : self::emptyWidgets();

            return $mirror ? mirror_media($widgets) : $widgets;
        }

        if (self::isCoolingDown($mainTenant)) {
            $widgets = self::emptyWidgets();

            Cache::put($cacheKey, $widgets, now()->addMinutes(2));

            return $mirror ? mirror_media($widgets) : $widgets;
        }

        $lockKey = "{$cacheKey}_lock";

        try {
            $widgets = Cache::lock($lockKey, 10)->block(3, function () use (
                $cacheKey,
                $missing,
                $mainTenant,
                $locale,
                $includeRatings
            ) {
                $cachedAgain = Cache::get($cacheKey, $missing);

                if ($cachedAgain !== $missing) {
                    return is_array($cachedAgain)
                        ? array_merge(self::emptyWidgets(), $cachedAgain)
                        : self::emptyWidgets();
                }

                if (self::isCoolingDown($mainTenant)) {
                    $widgets = self::emptyWidgets();

                    Cache::put($cacheKey, $widgets, now()->addMinutes(2));

                    return $widgets;
                }

                $result = self::buildWidgets($mainTenant, $locale, $includeRatings);
                $widgets = array_merge(self::emptyWidgets(), $result['widgets']);

                $ttl = $result['has_failure']
                    ? self::failureTtlByStatus($result['failed_status'] ?? null)
                    : now()->addDays(self::CACHE_TTL_SUCCESS_DAYS);

                Cache::put($cacheKey, $widgets, $ttl);

                return $widgets;
            });
        } catch (\Throwable $e) {
            Log::warning('Widget cache lock failed, skipping API fetch', [
                'tenant' => $mainTenant,
                'locale' => $locale,
                'error' => $e->getMessage(),
            ]);

            // Önemli: Lock alınamazsa API’ye kilitsiz gitme.
            // Yoğun trafikte bu 429’u tekrar büyütür.
            $widgets = self::emptyWidgets();

            Cache::put($cacheKey, $widgets, now()->addMinutes(2));
        }

        return $mirror ? mirror_media($widgets) : $widgets;
    }

    public static function getFrontendWidgets($tenant, $locale = 'de'): array
    {
        // Frontend'de ratings kullanılmıyor; gereksiz API çağrısını kapatıyoruz.
        $widgets = self::getWidgets($tenant, $locale, true, false);

        return [
            'whatsapp' => self::compactWhatsapp($widgets['whatsapp'] ?? []),
            'highlights' => self::compactHighlights($widgets['highlights'] ?? [], $locale),
        ];
    }

    private static function compactWhatsapp(array $items): array
    {
        return collect($items)
            ->filter(fn ($item) => ! empty($item['is_active']))
            ->map(function ($item) {
                $imageUrl = $item['button_image_url']
                    ?? $item['logo_url']
                    ?? data_get($item, 'media.url');
                $resolvedImageUrl = MediaUrl::resolve($imageUrl);

                return [
                    'phone_number' => $item['phone_number'] ?? null,
                    'welcome_text' => $item['welcome_text'] ?? null,
                    'default_message' => $item['default_message'] ?? null,
                    'button_position' => $item['button_position'] ?? null,
                    'button_color' => $item['button_color'] ?? null,
                    'button_text_color' => $item['button_text_color'] ?? null,
                    'button_type' => $item['button_type'] ?? null,
                    'button_image_url' => $resolvedImageUrl,
                    'logo_url' => MediaUrl::resolve($item['logo_url'] ?? null) ?: $resolvedImageUrl,
                    'open_by_default' => (bool) ($item['open_by_default'] ?? false),
                    'is_active' => (bool) ($item['is_active'] ?? false),
                ];
            })
            ->values()
            ->all();
    }

    private static function compactHighlights(array $items, string $locale): array
    {
        $locale = strtolower($locale);
        $lang = explode('-', $locale)[0];

        return collect($items)
            ->filter(fn ($item) => ! isset($item['is_active']) || ! empty($item['is_active']))
            ->map(function ($item) use ($locale, $lang) {
                $translations = is_array($item['translations'] ?? null)
                    ? $item['translations']
                    : [];

                $translation = collect($translations)->first(function ($translation) use ($locale) {
                    return strtolower((string) ($translation['language_code'] ?? '')) === $locale;
                }) ?: collect($translations)->first(function ($translation) use ($lang) {
                    return str_starts_with(strtolower((string) ($translation['language_code'] ?? '')), $lang);
                }) ?: collect($translations)->first();

                $imageUrl = $item['image_url']
                    ?? $item['image']
                    ?? data_get($item, 'media.url')
                    ?? data_get($item, 'media.path')
                    ?? data_get($item, 'media.id');

                return [
                    'id' => $item['id'] ?? null,
                    'name' => $translation['name'] ?? $item['name'] ?? null,
                    'description' => $translation['description'] ?? $item['description'] ?? null,
                    'image_url' => MediaUrl::resolve($imageUrl),
                    'sort' => $item['sort'] ?? $item['order'] ?? null,
                ];
            })
            ->sortBy('sort')
            ->values()
            ->all();
    }
}
