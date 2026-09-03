<?php

namespace App\Http\Controllers;

use App\Support\LocaleMapper;
use App\Support\OmrConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class StaticPageController extends Controller
{
    private static function pagesCacheKey(string $tenant, string $locale): string
    {
        return "pages_list_v5_{$tenant}_{$locale}";
    }

    private static function pagesStaleKey(string $tenant, string $locale): string
    {
        return "pages_list_stale_v5_{$tenant}_{$locale}";
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

    private static function isCoolingDown(?string $tenant = null): bool
    {
        foreach (self::cooldownKeys($tenant) as $key) {
            if (Cache::has($key)) {
                return true;
            }
        }

        return false;
    }

    private static function markCoolingDown(?string $tenant = null, int $minutes = 15): void
    {
        foreach (self::cooldownKeys($tenant) as $key) {
            Cache::put($key, true, now()->addMinutes($minutes));
        }
    }

    private static function failureTtlByStatus(?int $status)
    {
        return match ($status) {
            429 => now()->addMinutes(15),
            404 => now()->addSeconds(60),
            default => now()->addMinutes(2),
        };
    }

    private static function normalizePageSlug(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $value = urldecode($value);
        $value = preg_replace('#^https?://[^/]+#i', '', $value) ?: $value;

        $path = parse_url($value, PHP_URL_PATH);

        if (is_string($path) && $path !== '') {
            $value = $path;
        }

        $value = trim($value, "/ \t\n\r\0\x0B");

        if ($value === '') {
            return null;
        }

        $segments = array_values(array_filter(explode('/', $value), fn ($segment) => $segment !== ''));

        if (! empty($segments)) {
            $first = strtolower((string) $segments[0]);

            if (in_array(LocaleMapper::toWeb($first), LocaleMapper::WEB_LOCALES, true)) {
                array_shift($segments);
            }
        }

        if (count($segments) !== 1) {
            return null;
        }

        $slug = strtolower(trim((string) $segments[0]));

        return $slug !== '' ? $slug : null;
    }

    private static function pageSlugs(array $page): array
    {
        $candidates = [];

        foreach (['slug', 'identifier', 'handle', 'path', 'url', 'permalink', 'route'] as $key) {
            if (! empty($page[$key]) && is_scalar($page[$key])) {
                $candidates[] = (string) $page[$key];
            }
        }

        $translations = $page['translations'] ?? [];

        if (is_array($translations)) {
            foreach ($translations as $translation) {
                if (! is_array($translation)) {
                    continue;
                }

                foreach (['slug', 'identifier', 'handle', 'path', 'url', 'permalink', 'route'] as $key) {
                    if (! empty($translation[$key]) && is_scalar($translation[$key])) {
                        $candidates[] = (string) $translation[$key];
                    }
                }
            }
        }

        return collect($candidates)
            ->map(fn ($value) => self::normalizePageSlug($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function renderTemporarilyUnavailable(string $locale)
    {
        return Inertia::render('Errors/NotFound', [
            'status' => 503,
            'locale' => $locale,
            'page' => [
                'title' => '503 — Dienst vorübergehend nicht verfügbar',
                'content' => 'Die Seite wird gerade aktualisiert. Bitte versuchen Sie es in wenigen Minuten erneut.',
            ],
        ])->toResponse(request())->setStatusCode(503);
    }

    public static function getPage(string $locale, string $pageSlug): ?array
    {
        $tenant = OmrConfig::tenantId();
        $mainTenant = OmrConfig::tenantForSharedContent();

        $locale = LocaleMapper::toWeb($locale);
        $pageSlug = self::normalizePageSlug($pageSlug) ?: '';

        if (! $tenant || ! $mainTenant || $pageSlug === '') {
            return null;
        }

        /**
         * Tekil sayfa endpointine gitmiyoruz.
         * Bütün sayfalar cache'li /pages listesinden çözülüyor.
         */
        $pages = self::getPages($locale);

        $page = collect($pages)->first(function ($page) use ($pageSlug) {
            return is_array($page) && in_array($pageSlug, self::pageSlugs($page), true);
        });

        return is_array($page) && ! empty($page) ? $page : null;
    }

    public static function getPages(string $locale): array
    {
        $tenant = OmrConfig::tenantId();
        $mainTenant = OmrConfig::tenantForSharedContent();

        $locale = LocaleMapper::toWeb($locale);

        if (! $tenant || ! $mainTenant) {
            return [];
        }

        $cacheKey = self::pagesCacheKey($mainTenant, $locale);
        $staleKey = self::pagesStaleKey($mainTenant, $locale);
        $lockKey = "lock_{$cacheKey}";
        $missing = '__pages_list_missing__';

        $cached = Cache::get($cacheKey, $missing);

        if ($cached !== $missing) {
            return is_array($cached) ? $cached : [];
        }

        $stale = Cache::get($staleKey);

        if (self::isCoolingDown($mainTenant)) {
            if (is_array($stale) && ! empty($stale)) {
                return $stale;
            }

            return $locale !== 'de' ? self::getPages('de') : [];
        }

        try {
            return Cache::lock($lockKey, 20)->block(4, function () use (
                $cacheKey,
                $staleKey,
                $missing,
                $stale,
                $mainTenant,
                $locale
            ) {
                $cachedAgain = Cache::get($cacheKey, $missing);

                if ($cachedAgain !== $missing) {
                    return is_array($cachedAgain) ? $cachedAgain : [];
                }

                if (self::isCoolingDown($mainTenant)) {
                    return is_array($stale) ? $stale : [];
                }

                $allPages = [];
                $page = 1;
                $maxPages = 5;
                $failedStatus = null;

                while ($page <= $maxPages) {
                    $res = Http::withoutVerifying()
                        ->connectTimeout(5)
                        ->timeout(20)
                        ->withHeaders([
                            'X-Tenant-ID' => $mainTenant,
                            'Accept' => 'application/json',
                        ])
                        ->get(OmrConfig::apiUrl('pages'), [
                            'tenant' => $mainTenant,
                            'locale' => LocaleMapper::toApi($locale),
                            'page' => $page,
                            'per_page' => 100,
                        ]);

                    if (! $res->successful()) {
                        $failedStatus = $res->status();

                        if ($failedStatus === 429) {
                            self::markCoolingDown($mainTenant, 15);
                        }

                        Log::warning('Pages list API failed', [
                            'tenant' => $mainTenant,
                            'status' => $failedStatus,
                            'body' => mb_substr($res->body(), 0, 300),
                            'locale' => $locale,
                            'page' => $page,
                        ]);

                        break;
                    }

                    $json = $res->json() ?? [];
                    $items = $json['data'] ?? [];

                    if (! is_array($items) || empty($items)) {
                        break;
                    }

                    $allPages = array_merge($allPages, $items);

                    $lastPage = (int) (
                        data_get($json, 'pagination.last_page') ??
                        data_get($json, 'meta.last_page') ??
                        0
                    );

                    if ($lastPage > 0 && $page >= $lastPage) {
                        break;
                    }

                    if (count($items) < 100) {
                        break;
                    }

                    $page++;
                }

                if ($failedStatus) {
                    if ($locale !== 'de') {
                        $fallbackPages = self::getPages('de');

                        if (! empty($fallbackPages)) {
                            Cache::put($cacheKey, $fallbackPages, now()->addDays(7));
                            Cache::put($staleKey, $fallbackPages, now()->addDays(14));

                            return $fallbackPages;
                        }
                    }

                    Cache::put(
                        $cacheKey,
                        is_array($stale) ? $stale : [],
                        self::failureTtlByStatus($failedStatus)
                    );

                    return is_array($stale) ? $stale : [];
                }

                if (empty($allPages) && $locale !== 'de') {
                    $fallbackPages = self::getPages('de');

                    if (! empty($fallbackPages)) {
                        Cache::put($cacheKey, $fallbackPages, now()->addDays(7));
                        Cache::put($staleKey, $fallbackPages, now()->addDays(14));

                        return $fallbackPages;
                    }
                }

                Cache::put(
                    $cacheKey,
                    $allPages,
                    ! empty($allPages) ? now()->addDays(7) : now()->addMinutes(2)
                );

                if (! empty($allPages)) {
                    Cache::put($staleKey, $allPages, now()->addDays(14));
                }

                return $allPages;
            });
        } catch (\Throwable $e) {
            Cache::put(
                $cacheKey,
                is_array($stale) ? $stale : [],
                now()->addMinutes(2)
            );

            Log::warning('getPages exception', [
                'error' => $e->getMessage(),
                'locale' => $locale,
            ]);

            return is_array($stale) ? $stale : [];
        }
    }

    public static function hasFreshPages(string $locale): bool
    {
        $tenant = OmrConfig::tenantForSharedContent();

        return $tenant !== ''
            && Cache::has(self::pagesCacheKey($tenant, LocaleMapper::toWeb($locale)));
    }

    /**
     * Read the existing page snapshot without ever contacting the OMR API.
     * Sitemap generation uses this method so crawlers cannot trigger a slow
     * upstream request when the sitemap endpoint is opened.
     */
    public static function getCachedPages(string $locale = 'de'): array
    {
        $tenant = OmrConfig::tenantForSharedContent();

        if ($tenant === '') {
            return [];
        }

        $locale = LocaleMapper::toWeb($locale);

        foreach ([
            self::pagesCacheKey($tenant, $locale),
            self::pagesStaleKey($tenant, $locale),
        ] as $key) {
            $pages = Cache::get($key);

            if (is_array($pages) && $pages !== []) {
                return $pages;
            }
        }

        return [];
    }

    public static function warmPages(string $locale = 'de', bool $allLocales = false, bool $force = false): array
    {
        $tenant = OmrConfig::tenantForSharedContent();
        $locale = LocaleMapper::toWeb($locale);

        if ($tenant === '') {
            return ['ok' => false, 'count' => 0, 'locale' => $locale, 'locales' => []];
        }

        if ($force) {
            Cache::forget(self::pagesCacheKey($tenant, $locale));
        }

        $pages = self::getPages($locale);
        $locales = $allLocales ? LocaleMapper::WEB_LOCALES : [$locale];

        if (! empty($pages)) {
            foreach ($locales as $targetLocale) {
                Cache::forever(self::pagesCacheKey($tenant, $targetLocale), $pages);
                Cache::forever(self::pagesStaleKey($tenant, $targetLocale), $pages);
            }
        }

        return [
            'ok' => ! empty($pages),
            'count' => count($pages),
            'locale' => $locale,
            'locales' => $locales,
        ];
    }

    public function index($locale)
    {
        $locale = LocaleMapper::toWeb($locale);
        $mainTenant = OmrConfig::tenantForSharedContent();

        $pages = self::getPages($locale);

        return Inertia::render('Pages/Index', [
            'pages' => $pages,
            'locale' => $locale,
            'omrCoolingDown' => $mainTenant ? self::isCoolingDown($mainTenant) : false,
        ]);
    }

    public function show($locale, $pageSlug)
    {
        $tenant = OmrConfig::tenantId();
        $mainTenant = OmrConfig::tenantForSharedContent();

        $locale = LocaleMapper::toWeb($locale);
        $pageSlug = strtolower(trim((string) $pageSlug));

        if (! $tenant || ! $mainTenant) {
            abort(500, 'TENANT_ID missing');
        }

        $errorPages = [
            '404' => 404,
            '500' => 500,
            'seite-nichtgefunden-404' => 404,
            'not-found-404' => 404,
            'keine-antwort-500' => 500,
            'not-responding-500' => 500,
        ];

        $isErrorSlug = array_key_exists($pageSlug, $errorPages);
        $status = $errorPages[$pageSlug] ?? 404;

        $page = self::getPage($locale, $pageSlug);

        if (! $page && self::isCoolingDown($mainTenant)) {
            return $this->renderTemporarilyUnavailable($locale);
        }

        if (! $page && $isErrorSlug) {
            return Inertia::render('Errors/NotFound', [
                'status' => $status,
                'locale' => $locale,
                'page' => [
                    'title' => ($status === 500 ? '500 — Serverfehler' : '404 — Seite nicht gefunden'),
                    'content' => ($status === 500
                        ? 'Ein Serverfehler ist aufgetreten — bitte versuchen Sie es erneut.'
                        : 'Die Seite wurde nicht gefunden oder existiert nicht mehr.'),
                ],
            ])->toResponse(request())->setStatusCode($status);
        }

        if ($isErrorSlug) {
            return Inertia::render('Errors/NotFound', [
                'status' => $status,
                'locale' => $locale,
                'page' => $page,
            ])->toResponse(request())->setStatusCode($status);
        }

        if (! $page) {
            abort(404);
        }

        return Inertia::render('Pages/Show', [
            'page' => $page,
            'locale' => $locale,
        ]);
    }
}
