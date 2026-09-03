<?php

namespace App\Http\Controllers;

use App\Support\OmrConfig;
use App\Support\Omr\OmrCachedClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MenuController extends Controller
{
    private const CACHE_TTL_SUCCESS_DAYS = 7;
    private const CACHE_TTL_EMPTY_MINUTES = 10;
    private const CACHE_TTL_ERROR_MINUTES = 10;

    private static function localizedLabel(array $item, string $locale): string
    {
        $locale = strtolower($locale);
        $lang = explode('-', $locale)[0];

        $translations = is_array($item['translations'] ?? null)
            ? $item['translations']
            : [];

        $translation = collect($translations)->first(function ($translation) use ($locale) {
            return strtolower((string) ($translation['language_code'] ?? '')) === $locale;
        }) ?: collect($translations)->first(function ($translation) use ($lang) {
            return str_starts_with(strtolower((string) ($translation['language_code'] ?? '')), $lang);
        });

        return (string) (
            $translation['label']
            ?? $translation['name']
            ?? $item['label']
            ?? $item['name']
            ?? ''
        );
    }

    private static function sanitizeMenuItems(array $items, string $locale): array
    {
        return collect($items)
            ->map(function ($item) use ($locale) {
                if (! is_array($item)) {
                    return null;
                }

                $children = is_array($item['children'] ?? null)
                    ? self::sanitizeMenuItems($item['children'], $locale)
                    : [];

                return array_filter([
                    'id' => $item['id'] ?? null,
                    'label' => self::localizedLabel($item, $locale),
                    'url' => $item['url'] ?? null,
                    'target' => $item['target'] ?? null,
                    'order' => $item['order'] ?? null,
                    'children' => $children,
                ], fn ($value) => $value !== null && $value !== '' && $value !== []);
            })
            ->filter()
            ->sortBy('order')
            ->values()
            ->all();
    }

    private static function normalizeMenuData(?array $json): array
    {
        if (! is_array($json)) {
            return [];
        }

        $data =
            $json['data']['items'] ??
            $json['data'] ??
            $json['items'] ??
            [];

        return is_array($data) ? $data : [];
    }

    private static function cacheKey(string $type, string $tenant, string $locale): string
    {
        return "omr_menu_{$tenant}_{$locale}_{$type}";
    }

    private static function fetchMenuFromApi(string $type, string $locale, string $mainTenant, string $cacheKey): array
    {
        $res = OmrCachedClient::get('menus', "menus/{$type}", [
            'locale' => $locale,
        ], $mainTenant, [
            'connect_timeout' => 2,
            'timeout' => 5,
            'success_ttl' => now()->addDays(self::CACHE_TTL_SUCCESS_DAYS),
            'stale_ttl' => now()->addDays(14),
            'failure_ttl' => now()->addMinutes(self::CACHE_TTL_ERROR_MINUTES),
            'rate_limit_ttl' => now()->addMinutes(30),
            'cooldown_minutes' => 15,
        ]);

        if ($res['ok']) {
            $data = self::normalizeMenuData($res['json'] ?? []);

            if (! empty($data)) {
                Cache::put($cacheKey, $data, now()->addDays(self::CACHE_TTL_SUCCESS_DAYS));

                return $data;
            }

            Cache::put($cacheKey, [], now()->addMinutes(self::CACHE_TTL_EMPTY_MINUTES));

            Log::warning('Menu API returned empty data', [
                'type' => $type,
                'tenant' => $mainTenant,
                'locale' => $locale,
                'status' => $res['status'] ?? null,
            ]);

            return [];
        }

        $errorTtl = ($res['status'] ?? null) === 429
            ? now()->addMinutes(30)
            : now()->addMinutes(self::CACHE_TTL_ERROR_MINUTES);

        Cache::put($cacheKey, [], $errorTtl);

        return [];
    }

    public static function fetchMenu(string $type, string $locale): array
    {
        $type = strtolower(trim($type));
        $locale = strtolower(trim($locale ?: OmrConfig::defaultLocale()));
        $mainTenant = OmrConfig::tenantForSharedContent();

        if (! preg_match('/^[a-z0-9_-]+$/', $type)) {
            Log::warning('Invalid menu type requested', [
                'type' => $type,
                'locale' => $locale,
                'tenant' => $mainTenant,
            ]);

            return [];
        }

        $cacheKey = self::cacheKey($type, $mainTenant, $locale);
        $missing = '__omr_menu_cache_missing__';

        $cached = Cache::get($cacheKey, $missing);

        if ($cached !== $missing) {
            return is_array($cached) ? $cached : [];
        }

        $lockKey = "{$cacheKey}_lock";

        try {
            return Cache::lock($lockKey, 10)->block(3, function () use ($type, $locale, $mainTenant, $cacheKey, $missing) {
                $cached = Cache::get($cacheKey, $missing);

                if ($cached !== $missing) {
                    return is_array($cached) ? $cached : [];
                }

                return self::fetchMenuFromApi($type, $locale, $mainTenant, $cacheKey);
            });
        } catch (\Throwable $e) {
            Log::warning('Menu cache lock failed, skipping API fetch', [
                'type' => $type,
                'tenant' => $mainTenant,
                'locale' => $locale,
                'error' => $e->getMessage(),
            ]);

            // Önemli: Lock alınamadığında API’ye kilitsiz gitme.
            // Yoğun trafikte bu 429’u tekrar büyütür.
            Cache::add($cacheKey, [], now()->addSeconds(60));

            return [];
        }
    }

    public static function getHeaderMenu(string $locale): array
    {
        return self::fetchMenu('header', $locale);
    }

    public static function getFooterMenu(string $locale): array
    {
        return self::fetchMenu('footer', $locale);
    }

    public static function getFrontendHeaderMenu(string $locale): array
    {
        return self::sanitizeMenuItems(self::getHeaderMenu($locale), $locale);
    }

    public static function getFrontendFooterMenu(string $locale): array
    {
        return self::sanitizeMenuItems(self::getFooterMenu($locale), $locale);
    }
}
