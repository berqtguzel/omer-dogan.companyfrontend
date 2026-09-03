<?php

namespace App\Services;

use App\Support\LocaleMapper;
use App\Support\LegacyUrlNormalizer;
use App\Support\Omr\OmrCatalog;
use App\Support\OmrConfig;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SitemapWarmService
{
    private ?array $translationCatalog = null;

    private array $metrics = [];

    public function __construct(
        private readonly SitemapCacheStore $store,
        private readonly ServiceTranslationResolver $translationResolver,
        private readonly TenantCanonicalUrlResolver $canonicalUrls
    ) {}

    public function warm(?string $requestedLocale = null, bool $force = false): array
    {
        $requestedLocale = $requestedLocale ? LocaleMapper::toWeb($requestedLocale) : null;

        if ($requestedLocale && ! in_array($requestedLocale, config('sitemap.locales', []), true)) {
            return $this->failure("Unsupported sitemap locale [{$requestedLocale}].");
        }

        $metadata = $this->store->metadata();

        if (! $force && $this->isFresh($metadata, $requestedLocale)) {
            return array_merge($metadata, ['ok' => true, 'skipped' => true]);
        }

        $tenant = OmrConfig::tenantId();

        if ($tenant === '') {
            return $this->failure('OMR sitemap tenant is missing.');
        }

        try {
            $scope = $this->canonicalUrls->cacheScope($tenant);

            return Cache::lock("sitemap:warm:lock:{$tenant}:{$scope}", 1800)->block(3, function () use ($requestedLocale) {
                return $this->build($requestedLocale);
            });
        } catch (\Throwable $e) {
            Log::error('Sitemap warm failed; previous active sitemap preserved', [
                'tenant' => $tenant,
                'locale' => $requestedLocale,
                'error' => $e->getMessage(),
            ]);

            return $this->failure($e->getMessage());
        }
    }

    private function build(?string $requestedLocale): array
    {
        $this->metrics = [
            'api_requests' => 0,
            'api_pages' => 0,
            'duplicates_removed' => 0,
            'invalid_urls_removed' => 0,
            'translations_removed' => 0,
            'non_indexable_removed' => 0,
            'empty_modules_skipped' => 0,
            'per_locale' => [],
            'per_module' => [],
        ];

        $rootPayload = $this->fetchApi('sitemap');
        $rootData = $this->payloadData($rootPayload);
        $availableLocales = $this->discoverLocales($rootData);

        if ($availableLocales === []) {
            throw new RuntimeException('Sitemap API root response contains no active locales.');
        }

        $locales = $requestedLocale ? [$requestedLocale] : $availableLocales;

        if ($requestedLocale && ! in_array($requestedLocale, $availableLocales, true)) {
            throw new RuntimeException("Locale [{$requestedLocale}] is not active in the sitemap API.");
        }

        $documents = $this->carriedDocuments($requestedLocale);
        $seenUrls = $this->urlsFromDocuments($documents);

        foreach ($locales as $locale) {
            // The real root contract already embeds locales_detail.modules. Use it
            // first so a full warm does not spend one extra API request per locale.
            // Older API responses remain supported through the locale endpoint.
            $moduleEntries = $this->moduleEntries([], $rootData, $locale);

            if ($moduleEntries === []) {
                $localePayload = $this->fetchApi('sitemap/'.$this->sitemapApiLocale($locale));
                $localeData = $this->payloadData($localePayload);
                $moduleEntries = $this->moduleEntries($localeData, $rootData, $locale);
            }

            if ($moduleEntries === []) {
                throw new RuntimeException("Sitemap API returned no modules for locale [{$locale}].");
            }

            $descriptors = $this->moduleDescriptors($moduleEntries);

            if ($descriptors === []) {
                throw new RuntimeException("Sitemap API module URLs are invalid for locale [{$locale}].");
            }

            foreach ($descriptors as $module => $pages) {
                $rawEntries = $this->fetchModuleEntries($locale, $module, $pages);
                $entries = $this->normalizeEntries($rawEntries, $locale, $module, $seenUrls);

                if ($entries === []) {
                    // The upstream page itself was non-empty (fetchModuleEntries
                    // rejects empty API pages), but every record was filtered as
                    // non-canonical/non-indexable/fallback content. Do not publish
                    // an empty child sitemap and continue with the other modules.
                    $this->metrics['empty_modules_skipped']++;

                    continue;
                }

                foreach ($this->documentsForModule($locale, $module, $entries) as $name => $document) {
                    $documents[$name] = $document;
                }

                $this->metrics['per_locale'][$locale] = ($this->metrics['per_locale'][$locale] ?? 0) + count($entries);
                $this->metrics['per_module']["{$locale}:{$module}"] = count($entries);
            }
        }

        if ($documents === []) {
            throw new RuntimeException('No non-empty sitemap documents were generated.');
        }

        uasort($documents, fn ($a, $b) => strcmp((string) $a['path'], (string) $b['path']));
        $indexEntries = collect($documents)->map(fn ($document) => [
            'loc' => $this->baseUrl().$document['path'],
            'lastmod' => $document['lastmod'] ?? null,
        ])->values()->all();
        $indexXml = $this->buildIndexXml($indexEntries);
        $documents = ['index' => $this->document('index', '/sitemap.xml', $indexXml, count($indexEntries), null, 'index', $this->latestLastmod($indexEntries))] + $documents;

        foreach ($documents as $name => $document) {
            $expectedRoot = $name === 'index' ? 'sitemapindex' : 'urlset';

            if (! $this->validXml($document['xml'], $expectedRoot)) {
                throw new RuntimeException("Generated XML validation failed for [{$name}].");
            }
        }

        $buildId = now()->format('YmdHis').'-'.bin2hex(random_bytes(4));

        foreach ($documents as $name => $document) {
            $this->store->putBuilding($buildId, $name, $document);
        }

        $metadata = array_merge($this->metrics, [
            'ok' => true,
            'generated_at' => now()->toAtomString(),
            'tenant' => OmrConfig::tenantId(),
            'locales' => collect($documents)->pluck('locale')->filter()->unique()->sort()->values()->all(),
            'sitemap_count' => count($documents) - 1,
            'url_count' => collect($documents)->except('index')->sum('count'),
            'partial_locale' => $requestedLocale,
        ]);

        $this->store->activate($buildId, array_keys($documents), $metadata);

        return array_merge($metadata, ['version' => $buildId]);
    }

    private function fetchApi(string $path, array $query = []): array
    {
        $tenant = OmrConfig::tenantId();
        $query = array_merge($query, ['tenant' => $tenant]);

        if (($this->metrics['api_requests'] ?? 0) > 0) {
            $sleepMilliseconds = max(0, (int) config('sitemap.request_sleep_ms', 0));

            if ($sleepMilliseconds > 0) {
                usleep($sleepMilliseconds * 1000);
            }
        }

        $this->metrics['api_requests']++;

        try {
            $response = Http::withoutVerifying()
                ->connectTimeout(5)
                ->timeout(20)
                ->retry(
                    (int) config('sitemap.retry_count', 2),
                    (int) config('sitemap.retry_sleep_ms', 750),
                    throw: false
                )
                ->withHeaders([
                    'Accept' => 'application/json, application/xml;q=0.9, text/xml;q=0.8',
                    'X-Tenant-ID' => $tenant,
                ])
                ->get(OmrConfig::apiUrl($path), $query);
        } catch (\Throwable $e) {
            Log::error('Sitemap API request exception', [
                'path' => $path,
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException("Sitemap API request failed for [{$path}]: {$e->getMessage()}", previous: $e);
        }

        if (! $response->successful()) {
            Log::error('Sitemap API request failed', [
                'path' => $path,
                'query' => $query,
                'status' => $response->status(),
                'retry_after' => $response->header('Retry-After'),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

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

        if (
            str_contains($contentType, 'xml')
            || str_starts_with($body, '<?xml')
            || str_starts_with($body, '<urlset')
            || str_starts_with($body, '<sitemapindex')
        ) {
            return $body !== '' ? ['_xml' => $body] : [];
        }

        try {
            $json = $response->json();

            return is_array($json) ? $json : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function discoverLocales(array $data): array
    {
        $locales = collect($data['locales'] ?? [])
            ->merge(collect($data['locales_detail'] ?? [])->pluck('locale'))
            ->map(fn ($locale) => LocaleMapper::toWeb(is_scalar($locale) ? (string) $locale : ''))
            ->filter(fn ($locale) => in_array($locale, config('sitemap.locales', []), true))
            ->unique()
            ->values()
            ->all();

        return $locales;
    }

    private function moduleEntries(array $localeData, array $rootData, string $locale): array
    {
        $entries = $localeData['modules'] ?? [];

        if (is_array($entries) && $entries !== []) {
            return array_values(array_filter($entries, 'is_array'));
        }

        $detail = collect($rootData['locales_detail'] ?? [])->first(
            fn ($item) => is_array($item) && LocaleMapper::toWeb($item['locale'] ?? '') === $locale
        );

        $entries = is_array($detail) ? ($detail['modules'] ?? []) : [];

        return is_array($entries) ? array_values(array_filter($entries, 'is_array')) : [];
    }

    private function moduleDescriptors(array $entries): array
    {
        $descriptors = [];

        foreach ($entries as $entry) {
            $url = $entry['url'] ?? $entry['loc'] ?? null;
            $candidate = $entry['module'] ?? $entry['type'] ?? $entry['name'] ?? null;
            $page = isset($entry['page']) && is_numeric($entry['page']) ? max(1, (int) $entry['page']) : null;

            if ((! is_scalar($candidate) || trim((string) $candidate) === '') && is_scalar($url)) {
                $basename = basename((string) parse_url((string) $url, PHP_URL_PATH));

                if (preg_match('/^sitemap-([A-Za-z]+)(?:-(\d+))?\.xml$/', $basename, $matches)) {
                    $candidate = $matches[1];
                    $page = isset($matches[2]) ? (int) $matches[2] : $page;
                }
            }

            $module = $this->normalizeModule(is_scalar($candidate) ? (string) $candidate : '');

            if (! $module) {
                continue;
            }

            $descriptors[$module][] = $page ?: 1;
        }

        foreach ($descriptors as $module => $pages) {
            $descriptors[$module] = array_values(array_unique(array_map('intval', $pages)));
            sort($descriptors[$module]);
        }

        return $descriptors;
    }

    private function normalizeModule(string $module): ?string
    {
        $normalized = strtolower(preg_replace('/[^A-Za-z]/', '', $module) ?: '');

        foreach (config('sitemap.modules', []) as $allowed) {
            if (strtolower($allowed) === $normalized) {
                return $allowed;
            }
        }

        return null;
    }

    private function fetchModuleEntries(string $locale, string $module, array $initialPages): array
    {
        $queue = $initialPages ?: [1];
        $fetched = [];
        $entries = [];
        $maxPages = (int) config('sitemap.max_api_pages', 1000);

        while ($queue !== []) {
            sort($queue);
            $page = (int) array_shift($queue);

            if ($page < 1 || $page > $maxPages || isset($fetched[$page])) {
                continue;
            }

            $fetched[$page] = true;
            $query = ($page > 1 || $module === 'serviceLocation' || count($initialPages) > 1)
                ? ['page' => $page]
                : [];
            $payload = $this->fetchApi(
                'sitemap/'.$this->sitemapApiLocale($locale)."/{$module}",
                $query
            );
            $pageEntries = $this->rawEntries($payload);

            if ($pageEntries === []) {
                throw new RuntimeException("Sitemap API returned an empty [{$locale}:{$module}:{$page}] page.");
            }

            $entries = array_merge($entries, $pageEntries);
            $this->metrics['api_pages']++;
            $pagination = $this->pagination($payload, $page);

            if ($pagination['last_page'] > $maxPages) {
                throw new RuntimeException("Sitemap API pagination exceeds the configured {$maxPages} page limit.");
            }

            for ($next = $page + 1; $next <= $pagination['last_page']; $next++) {
                if (! isset($fetched[$next])) {
                    $queue[] = $next;
                }
            }

            if ($pagination['next_page'] && ! isset($fetched[$pagination['next_page']])) {
                $queue[] = $pagination['next_page'];
            }
        }

        return $entries;
    }

    private function rawEntries(array $payload): array
    {
        if (is_string($payload['_xml'] ?? null)) {
            return $this->xmlEntries($payload['_xml']);
        }

        $data = $this->payloadData($payload);

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

    private function pagination(array $payload, int $fallbackPage): array
    {
        $candidates = [
            $payload,
            is_array($payload['data'] ?? null) ? $payload['data'] : [],
            is_array($payload['meta'] ?? null) ? $payload['meta'] : [],
            is_array(data_get($payload, 'data.meta')) ? data_get($payload, 'data.meta') : [],
            is_array(data_get($payload, 'data.pagination')) ? data_get($payload, 'data.pagination') : [],
            is_array($payload['pagination'] ?? null) ? $payload['pagination'] : [],
        ];
        $currentPage = $fallbackPage;
        $lastPage = $fallbackPage;
        $nextPage = null;

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate['current_page'] ?? $candidate['page'] ?? null)) {
                $currentPage = max(1, (int) ($candidate['current_page'] ?? $candidate['page']));
            }

            if (is_numeric($candidate['last_page'] ?? null)) {
                $lastPage = max($lastPage, (int) $candidate['last_page']);
            } elseif (is_numeric($candidate['total_pages'] ?? null)) {
                $lastPage = max($lastPage, (int) $candidate['total_pages']);
            } elseif (is_numeric($candidate['total'] ?? null) && is_numeric($candidate['per_page'] ?? null) && (int) $candidate['per_page'] > 0) {
                $lastPage = max($lastPage, (int) ceil((int) $candidate['total'] / (int) $candidate['per_page']));
            }

            $nextUrl = $candidate['next_page_url'] ?? data_get($candidate, 'links.next');

            if (is_string($nextUrl) && $nextUrl !== '') {
                parse_str((string) parse_url($nextUrl, PHP_URL_QUERY), $nextQuery);
                $nextPage = is_numeric($nextQuery['page'] ?? null) ? (int) $nextQuery['page'] : $currentPage + 1;
            }
        }

        return [
            'current_page' => $currentPage,
            'last_page' => max($currentPage, $lastPage),
            'next_page' => $nextPage,
        ];
    }

    private function normalizeEntries(array $entries, string $locale, string $module, array &$seenUrls): array
    {
        $normalized = [];

        foreach ($entries as $entry) {
            $item = is_array($entry) ? $entry : ['loc' => $entry];

            if (! $this->indexable($item)) {
                $this->metrics['non_indexable_removed']++;

                continue;
            }

            $rawUrl = $item['loc'] ?? $item['url'] ?? $item['location'] ?? $item['permalink'] ?? null;
            $loc = $this->productionUrl($rawUrl, $locale);

            if (! $loc || ! $this->canonicalMatches($item, $loc, $locale)) {
                $this->metrics['invalid_urls_removed']++;

                continue;
            }

            if (! $this->translationAllowed($item, $loc, $locale, $module)) {
                $this->metrics['translations_removed']++;

                continue;
            }

            if (! $this->validModulePath($loc, $locale, $module)) {
                $this->metrics['invalid_urls_removed']++;

                continue;
            }

            if (isset($seenUrls[$loc])) {
                $this->metrics['duplicates_removed']++;

                continue;
            }

            $seenUrls[$loc] = true;
            $normalized[] = [
                'loc' => $loc,
                'lastmod' => $this->normalizeLastmod($item['lastmod'] ?? $item['updated_at'] ?? null),
                'freq' => $this->normalizeFrequency($item['changefreq'] ?? $item['freq'] ?? null),
                'priority' => $this->normalizePriority($item['priority'] ?? null),
            ];
        }

        return $normalized;
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

        if ($status !== '' && in_array($status, ['inactive', 'draft', 'deleted', 'trashed', 'archived', 'disabled'], true)) {
            return false;
        }

        $robots = strtolower((string) ($item['robots'] ?? data_get($item, 'seo.robots', '')));

        return ! str_contains($robots, 'noindex');
    }

    private function canonicalMatches(array $item, string $loc, string $locale): bool
    {
        $canonical = $item['canonical'] ?? $item['canonical_url'] ?? data_get($item, 'seo.canonical');

        if (! is_scalar($canonical) || trim((string) $canonical) === '') {
            return true;
        }

        return $this->productionUrl((string) $canonical, $locale) === $loc;
    }

    private function translationAllowed(array $item, string $loc, string $locale, string $module): bool
    {
        if ($locale === 'de') {
            return true;
        }

        if (data_get($item, '_translation.indexable') === false || data_get($item, '_translation.fallback') === true) {
            return false;
        }

        foreach (['locale', 'language_code', 'resolved_locale', '_resolved_locale'] as $field) {
            if (! empty($item[$field]) && LocaleMapper::toWeb((string) $item[$field]) !== $locale) {
                return false;
            }
        }

        if (! in_array($module, ['services', 'serviceLocation'], true)) {
            return true;
        }

        $slug = basename(trim((string) parse_url($loc, PHP_URL_PATH), '/'));
        $service = collect($this->translationCatalog())->first(function ($service) use ($slug) {
            $slugs = collect([$service['slug'] ?? null, $service['category_slug'] ?? null])
                ->merge(collect($service['translations'] ?? [])->pluck('slug'))
                ->filter()
                ->map(fn ($value) => strtolower(trim((string) $value, '/')));

            return $slugs->contains(strtolower($slug));
        });

        return is_array($service)
            && in_array($locale, $this->translationResolver->availableWebLocales($service, OmrConfig::tenantForSharedContent()), true);
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

    private function translationCatalog(): array
    {
        if (is_array($this->translationCatalog)) {
            return $this->translationCatalog;
        }

        $tenant = OmrConfig::tenantForSharedContent();
        $roots = collect(OmrCatalog::rootCategories($tenant, 'de'));

        return $this->translationCatalog = $roots->flatMap(function ($root) use ($tenant) {
            if (! is_array($root)) {
                return [];
            }

            $slug = $root['category_slug'] ?? $root['slug'] ?? null;
            $children = $slug ? OmrCatalog::services($tenant, 'de', [
                'category_slug' => $slug,
                '_max_pages' => 120,
            ]) : [];

            return [$root, ...$children];
        })->filter(fn ($item) => is_array($item))->values()->all();
    }

    private function documentsForModule(string $locale, string $module, array $entries): array
    {
        $chunks = array_chunk($entries, (int) config('sitemap.max_urls_per_file', 50000));
        $documents = [];

        foreach ($chunks as $index => $chunk) {
            $page = $index + 1;
            $isPaged = $module === 'serviceLocation' || count($chunks) > 1;
            $suffix = $isPaged ? "-{$page}" : '';
            $name = $isPaged ? "{$locale}:{$module}:{$page}" : "{$locale}:{$module}";
            $path = "/{$locale}/sitemap-{$module}{$suffix}.xml";
            $xml = $this->buildUrlsetXml($chunk);
            $documents[$name] = $this->document(
                $name,
                $path,
                $xml,
                count($chunk),
                $locale,
                $module,
                $this->latestLastmod($chunk)
            );
        }

        return $documents;
    }

    private function document(
        string $name,
        string $path,
        string $xml,
        int $count,
        ?string $locale,
        string $module,
        ?string $lastmod
    ): array {
        return [
            'name' => $name,
            'path' => $path,
            'xml' => $xml,
            'count' => $count,
            'locale' => $locale,
            'module' => $module,
            'lastmod' => $lastmod,
            'etag' => '"'.hash('sha256', $xml).'"',
        ];
    }

    private function carriedDocuments(?string $requestedLocale): array
    {
        if (! $requestedLocale) {
            return [];
        }

        return collect($this->store->documents())
            ->except('index')
            ->reject(fn ($document) => ($document['locale'] ?? null) === $requestedLocale)
            ->all();
    }

    private function urlsFromDocuments(array $documents): array
    {
        $seen = [];

        foreach ($documents as $document) {
            foreach ($this->xmlEntries((string) ($document['xml'] ?? '')) as $entry) {
                $loc = is_array($entry) ? ($entry['loc'] ?? null) : null;

                if (is_string($loc) && $loc !== '') {
                    $seen[$loc] = true;
                }
            }
        }

        return $seen;
    }

    private function buildUrlsetXml(array $entries): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= "\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">";

        foreach ($entries as $entry) {
            $xml .= "\n<url>\n<loc>".$this->escape($entry['loc']).'</loc>';

            if (! empty($entry['lastmod'])) {
                $xml .= "\n<lastmod>".$this->escape($entry['lastmod']).'</lastmod>';
            }

            if (! empty($entry['freq'])) {
                $xml .= "\n<changefreq>".$this->escape($entry['freq']).'</changefreq>';
            }

            if ($entry['priority'] !== null) {
                $xml .= "\n<priority>".$this->escape($entry['priority']).'</priority>';
            }

            $xml .= "\n</url>";
        }

        return $xml."\n</urlset>";
    }

    private function buildIndexXml(array $entries): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= "\n<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">";

        foreach ($entries as $entry) {
            $xml .= "\n<sitemap>\n<loc>".$this->escape($entry['loc']).'</loc>';

            if (! empty($entry['lastmod'])) {
                $xml .= "\n<lastmod>".$this->escape($entry['lastmod']).'</lastmod>';
            }

            $xml .= "\n</sitemap>";
        }

        return $xml."\n</sitemapindex>";
    }

    private function xmlEntries(string $xml): array
    {
        if ($xml === '') {
            return [];
        }

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

    private function validXml(string $xml, string $expectedRoot): bool
    {
        if (! str_starts_with($xml, '<?xml version="1.0" encoding="UTF-8"?>')) {
            return false;
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $document = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);

            return $document !== false && strtolower($document->getName()) === $expectedRoot;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function payloadData(array $payload): array
    {
        $data = $payload['data'] ?? $payload;

        return is_array($data) ? $data : [];
    }

    private function productionUrl(mixed $value, string $locale): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $path = parse_url($value, PHP_URL_PATH);

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

        return $this->canonicalUrls->url($path, $locale);
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

    private function latestLastmod(array $entries): ?string
    {
        return collect($entries)->pluck('lastmod')->filter()->sortDesc()->first();
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

    private function baseUrl(): string
    {
        return $this->canonicalUrls->baseUrl()
            ?? throw new RuntimeException('Tenant canonical domain is missing; sitemap was not activated.');
    }

    private function sitemapApiLocale(string $locale): string
    {
        // Content endpoints use `cz`, while sitemap endpoints use the public
        // Czech locale code `cs`.
        return LocaleMapper::toWeb($locale);
    }

    private function isFresh(array $metadata, ?string $locale): bool
    {
        if (empty($metadata['generated_at'])) {
            return false;
        }

        if ($locale && ! in_array($locale, $metadata['locales'] ?? [], true)) {
            return false;
        }

        if (! $locale) {
            $expectedLocales = collect(config('sitemap.locales', []))->sort()->values()->all();
            $cachedLocales = collect($metadata['locales'] ?? [])->sort()->values()->all();

            if ($cachedLocales !== $expectedLocales) {
                return false;
            }
        }

        try {
            return Carbon::parse($metadata['generated_at'])
                ->addHours((int) config('sitemap.fresh_hours', 24))
                ->isFuture();
        } catch (\Throwable) {
            return false;
        }
    }

    private function failure(string $message): array
    {
        return [
            'ok' => false,
            'error' => $message,
            'active_version' => $this->store->activeVersion(),
            'stale_preserved' => $this->store->activeVersion() !== null,
        ];
    }
}
