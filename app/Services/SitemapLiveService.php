<?php

namespace App\Services;

use App\Exceptions\EmptySitemapException;
use App\Support\LocaleMapper;
use App\Support\LegacyUrlNormalizer;
use App\Support\OmrConfig;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SitemapLiveService
{
    public function __construct(
        private readonly SitemapTranslationAuditService $translations,
        private readonly TenantCanonicalUrlResolver $canonicalUrls
    ) {}

    public function index(?string $onlyLocale = null): array
    {
        $tenant = OmrConfig::tenantId();
        $cacheKey = "sitemap:live:{$tenant}:{$this->canonicalUrls->cacheScope()}:v3:index";
        $entries = Cache::get($cacheKey);

        if (! is_array($entries)) {
            try {
                $entries = $this->discoverIndexEntries();
                Cache::put($cacheKey, $entries, $this->cacheTtl());
                Cache::put($cacheKey.':stale', $entries, now()->addDays(30));
            } catch (\Throwable $e) {
                $stale = Cache::get($cacheKey.':stale');

                if (! is_array($stale)) {
                    throw $e;
                }

                $entries = $stale;
            }
        }

        $entries = is_array($entries) ? $entries : [];
        $this->rememberUnknownForeignEntries($entries);
        $this->refreshDueUnavailableEntries($entries);

        $entries = collect(is_array($entries) ? $entries : [])
            ->filter(function ($entry) use ($onlyLocale) {
                if (! is_array($entry) || empty($entry['path'])) {
                    return false;
                }

                if ($onlyLocale && ! str_starts_with($entry['path'], "/{$onlyLocale}/")) {
                    return false;
                }

                if (Cache::has($this->emptyKey($entry['path']))) {
                    return false;
                }

                if (Cache::has($this->failureKey($entry['path']))) {
                    return false;
                }

                if (Cache::has($this->unavailableStateKey($entry['path']))) {
                    return false;
                }

                if (! (bool) config('sitemap.require_raw_translations', true)) {
                    return true;
                }

                $locale = $this->localeFromSitemapPath((string) $entry['path']);

                if ($locale === 'de') {
                    return true;
                }

                $module = $this->moduleFromSitemapPath((string) $entry['path']);

                if ($module === 'serviceLocation') {
                    return false;
                }

                // Never advertise an unchecked foreign child sitemap. Unknown
                // and previously empty modules are probed before publication.
                return Cache::has($this->validatedKey((string) $entry['path']));
            })
            ->values()
            ->all();

        if ($entries === []) {
            throw new RuntimeException('Sitemap API returned no non-empty sitemap modules.');
        }

        return $this->indexDocument($onlyLocale ? "/{$onlyLocale}/sitemap.xml" : '/sitemap.xml', $entries);
    }

    public function module(string $locale, string $module, ?int $page = null): array
    {
        $suffix = $page === null ? '' : '-'.max(1, $page);
        $path = "/{$locale}/sitemap-{$module}{$suffix}.xml";
        $tenant = OmrConfig::tenantId();
        $documentVersion = $module === 'serviceLocation' ? 'v4' : 'v3';
        $cacheKey = 'sitemap:live:'.$tenant.':'.$this->canonicalUrls->cacheScope().":{$documentVersion}:document:".sha1($path);

        try {
            if ((bool) config('sitemap.require_raw_translations', true)
                && $locale !== 'de'
                && $module === 'serviceLocation') {
                throw new EmptySitemapException("Foreign service-location sitemap is withheld until upstream exposes raw translation evidence [{$path}].");
            }

            $document = Cache::get($cacheKey);

            if (! is_array($document) && $locale === 'de' && $module !== 'serviceLocation') {
                $document = Cache::get('sitemap:live:'.$tenant.':'.$this->canonicalUrls->cacheScope().':document:'.sha1($path));
            }

            if (! is_array($document)) {
                try {
                    $document = $this->buildModule($locale, $module, $page);
                    Cache::put($cacheKey, $document, $this->cacheTtl());
                    Cache::put($cacheKey.':stale', $document, now()->addDays(30));
                } catch (\Throwable $e) {
                    if ($e instanceof EmptySitemapException) {
                        throw $e;
                    }

                    $stale = Cache::get($cacheKey.':stale');

                    if (! is_array($stale)) {
                        throw $e;
                    }

                    $document = $stale;
                }
            }
        } catch (\Throwable $e) {
            if ($e instanceof EmptySitemapException) {
                Cache::put($this->emptyKey($path), true, $this->emptyTtl());
                $this->rememberUnavailable($path, 'empty', $this->emptyTtl());
                Cache::forget($this->validatedKey($path));
            } else {
                Cache::put($this->failureKey($path), true, now()->addMinutes(30));
                $this->rememberUnavailable($path, 'failure', 1800);
            }

            throw $e;
        }

        Cache::forget($this->emptyKey($path));
        Cache::forget($this->failureKey($path));
        Cache::forget($this->unavailableStateKey($path));
        Cache::put($this->validatedKey($path), true, now()->addDays(30));

        return $document;
    }

    private function buildModule(string $locale, string $module, ?int $page = null): array
    {
        $requestedPage = max(1, $page ?? 1);
        $query = ($page !== null || $module === 'serviceLocation') ? ['page' => $requestedPage] : [];
        $payload = $this->fetch(
            'sitemap/'.$this->sitemapApiLocale($locale)."/{$module}",
            $query
        );
        $entries = $this->rawEntries($payload);

        if ($entries === []) {
            throw new EmptySitemapException("Sitemap API returned no URLs for [{$locale}:{$module}:{$requestedPage}].");
        }

        $pagination = $this->pagination($payload, $requestedPage);

        if ($page === null && $pagination['last_page'] > 1) {
            $paths = [];

            for ($number = 1; $number <= $pagination['last_page']; $number++) {
                $paths[] = "/{$locale}/sitemap-{$module}-{$number}.xml";
            }

            return $this->indexDocument("/{$locale}/sitemap-{$module}.xml", $paths);
        }

        $urls = $this->normalizeEntries($entries, $locale, $module);

        if ($urls === []) {
            throw new EmptySitemapException("Sitemap API returned no indexable URLs for [{$locale}:{$module}:{$requestedPage}].");
        }

        $suffix = $page === null ? '' : '-'.$requestedPage;

        return $this->urlsetDocument(
            "/{$locale}/sitemap-{$module}{$suffix}.xml",
            $urls
        );
    }

    private function discoverIndexEntries(): array
    {
        $payload = $this->fetch('sitemap', []);

        if (is_string($payload['_xml'] ?? null)) {
            return $this->xmlIndexEntries($payload['_xml']);
        }

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        $entries = [];

        foreach ($data['locales_detail'] ?? [] as $detail) {
            if (! is_array($detail)) {
                continue;
            }

            $locale = LocaleMapper::toWeb(is_scalar($detail['locale'] ?? null) ? (string) $detail['locale'] : '');
            $entries = array_merge($entries, $this->moduleIndexEntries($detail['modules'] ?? [], $locale));
        }

        foreach (['sitemaps', 'modules'] as $key) {
            if (is_array($data[$key] ?? null)) {
                $entries = array_merge($entries, $this->moduleIndexEntries($data[$key], ''));
            }
        }

        if ($entries === []) {
            foreach ($data['locales'] ?? [] as $rawLocale) {
                $locale = LocaleMapper::toWeb(is_scalar($rawLocale) ? (string) $rawLocale : '');

                if (! LocaleMapper::isSupportedWeb($locale)) {
                    continue;
                }

                $localePayload = $this->fetch('sitemap/'.$this->sitemapApiLocale($locale), []);
                $localeData = is_array($localePayload['data'] ?? null) ? $localePayload['data'] : $localePayload;
                $entries = array_merge($entries, $this->moduleIndexEntries($localeData['modules'] ?? [], $locale));
            }
        }

        return collect($entries)->unique('path')->sortBy('path')->values()->all();
    }

    private function moduleIndexEntries(mixed $modules, string $fallbackLocale): array
    {
        if (! is_array($modules)) {
            return [];
        }

        return collect($modules)->map(function ($module) use ($fallbackLocale) {
            $item = is_array($module) ? $module : ['url' => $module];
            $rawUrl = $item['url'] ?? $item['loc'] ?? null;

            if (! is_scalar($rawUrl)) {
                return null;
            }

            $path = LocaleMapper::normalizeWebUrl('/'.ltrim((string) parse_url((string) $rawUrl, PHP_URL_PATH), '/'));

            if (! preg_match('#^/([a-z]{2})/sitemap-([A-Za-z]+)(?:-(\d+))?\.xml$#', $path, $matches)) {
                return null;
            }

            $locale = LocaleMapper::toWeb($matches[1] ?: $fallbackLocale);
            $normalizedModule = strtolower($matches[2]);
            $allowed = collect(config('sitemap.modules', []))->contains(
                fn ($value) => strtolower((string) $value) === $normalizedModule
            );

            if (! LocaleMapper::isSupportedWeb($locale) || ! $allowed) {
                return null;
            }

            return [
                'path' => $path,
                'lastmod' => $this->normalizeLastmod($item['lastmod'] ?? $item['updated_at'] ?? null),
            ];
        })->filter()->values()->all();
    }

    private function fetch(string $path, array $query): array
    {
        $tenant = OmrConfig::tenantId();

        if ($tenant === '') {
            throw new RuntimeException('OMR sitemap tenant is missing.');
        }

        $response = Http::withoutVerifying()
            ->connectTimeout(5)
            ->timeout(20)
            ->withHeaders([
                'Accept' => 'application/json, application/xml;q=0.9, text/xml;q=0.8',
                'X-Tenant-ID' => $tenant,
            ])
            ->get(OmrConfig::apiUrl($path), array_merge($query, ['tenant' => $tenant]));

        if (! $response->successful()) {
            throw new RuntimeException("Sitemap API returned HTTP {$response->status()} for [{$path}].");
        }

        $payload = $this->responsePayload($response);

        if ($payload === []) {
            throw new RuntimeException("Sitemap API returned an empty response for [{$path}].");
        }

        return $payload;
    }

    private function responsePayload(Response $response): array
    {
        $body = trim($response->body());
        $contentType = strtolower($response->header('Content-Type', ''));

        if (str_contains($contentType, 'xml') || str_starts_with($body, '<?xml') || str_starts_with($body, '<urlset')) {
            return $body === '' ? [] : ['_xml' => $body];
        }

        try {
            $json = $response->json();

            return is_array($json) ? $json : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function rawEntries(array $payload): array
    {
        if (is_string($payload['_xml'] ?? null)) {
            return $this->xmlEntries($payload['_xml']);
        }

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

        if (array_is_list($data)) {
            return $data;
        }

        foreach (['urls', 'items', 'entries', 'data'] as $key) {
            if (is_array($data[$key] ?? null) && array_is_list($data[$key])) {
                return $data[$key];
            }
        }

        return [];
    }

    private function normalizeEntries(array $entries, string $locale, string $module): array
    {
        $seen = [];
        $normalized = [];
        $emptyTranslations = 0;
        $germanFallbacks = 0;
        $duplicates = 0;

        foreach ($entries as $entry) {
            $item = is_array($entry) ? $entry : ['loc' => $entry];

            if (! $this->indexable($item)) {
                continue;
            }

            $rawUrl = $item['loc'] ?? $item['url'] ?? $item['location'] ?? $item['permalink'] ?? null;
            $loc = $this->productionUrl($rawUrl, $locale);

            if (! $loc || isset($seen[$loc])) {
                if ($loc && isset($seen[$loc])) {
                    $duplicates++;
                }

                continue;
            }

            if (! $this->validModulePath($loc, $locale, $module)) {
                continue;
            }

            // German is the source language. The upstream German
            // service-location sitemap is already built from real service
            // records; reloading the entire 8k+ service catalog here takes
            // hundreds of paginated API calls and makes the XML time out.
            $requiresRawInspection = (bool) config('sitemap.require_raw_translations', true)
                && ! ($locale === 'de' && $module === 'serviceLocation');

            if ($requiresRawInspection) {
                $translation = $this->translations->inspect($loc, $locale, $module);

                if (! ($translation['allowed'] ?? false)) {
                    if (($translation['reason'] ?? null) === 'german_fallback') {
                        $germanFallbacks++;
                    } else {
                        $emptyTranslations++;
                    }

                    continue;
                }
            }

            $seen[$loc] = true;
            $normalized[] = [
                'loc' => $loc,
                'lastmod' => $this->normalizeLastmod($item['lastmod'] ?? $item['updated_at'] ?? null),
                'changefreq' => $this->normalizeFrequency($item['changefreq'] ?? $item['freq'] ?? null),
                'priority' => $this->normalizePriority($item['priority'] ?? null),
            ];
        }

        Log::info('Live sitemap translation filter', [
            'locale' => $locale,
            'module' => $module,
            'api_records' => count($entries),
            'added_urls' => count($normalized),
            'empty_translation_removed' => $emptyTranslations,
            'german_fallback_removed' => $germanFallbacks,
            'duplicates' => $duplicates,
            'empty_sitemaps' => $normalized === [] ? 1 : 0,
        ]);

        return $normalized;
    }

    private function sitemapApiLocale(string $locale): string
    {
        // Content endpoints use `cz`, but the sitemap API exposes Czech under
        // the public locale code `cs`.
        return LocaleMapper::toWeb($locale);
    }

    private function indexable(array $item): bool
    {
        if (! empty($item['deleted_at']) || ! empty($item['trashed'])) {
            return false;
        }

        foreach (['active', 'is_active', 'published', 'is_published', 'indexable', 'seo_index'] as $field) {
            if (array_key_exists($field, $item) && ! filter_var($item[$field], FILTER_VALIDATE_BOOLEAN)) {
                return false;
            }
        }

        if (array_key_exists('noindex', $item) && filter_var($item['noindex'], FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $status = strtolower(trim((string) ($item['status'] ?? '')));
        $robots = strtolower((string) ($item['robots'] ?? data_get($item, 'seo.robots', '')));

        return ! in_array($status, ['inactive', 'draft', 'deleted', 'trashed', 'archived', 'disabled'], true)
            && ! str_contains($robots, 'noindex');
    }

    private function productionUrl(mixed $value, string $locale): ?string
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return null;
        }

        $path = parse_url(trim((string) $value), PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return null;
        }

        $path = LocaleMapper::normalizeWebUrl('/'.ltrim($path, '/'));
        $path = preg_replace('#/+#', '/', $path) ?: $path;

        if ($locale === 'de') {
            $path = preg_replace(
                '#^/de/gastronomy-cleaning(?=-|$)#',
                '/de/gastronomiereinigung',
                $path
            ) ?: $path;
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        if (count($segments) === 2 && ($canonicalSlug = LegacyUrlNormalizer::serviceSlug($segments[1]))) {
            $path = '/'.$segments[0].'/'.$canonicalSlug;
        }

        if ($path !== "/{$locale}" && ! str_starts_with($path, "/{$locale}/")) {
            return null;
        }

        $url = $this->canonicalUrls->url($path, $locale);

        return $url;
    }

    private function pagination(array $payload, int $fallbackPage): array
    {
        $candidates = [
            $payload,
            is_array($payload['data'] ?? null) ? $payload['data'] : [],
            is_array($payload['meta'] ?? null) ? $payload['meta'] : [],
            is_array(data_get($payload, 'data.meta')) ? data_get($payload, 'data.meta') : [],
            is_array(data_get($payload, 'data.pagination')) ? data_get($payload, 'data.pagination') : [],
        ];
        $lastPage = $fallbackPage;

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate['last_page'] ?? null)) {
                $lastPage = max($lastPage, (int) $candidate['last_page']);
            } elseif (is_numeric($candidate['total_pages'] ?? null)) {
                $lastPage = max($lastPage, (int) $candidate['total_pages']);
            } elseif (is_numeric($candidate['total'] ?? null) && is_numeric($candidate['per_page'] ?? null) && (int) $candidate['per_page'] > 0) {
                $lastPage = max($lastPage, (int) ceil((int) $candidate['total'] / (int) $candidate['per_page']));
            }
        }

        return ['last_page' => max(1, $lastPage)];
    }

    private function xmlEntries(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $document = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);

            if (! $document || strtolower($document->getName()) !== 'urlset') {
                return [];
            }

            return collect($document->url ?? [])->map(fn ($item) => [
                'loc' => trim((string) $item->loc),
                'lastmod' => trim((string) $item->lastmod),
                'changefreq' => trim((string) $item->changefreq),
                'priority' => trim((string) $item->priority),
            ])->filter(fn ($item) => $item['loc'] !== '')->values()->all();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function xmlIndexEntries(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $document = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);

            if (! $document || strtolower($document->getName()) !== 'sitemapindex') {
                return [];
            }

            return collect($document->sitemap ?? [])->map(function ($item) {
                $url = trim((string) $item->loc);
                $path = LocaleMapper::normalizeWebUrl('/'.ltrim((string) parse_url($url, PHP_URL_PATH), '/'));

                if (! preg_match('#^/[a-z]{2}/sitemap-[A-Za-z]+(?:-\d+)?\.xml$#', $path)) {
                    return null;
                }

                return [
                    'path' => $path,
                    'lastmod' => $this->normalizeLastmod((string) $item->lastmod),
                ];
            })->filter()->unique('path')->sortBy('path')->values()->all();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function indexDocument(string $path, array $entries): array
    {
        $baseUrl = $this->baseUrl();
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= "\n<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">";

        foreach ($entries as $entry) {
            $item = is_array($entry) ? $entry : ['path' => $entry, 'lastmod' => null];
            $xml .= "\n<sitemap>\n<loc>".$this->escape($baseUrl.$item['path']).'</loc>';

            if (! empty($item['lastmod'])) {
                $xml .= "\n<lastmod>".$this->escape($item['lastmod']).'</lastmod>';
            }

            $xml .= "\n</sitemap>";
        }

        $xml .= "\n</sitemapindex>";

        return $this->document($path, $xml);
    }

    private function urlsetDocument(string $path, array $entries): array
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= "\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">";

        foreach ($entries as $entry) {
            $xml .= "\n<url>\n<loc>".$this->escape($entry['loc']).'</loc>';

            foreach (['lastmod', 'changefreq', 'priority'] as $field) {
                if ($entry[$field] !== null && $entry[$field] !== '') {
                    $xml .= "\n<{$field}>".$this->escape($entry[$field])."</{$field}>";
                }
            }

            $xml .= "\n</url>";
        }

        $xml .= "\n</urlset>";

        return $this->document($path, $xml);
    }

    private function document(string $path, string $xml): array
    {
        return [
            'path' => $path,
            'xml' => $xml,
            'etag' => '"'.hash('sha256', $xml).'"',
        ];
    }

    private function normalizeLastmod(mixed $value): ?string
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toAtomString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeFrequency(mixed $value): ?string
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'], true)
            ? $value
            : null;
    }

    private function normalizePriority(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        $priority = (float) $value;

        return $priority >= 0 && $priority <= 1 ? number_format($priority, 1, '.', '') : null;
    }

    private function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function cacheTtl(): int
    {
        return max(60, (int) config('sitemap.live_cache_seconds', 21600));
    }

    private function emptyTtl(): int
    {
        return max(60, (int) config('sitemap.empty_cache_seconds', 3600));
    }

    private function emptyKey(string $path): string
    {
        return 'sitemap:live:'.OmrConfig::tenantId().':'.$this->canonicalUrls->cacheScope().':v2:empty:'.sha1($path);
    }

    private function validatedKey(string $path): string
    {
        return 'sitemap:live:'.OmrConfig::tenantId().':'.$this->canonicalUrls->cacheScope().':v3:validated:'.sha1($path);
    }

    private function failureKey(string $path): string
    {
        return 'sitemap:live:'.OmrConfig::tenantId().':'.$this->canonicalUrls->cacheScope().':v1:failed:'.sha1($path);
    }

    private function unavailableStateKey(string $path): string
    {
        return 'sitemap:live:'.OmrConfig::tenantId().':'.$this->canonicalUrls->cacheScope().':v1:unavailable-state:'.sha1($path);
    }

    private function rememberUnavailable(string $path, string $reason, int $retryAfterSeconds): void
    {
        Cache::put($this->unavailableStateKey($path), [
            'reason' => $reason,
            'retry_at' => now()->addSeconds(max(60, $retryAfterSeconds))->getTimestamp(),
        ], now()->addDays(30));
    }

    private function refreshDueUnavailableEntries(array $entries): void
    {
        $probed = 0;

        foreach ($entries as $entry) {
            $path = is_array($entry) ? ($entry['path'] ?? null) : null;
            $state = is_string($path) ? Cache::get($this->unavailableStateKey($path)) : null;

            if (! is_array($state)
                || (int) ($state['retry_at'] ?? PHP_INT_MAX) > now()->getTimestamp()
                || $probed >= 2) {
                continue;
            }

            if (! preg_match('#^/([a-z]{2})/sitemap-([A-Za-z]+)(?:-(\d+))?\.xml$#', $path, $matches)) {
                continue;
            }

            $probed++;

            try {
                $this->module(
                    LocaleMapper::toWeb($matches[1]),
                    $this->normalizeModuleName($matches[2]) ?? $matches[2],
                    isset($matches[3]) ? max(1, (int) $matches[3]) : null
                );
            } catch (\Throwable) {
                // module() refreshes the durable unavailable state. The index
                // continues serving every other known-good sitemap.
            }
        }
    }

    private function rememberUnknownForeignEntries(array $entries): void
    {
        if (! (bool) config('sitemap.require_raw_translations', true)) {
            return;
        }

        foreach ($entries as $entry) {
            $path = is_array($entry) ? ($entry['path'] ?? null) : null;

            if (! is_string($path)
                || $this->localeFromSitemapPath($path) === 'de'
                || $this->moduleFromSitemapPath($path) === 'serviceLocation'
                || Cache::has($this->validatedKey($path))
                || Cache::has($this->unavailableStateKey($path))) {
                continue;
            }

            $this->rememberUnavailable($path, 'unvalidated', 60);
        }
    }

    private function validModulePath(string $url, string $locale, string $module): bool
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $prefix = $locale.'/';
        $relative = $path === $locale ? '' : (str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : null);

        if ($relative === null) {
            return false;
        }

        if ($module === 'pages') {
            return ! str_contains($relative, '/')
                && ! LocaleMapper::isSupportedWeb($relative)
                && ! in_array(strtolower($relative), ['404', 'keine-antwort-500', 'blog'], true);
        }

        return true;
    }

    private function baseUrl(): string
    {
        return $this->canonicalUrls->baseUrl()
            ?? throw new RuntimeException('Tenant canonical domain is missing; sitemap cannot be generated.');
    }

    private function localeFromSitemapPath(string $path): ?string
    {
        return preg_match('#^/([a-z]{2})/sitemap-#', $path, $matches)
            ? LocaleMapper::toWeb($matches[1])
            : null;
    }

    private function moduleFromSitemapPath(string $path): ?string
    {
        if (! preg_match('#^/[a-z]{2}/sitemap-([A-Za-z]+)(?:-\d+)?\.xml$#', $path, $matches)) {
            return null;
        }

        return $this->normalizeModuleName($matches[1]);
    }

    private function normalizeModuleName(string $module): ?string
    {
        $normalized = strtolower(preg_replace('/[^A-Za-z]/', '', $module) ?: '');

        foreach (config('sitemap.modules', []) as $allowed) {
            if (strtolower((string) $allowed) === $normalized) {
                return (string) $allowed;
            }
        }

        return null;
    }
}
