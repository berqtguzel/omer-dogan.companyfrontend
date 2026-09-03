<?php

namespace App\Support\Omr;

use App\Support\LocaleMapper;
use App\Support\OmrConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class OmrCatalog
{
    private const VERSION = 'v16';

    private const MISSING = '__omr_catalog_missing__';

    /**
     * Tenant service catalog.
     *
     * Örnekler:
     * OmrCatalog::services($tenant, 'de')
     * OmrCatalog::services($tenant, 'de', ['category_slug' => 'gebaudereinigung'])
     * OmrCatalog::services($tenant, 'de', ['parent_id' => 'null'])
     */
    public static function services(?string $tenant = null, ?string $locale = null, array $query = []): array
    {
        $tenant = trim((string) ($tenant ?: OmrConfig::tenantForSharedContent()));
        $locale = strtolower((string) ($locale ?: OmrConfig::defaultLocale()));

        if ($tenant === '') {
            return [];
        }

        [$query, $fetchOptions, $cacheKey, $staleKey, $memoryKey] = self::cacheContext($tenant, $locale, $query);

        static $memory = [];

        if (array_key_exists($memoryKey, $memory)) {
            $items = is_array($memory[$memoryKey]) ? $memory[$memoryKey] : [];

            if (! empty($items)) {
                return $items;
            }

            return $memory[$memoryKey] = self::defaultLocaleServices($tenant, $locale, $query);
        }

        $cached = Cache::get($cacheKey, self::MISSING);

        if ($cached !== self::MISSING) {
            $memory[$memoryKey] = is_array($cached) ? $cached : [];

            if (empty($memory[$memoryKey])) {
                $memory[$memoryKey] = self::defaultLocaleServices($tenant, $locale, $query);
            }

            return $memory[$memoryKey];
        }

        $stale = Cache::get($staleKey);

        if (is_array($stale)) {
            $memory[$memoryKey] = is_array($stale) ? $stale : [];

            if (empty($memory[$memoryKey])) {
                $memory[$memoryKey] = self::defaultLocaleServices($tenant, $locale, $query);
            }

            return $memory[$memoryKey];
        }

        $snapshot = self::readSnapshot($cacheKey);

        if (is_array($snapshot)) {
            Cache::put($cacheKey, $snapshot, ! empty($snapshot) ? now()->addDays(7) : now()->addMinutes(10));

            if (! empty($snapshot)) {
                Cache::put($staleKey, $snapshot, now()->addDays(30));
            }

            $memory[$memoryKey] = $snapshot;

            if (empty($memory[$memoryKey])) {
                $memory[$memoryKey] = self::defaultLocaleServices($tenant, $locale, $query);
            }

            return $memory[$memoryKey];
        }

        self::markWarmNeeded($tenant, $locale, $query, $fetchOptions);

        $memory[$memoryKey] = self::defaultLocaleServices($tenant, $locale, $query);

        return $memory[$memoryKey];
    }

    /**
     * The scheduler warms the default catalog, whose records already contain all
     * translations. Reuse it whenever a locale-specific catalog is absent or empty.
     */
    private static function defaultLocaleServices(string $tenant, string $locale, array $query): array
    {
        $defaultLocale = strtolower((string) (OmrConfig::defaultLocale() ?: 'de'));

        if ($locale === $defaultLocale) {
            return [];
        }

        return self::services($tenant, $defaultLocale, $query);
    }

    public static function warmServices(?string $tenant = null, ?string $locale = null, array $query = []): array
    {
        $tenant = trim((string) ($tenant ?: OmrConfig::tenantForSharedContent()));
        $locale = strtolower((string) ($locale ?: OmrConfig::defaultLocale()));

        if ($tenant === '') {
            return ['ok' => false, 'count' => 0, 'status' => null];
        }

        [$query, $fetchOptions, $cacheKey, $staleKey] = self::cacheContext($tenant, $locale, $query);
        $lockKey = "lock_{$cacheKey}";

        try {
            return Cache::lock($lockKey, 300)->block(1, function () use ($tenant, $locale, $query, $fetchOptions, $cacheKey, $staleKey) {
                if (self::isCoolingDown($tenant)) {
                    $stale = Cache::get($staleKey);

                    return [
                        'ok' => is_array($stale),
                        'count' => is_array($stale) ? count($stale) : 0,
                        'status' => 429,
                        'stale' => is_array($stale),
                    ];
                }

                $result = self::fetchServicesFromApi($tenant, $locale, $query, $fetchOptions);

                if (! ($result['ok'] ?? false)) {
                    $status = $result['status'] ?? null;

                    if ((int) $status === 429) {
                        self::markCoolingDown($tenant, 15);
                    }

                    Log::warning('OMR catalog warm failed', [
                        'tenant' => $tenant,
                        'locale' => $locale,
                        'query' => $query,
                        'status' => $status,
                        'page' => $result['page'] ?? null,
                        'body' => mb_substr((string) ($result['body'] ?? ''), 0, 300),
                    ]);

                    return [
                        'ok' => false,
                        'count' => 0,
                        'status' => $status,
                    ];
                }

                $items = $result['items'] ?? [];

                Cache::put($cacheKey, $items, ! empty($items) ? now()->addDays(7) : now()->addMinutes(10));

                if (! empty($items)) {
                    Cache::put($staleKey, $items, now()->addDays(30));
                    self::writeSnapshot($cacheKey, $items);

                    if (($query['parent_id'] ?? null) === 'null') {
                        Cache::forever(
                            self::rootCacheKey($tenant, $locale),
                            self::buildRootCategories($items),
                        );
                    }
                }

                return [
                    'ok' => true,
                    'count' => count($items),
                    'status' => 200,
                    'pages' => $result['pages'] ?? null,
                ];
            });
        } catch (\Throwable $e) {
            Log::warning('OMR catalog warm lock/exception', [
                'tenant' => $tenant,
                'locale' => $locale,
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            $stale = Cache::get($staleKey);

            return [
                'ok' => is_array($stale),
                'count' => is_array($stale) ? count($stale) : 0,
                'status' => null,
                'stale' => is_array($stale),
            ];
        }
    }

    public static function hasFreshServices(?string $tenant = null, ?string $locale = null, array $query = []): bool
    {
        $tenant = trim((string) ($tenant ?: OmrConfig::tenantForSharedContent()));
        $locale = strtolower((string) ($locale ?: OmrConfig::defaultLocale()));

        if ($tenant === '') {
            return false;
        }

        [, , $cacheKey] = self::cacheContext($tenant, $locale, $query);

        return Cache::has($cacheKey) || self::snapshotExists($cacheKey);
    }

    /**
     * Root categories are resolved from the API's own parent_id=null dataset.
     * Main service pages must use this list so city/location records never become
     * the main service content.
     */
    public static function rootCategories(?string $tenant = null, ?string $locale = null): array
    {
        $tenant = trim((string) ($tenant ?: OmrConfig::tenantForSharedContent()));
        $locale = strtolower((string) ($locale ?: OmrConfig::defaultLocale()));

        if ($tenant === '') {
            return [];
        }

        $cacheKey = self::rootCacheKey($tenant, $locale);
        $cached = Cache::get($cacheKey, self::MISSING);

        if ($cached !== self::MISSING) {
            return is_array($cached) ? $cached : [];
        }

        $categories = self::services($tenant, $locale, [
            'parent_id' => 'null',
            '_max_pages' => 1,
        ]);

        if (empty($categories)) {
            self::markWarmNeeded($tenant, $locale, ['parent_id' => 'null'], self::fetchOptions(['_max_pages' => 1]));

            return [];
        }

        $categories = collect($categories)
            ->filter(fn ($item) => is_array($item))
            ->filter(fn ($item) => self::isRootParent($item))
            ->filter(fn ($item) => ! self::hasCity($item))
            ->unique(fn ($item) => self::itemIdentity($item))
            ->sortBy(fn ($item) => $item['order'] ?? $item['sort_order'] ?? $item['name'] ?? $item['title'] ?? 999999)
            ->values()
            ->toArray();

        Cache::put(
            $cacheKey,
            $categories,
            ! empty($categories) ? now()->addDays(7) : now()->addMinutes(5)
        );

        return $categories;
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

    private static function fetchServicesFromApi(string $tenant, string $locale, array $query = [], array $fetchOptions = []): array
    {
        $allServices = [];
        $page = 1;
        $lastPage = 1;
        $maxPages = max(1, min(500, (int) ($fetchOptions['max_pages'] ?? 1)));
        $perPage = max(1, min(500, (int) ($fetchOptions['per_page'] ?? 500)));
        $sleepMs = max(0, min(5000, (int) ($fetchOptions['sleep_ms'] ?? 0)));

        do {
            $requestQuery = array_merge($query, [
                'tenant' => $tenant,
                'locale' => LocaleMapper::toApi($locale),
                'page' => $page,
                'per_page' => $perPage,
            ]);

            $response = Http::withOptions(['verify' => false])
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(20)
                ->retry(OmrConfig::retryCount(), OmrConfig::retrySleep(), throw: false)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-Tenant-ID' => $tenant,
                ])
                ->get(OmrConfig::apiUrl('services'), $requestQuery);

            if (! $response->ok()) {
                return [
                    'ok' => false,
                    'status' => $response->status(),
                    'page' => $page,
                    'body' => $response->body(),
                    'items' => [],
                ];
            }

            $json = $response->json() ?? [];
            $items = $json['data'] ?? [];

            if (! is_array($items) || empty($items)) {
                break;
            }

            $allServices = array_merge($allServices, $items);

            $apiLastPage = data_get($json, 'pagination.last_page')
                ?? data_get($json, 'meta.last_page');

            if ($apiLastPage) {
                $lastPage = max(1, (int) $apiLastPage);
            } else {
                $lastPage = count($items) >= $perPage ? $page + 1 : $page;
            }

            $page++;

            if ($sleepMs > 0 && $page <= $lastPage && $page <= $maxPages) {
                usleep($sleepMs * 1000);
            }
        } while ($page <= $lastPage && $page <= $maxPages);

        return [
            'ok' => true,
            'status' => 200,
            'items' => $allServices,
            'pages' => $page - 1,
        ];
    }

    private static function buildRootCategories(array $services): array
    {
        $items = collect($services)
            ->filter(fn ($item) => is_array($item));

        $rootItems = $items
            ->filter(fn ($item) => self::isRootParent($item) && ! self::hasCity($item))
            ->unique(fn ($item) => self::itemIdentity($item))
            ->sortBy(fn ($item) => $item['order'] ?? $item['sort_order'] ?? $item['name'] ?? $item['title'] ?? 999999)
            ->values()
            ->toArray();

        if (! empty($rootItems)) {
            return $rootItems;
        }

        $fromCategoryObject = $items
            ->map(fn ($item) => $item['category'] ?? null)
            ->filter(fn ($category) => is_array($category) && ! empty($category))
            ->map(fn ($category) => array_filter([
                'id' => $category['id'] ?? $category['slug'] ?? null,
                'slug' => $category['slug'] ?? null,
                'category_slug' => $category['slug'] ?? null,
                'name' => $category['name'] ?? $category['title'] ?? null,
                'title' => $category['title'] ?? $category['name'] ?? null,
                'image' => $category['image'] ?? null,
                'description' => $category['description'] ?? null,
                'short_description' => $category['short_description'] ?? null,
                'translations' => $category['translations'] ?? [],
            ], fn ($value) => $value !== null && $value !== ''))
            ->filter(fn ($category) => ! empty($category['slug']) || ! empty($category['name']) || ! empty($category['title']))
            ->unique(fn ($category) => $category['id'] ?? $category['slug'] ?? $category['name'] ?? $category['title'])
            ->values()
            ->toArray();

        if (! empty($fromCategoryObject)) {
            return $fromCategoryObject;
        }

        return $items
            ->map(function ($item) {
                $slug = self::firstScalar([
                    $item['category_slug'] ?? null,
                    $item['service_slug'] ?? null,
                    data_get($item, 'parent.slug'),
                    data_get($item, 'service.slug'),
                ]);

                if (! $slug) {
                    return null;
                }

                $name = self::firstScalar([
                    data_get($item, 'category.name'),
                    data_get($item, 'category.title'),
                    data_get($item, 'service.name'),
                    data_get($item, 'service.title'),
                    data_get($item, 'parent.name'),
                    data_get($item, 'parent.title'),
                ]);

                return array_filter([
                    'id' => 'category-'.self::normalizeSlug($slug),
                    'slug' => self::normalizeSlug($slug),
                    'category_slug' => self::normalizeSlug($slug),
                    'name' => $name,
                    'title' => $name,
                    'image' => $item['image'] ?? null,
                    'description' => data_get($item, 'category.description') ?? data_get($item, 'service.description') ?? null,
                    'short_description' => data_get($item, 'category.short_description') ?? data_get($item, 'service.short_description') ?? null,
                    'translations' => data_get($item, 'category.translations') ?? data_get($item, 'service.translations') ?? [],
                ], fn ($value) => $value !== null && $value !== '');
            })
            ->filter()
            ->unique(fn ($category) => $category['slug'])
            ->values()
            ->toArray();
    }

    private static function fetchOptions(array $query): array
    {
        return [
            'max_pages' => max(1, min(500, (int) ($query['_max_pages'] ?? 1))),
            'per_page' => max(1, min(500, (int) ($query['_per_page'] ?? 500))),
            'sleep_ms' => max(0, min(5000, (int) ($query['_sleep_ms'] ?? 0))),
        ];
    }

    private static function rootCacheKey(string $tenant, string $locale): string
    {
        return 'omr_catalog_'.self::VERSION."_root_categories_{$tenant}_{$locale}";
    }

    private static function cacheContext(string $tenant, string $locale, array $query): array
    {
        $fetchOptions = self::fetchOptions($query);
        $query = self::normalizeQuery($query);
        $queryHash = md5(json_encode($query, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $cacheKey = 'omr_catalog_'.self::VERSION."_services_{$tenant}_{$locale}_{$queryHash}";
        $staleKey = 'omr_catalog_'.self::VERSION."_services_stale_{$tenant}_{$locale}_{$queryHash}";
        $memoryKey = "{$tenant}|{$locale}|{$queryHash}";

        return [$query, $fetchOptions, $cacheKey, $staleKey, $memoryKey];
    }

    private static function markWarmNeeded(string $tenant, string $locale, array $query, array $fetchOptions): void
    {
        Cache::put('omr_catalog_warm_needed_'.md5(json_encode([
            'tenant' => $tenant,
            'locale' => $locale,
            'query' => $query,
            'fetch' => $fetchOptions,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), [
            'tenant' => $tenant,
            'locale' => $locale,
            'query' => $query,
            'fetch' => $fetchOptions,
            'created_at' => now()->toIso8601String(),
        ], now()->addHours(6));
    }

    private static function snapshotPath(string $cacheKey): string
    {
        return storage_path('app/omr-catalog/'.sha1($cacheKey).'.json');
    }

    private static function snapshotExists(string $cacheKey): bool
    {
        return File::exists(self::snapshotPath($cacheKey));
    }

    private static function readSnapshot(string $cacheKey): ?array
    {
        $path = self::snapshotPath($cacheKey);

        if (! File::exists($path)) {
            return null;
        }

        try {
            $payload = json_decode((string) File::get($path), true);

            return is_array($payload['items'] ?? null) ? $payload['items'] : null;
        } catch (\Throwable $e) {
            Log::warning('OMR catalog snapshot read failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private static function writeSnapshot(string $cacheKey, array $items): void
    {
        if (empty($items)) {
            return;
        }

        $path = self::snapshotPath($cacheKey);

        try {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, json_encode([
                'items' => $items,
                'created_at' => now()->toIso8601String(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } catch (\Throwable $e) {
            Log::warning('OMR catalog snapshot write failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private static function normalizeQuery(array $query): array
    {
        foreach (array_keys($query) as $key) {
            if (is_string($key) && str_starts_with($key, '_')) {
                unset($query[$key]);
            }
        }

        unset($query['tenant'], $query['locale'], $query['page'], $query['per_page']);

        if (array_key_exists('parent_id', $query)) {
            $parentId = $query['parent_id'];

            if ($parentId === null || $parentId === '' || strtolower((string) $parentId) === 'null') {
                $query['parent_id'] = 'null';
            }
        }

        $query = array_filter($query, function ($value, $key) {
            if ($key === 'parent_id') {
                return true;
            }

            return $value !== null && $value !== '';
        }, ARRAY_FILTER_USE_BOTH);

        ksort($query);

        return $query;
    }

    private static function cooldownKeys(?string $tenant = null): array
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

    private static function failureTtlByStatus(?int $status)
    {
        return match ((int) $status) {
            429 => now()->addMinutes(15),
            404 => now()->addSeconds(60),
            default => now()->addMinutes(2),
        };
    }

    private static function isRootParent(array $item): bool
    {
        if (! array_key_exists('parent_id', $item)) {
            return true;
        }

        $parentId = $item['parent_id'];

        return $parentId === null
            || $parentId === ''
            || (string) $parentId === '0'
            || strtolower((string) $parentId) === 'null';
    }

    private static function hasCity(array $item): bool
    {
        $cityCandidates = [
            $item['city_slug'] ?? null,
            $item['city'] ?? null,
            $item['district'] ?? null,
            $item['tenant_city'] ?? null,
            $item['tenant_district'] ?? null,
            data_get($item, 'location.slug'),
            data_get($item, 'location.city_slug'),
            data_get($item, 'location.city'),
            data_get($item, 'location.district'),
            data_get($item, 'omr.tenant_city'),
            data_get($item, 'omr.tenant_district'),
        ];

        foreach ($cityCandidates as $candidate) {
            if (is_scalar($candidate) && trim((string) $candidate) !== '') {
                return true;
            }
        }

        return false;
    }

    private static function itemIdentity(array $item): string
    {
        return (string) (
            $item['id']
            ?? $item['slug']
            ?? $item['category_slug']
            ?? $item['name']
            ?? md5(json_encode($item))
        );
    }

    private static function firstScalar(array $values): ?string
    {
        foreach ($values as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    private static function normalizeSlug(?string $text): string
    {
        $text = str_replace(['/', '\\'], '-', (string) $text);
        $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text) ?: $text;
        $text = preg_replace('/[^A-Za-z0-9]+/', '-', $text);

        return trim(strtolower($text), '-');
    }
}
