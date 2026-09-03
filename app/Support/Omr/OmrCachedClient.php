<?php

namespace App\Support\Omr;

use App\Support\OmrConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class OmrCachedClient
{
    private const MISSING = '__omr_cached_client_missing__';
    private const VERSION = 'v1';

    public static function get(
        string $namespace,
        string $path,
        array $query = [],
        ?string $tenant = null,
        array $options = []
    ): array {
        $tenant = $tenant ?: OmrConfig::tenantForSharedContent();
        $query = self::query($query, $tenant);
        $cacheKey = self::cacheKey($namespace, $path, $tenant, $query);
        $staleKey = "{$cacheKey}_stale";
        $lockKey = "{$cacheKey}_lock";

        $cached = Cache::get($cacheKey, self::MISSING);

        if ($cached !== self::MISSING) {
            return self::result($cached);
        }

        $stale = Cache::get($staleKey);

        $snapshot = self::readSnapshot($cacheKey);

        if (is_array($snapshot)) {
            Cache::put($cacheKey, $snapshot, $options['success_ttl'] ?? now()->addHours(6));
            Cache::put($staleKey, $snapshot, $options['stale_ttl'] ?? now()->addDays(2));

            return self::result($snapshot);
        }

        if (self::isCoolingDown($tenant)) {
            return self::result($stale);
        }

        try {
            return Cache::lock($lockKey, (int) ($options['lock_seconds'] ?? 20))->block(
                (int) ($options['block_seconds'] ?? 4),
                function () use ($cacheKey, $staleKey, $stale, $namespace, $path, $query, $tenant, $options) {
                    $cachedAgain = Cache::get($cacheKey, self::MISSING);

                    if ($cachedAgain !== self::MISSING) {
                        return self::result($cachedAgain);
                    }

                    if (self::isCoolingDown($tenant)) {
                        return self::result($stale);
                    }

                    $result = self::fetch($namespace, $path, $query, $tenant, $options);

                    if ($result['ok']) {
                        Cache::put($cacheKey, $result, $options['success_ttl'] ?? now()->addHours(6));
                        Cache::put($staleKey, $result, $options['stale_ttl'] ?? now()->addDays(2));
                        self::writeSnapshot($cacheKey, $result);

                        return $result;
                    }

                    Cache::put($cacheKey, $result, self::failureTtl($result['status'] ?? null, $options));

                    return is_array($stale) ? self::result($stale) : $result;
                }
            );
        } catch (\Throwable $e) {
            Log::warning('OMR cached client lock failed', [
                'namespace' => $namespace,
                'path' => $path,
                'tenant' => $tenant,
                'error' => $e->getMessage(),
            ]);

            return self::result($stale);
        }
    }

    public static function isCoolingDown(?string $tenant = null): bool
    {
        foreach (self::cooldownKeys($tenant) as $key) {
            if (Cache::has($key)) {
                return true;
            }
        }

        return false;
    }

    public static function markCoolingDown(?string $tenant = null, int $minutes = 15): void
    {
        foreach (self::cooldownKeys($tenant) as $key) {
            Cache::put($key, true, now()->addMinutes($minutes));
        }
    }

    private static function fetch(string $namespace, string $path, array $query, string $tenant, array $options): array
    {
        $url = OmrConfig::apiUrl($path);

        try {
            $response = Http::withoutVerifying()
                ->connectTimeout(5)
                ->timeout(20)
                ->retry(
                    (int) ($options['retry_count'] ?? OmrConfig::retryCount()),
                    (int) ($options['retry_sleep'] ?? OmrConfig::retrySleep()),
                    throw: false
                )
                ->withHeaders([
                    'Accept' => $options['accept'] ?? 'application/json',
                    'X-Tenant-ID' => $tenant,
                ])
                ->get($url, $query);

            if ($response->status() === 429) {
                self::markCoolingDown($tenant, (int) ($options['cooldown_minutes'] ?? 15));
            } elseif ($response->serverError()) {
                self::markCoolingDown($tenant, 2);
            }

            $json = null;

            try {
                $decoded = $response->json();
                $json = is_array($decoded) ? $decoded : null;
            } catch (\Throwable) {
                $json = null;
            }

            $result = [
                'ok' => $response->successful(),
                'status' => $response->status(),
                'json' => $json,
                'body' => (string) $response->body(),
            ];

            if (! $result['ok']) {
                Log::warning('OMR API request failed', [
                    'namespace' => $namespace,
                    'path' => $path,
                    'tenant' => $tenant,
                    'status' => $response->status(),
                    'body' => mb_substr((string) $response->body(), 0, 500),
                ]);
            }

            return $result;
        } catch (\Throwable $e) {
            self::markCoolingDown($tenant, 2);

            Log::warning('OMR API request exception', [
                'namespace' => $namespace,
                'path' => $path,
                'tenant' => $tenant,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'status' => null,
                'json' => null,
                'body' => '',
            ];
        }
    }

    private static function query(array $query, string $tenant): array
    {
        $query['tenant'] = $query['tenant'] ?? $tenant;

        return collect($query)
            ->reject(fn ($value) => $value === null || $value === '')
            ->sortKeys()
            ->all();
    }

    private static function cacheKey(string $namespace, string $path, string $tenant, array $query): string
    {
        $safeNamespace = preg_replace('/[^a-z0-9_:-]+/i', '_', $namespace);
        $safePath = preg_replace('/[^a-z0-9_:-]+/i', '_', trim($path, '/'));

        return "omr_cached_client_".self::VERSION."_{$safeNamespace}_{$tenant}_{$safePath}_".md5(json_encode($query));
    }

    private static function cooldownKeys(?string $tenant): array
    {
        return collect([
            $tenant,
            OmrConfig::tenantId(),
            OmrConfig::tenantForSharedContent(),
        ])
            ->filter()
            ->unique()
            ->map(fn ($id) => 'omr_rate_limited_'.$id)
            ->values()
            ->all();
    }

    private static function failureTtl(?int $status, array $options)
    {
        if ($status === 429) {
            return $options['rate_limit_ttl'] ?? now()->addMinutes(15);
        }

        if ($status === 404) {
            return $options['not_found_ttl'] ?? now()->addMinutes(5);
        }

        return $options['failure_ttl'] ?? now()->addMinutes(5);
    }

    private static function snapshotPath(string $cacheKey): string
    {
        return storage_path('app/omr-cache/'.sha1($cacheKey).'.json');
    }

    private static function readSnapshot(string $cacheKey): ?array
    {
        $path = self::snapshotPath($cacheKey);

        if (! File::exists($path)) {
            return null;
        }

        try {
            $payload = json_decode((string) File::get($path), true);

            return is_array($payload['result'] ?? null) ? $payload['result'] : null;
        } catch (\Throwable $e) {
            Log::warning('OMR cached client snapshot read failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private static function writeSnapshot(string $cacheKey, array $result): void
    {
        if (empty($result['ok'])) {
            return;
        }

        $path = self::snapshotPath($cacheKey);

        try {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, json_encode([
                'result' => $result,
                'created_at' => now()->toIso8601String(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } catch (\Throwable $e) {
            Log::warning('OMR cached client snapshot write failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private static function result($value): array
    {
        return is_array($value)
            ? array_merge([
                'ok' => false,
                'status' => null,
                'json' => null,
                'body' => '',
            ], $value)
            : [
                'ok' => false,
                'status' => null,
                'json' => null,
                'body' => '',
            ];
    }
}
