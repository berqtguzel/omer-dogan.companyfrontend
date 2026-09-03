<?php

namespace App\Support;

use App\Support\Omr\OmrCachedClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class MediaUrl
{
    private const CACHE_MISSING = '__media_url_cache_missing__';

    public static function resolve(mixed $value): ?string
    {
        if (is_array($value)) {
            if (isset($value['url']) && is_string($value['url'])) {
                return self::replaceTenant($value['url']);
            }

            if (isset($value['id'])) {
                return self::fromId((string) $value['id']);
            }

            return null;
        }

        if (is_int($value) || is_float($value)) {
            return self::fromId((string) (int) $value);
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            return self::fromId($value);
        }

        return self::absoluteStorageUrl(self::replaceTenant($value));
    }

    private static function fromId(string $mediaId): ?string
    {
        if (! ctype_digit($mediaId)) {
            return null;
        }

        $tenant = OmrConfig::tenantForSharedContent()
            ?: OmrConfig::mainTenantId()
            ?: OmrConfig::tenantId();
        $cacheKey = "media_url_{$tenant}_{$mediaId}";
        $cached = Cache::get($cacheKey, self::CACHE_MISSING);

        if ($cached !== self::CACHE_MISSING) {
            return is_string($cached) && $cached !== '' ? $cached : null;
        }

        if (OmrCachedClient::isCoolingDown($tenant)) {
            return null;
        }

        try {
            $response = Http::withoutVerifying()
                ->connectTimeout(5)
                ->timeout(20)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-Tenant-ID' => $tenant,
                ])
                ->get(OmrConfig::apiUrl("media/{$mediaId}"), [
                    'tenant' => $tenant,
                ]);

            if ($response->successful()) {
                $url = $response->json('data.url') ?? $response->json('url');
                $resolved = is_string($url)
                    ? self::absoluteStorageUrl(self::replaceTenant($url))
                    : null;

                if ($resolved !== null && $resolved !== '') {
                    Cache::put($cacheKey, $resolved, now()->addDays(7));

                    return $resolved;
                }
            } elseif ($response->serverError() || $response->status() === 429) {
                OmrCachedClient::markCoolingDown($tenant, 2);
            }
        } catch (\Throwable $e) {
            OmrCachedClient::markCoolingDown($tenant, 2);

            Log::warning('Media URL resolve failed', [
                'media_id' => $mediaId,
                'error' => $e->getMessage(),
            ]);
        }

        Cache::put($cacheKey, false, now()->addMinutes(10));

        return null;
    }

    private static function replaceTenant(string $url): string
    {
        $mainTenant = OmrConfig::mainTenantId();

        if (! $mainTenant) {
            return $url;
        }

        $pattern = '/(\/storage\/)([^\/]+)(\/media\/)/';

        if (! preg_match($pattern, $url, $matches) || $matches[2] === $mainTenant) {
            return $url;
        }

        return preg_replace($pattern, '$1'.$mainTenant.'$3', $url) ?? $url;
    }

    private static function absoluteStorageUrl(string $url): string
    {
        $trimmed = trim($url);

        if ($trimmed === '' || preg_match('/^https?:\/\//i', $trimmed)) {
            return $trimmed;
        }

        if (str_starts_with($trimmed, '/storage/')) {
            return self::mediaBaseUrl().$trimmed;
        }

        if (str_starts_with($trimmed, 'storage/')) {
            return self::mediaBaseUrl().'/'.$trimmed;
        }

        return $trimmed;
    }

    private static function mediaBaseUrl(): string
    {
        return preg_replace('/\/api(?:\/?)$/', '', OmrConfig::baseUrl()) ?: 'https://omerdogan.de';
    }
}
