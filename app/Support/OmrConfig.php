<?php

namespace App\Support;

/**
 * Tenant API / OMR ayarları — sadece config() kullanır (config:cache uyumlu).
 */
final class OmrConfig
{
    public static function tenantId(): string
    {
        return (string) (config('services.omr.tenant_id') ?: config('services.omr.tenant_id_fallback'));
    }

    public static function mainTenantId(): ?string
    {
        $m = config('services.omr.main_tenant');

        return ($m !== null && $m !== '') ? (string) $m : null;
    }

    /** API isteklerinde: ana marka tenant’ı varsa o, yoksa site tenant’ı. */
    public static function tenantForSharedContent(): string
    {
        return self::mainTenantId() ?: self::tenantId();
    }

    public static function dashboardSiteId(): ?string
    {
        $siteId = config('services.omr.dashboard_site_id') ?: config('services.api.site_id');

        return ($siteId !== null && $siteId !== '') ? (string) $siteId : null;
    }

    public static function baseUrl(): string
    {
        return rtrim((string) config('services.omr.base', 'https://omerdogan.de/api'), '/');
    }

    /** 1 veya 2 — tenant API yolu /api/v1 veya /api/v2. */
    public static function apiVersionNumber(): int
    {
        $v = (int) config('services.omr.api_version', 2);

        return $v < 1 ? 1 : ($v > 2 ? 2 : $v);
    }

    public static function apiVersionSegment(): string
    {
        return 'v'.self::apiVersionNumber();
    }

    /** Örn. base https://host/api + v2 + path → https://host/api/v2/menus/header */
    public static function apiUrl(string $path): string
    {
        $path = ltrim($path, '/');

        return self::baseUrl().'/'.self::apiVersionSegment().'/'.$path;
    }

    public static function defaultLocale(): string
    {
        return (string) config('services.omr.default_locale', 'de');
    }

    public static function connectTimeout(): int
    {
        return max(1, (int) config('services.omr.connect_timeout', 5));
    }

    public static function timeout(): int
    {
        return max(self::connectTimeout(), (int) config('services.omr.timeout', 20));
    }

    public static function retryCount(): int
    {
        return max(0, (int) config('services.omr.retry_count', 1));
    }

    public static function retrySleep(): int
    {
        return max(0, (int) config('services.omr.retry_sleep', 500));
    }

    public static function analyticsEnabled(): bool
    {
        return (bool) config('services.omr.analytics_enabled', false);
    }

    public static function pageTimingEnabled(): bool
    {
        return (bool) config('services.omr.page_timing_enabled', false);
    }

    public static function analyticsThrottleSeconds(): int
    {
        return max(0, (int) config('services.omr.analytics_throttle_seconds', 300));
    }

    public static function locationCategorySlug(): ?string
    {
        $value = config('services.omr.location_category_slug');

        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return trim((string) $value);
    }

    public static function locationParentId(): ?string
    {
        $value = config('services.omr.location_parent_id');

        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return trim((string) $value);
    }
}
