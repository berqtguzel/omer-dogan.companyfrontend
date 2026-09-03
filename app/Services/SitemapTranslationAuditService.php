<?php

namespace App\Services;

use App\Support\LocaleMapper;
use App\Support\OmrConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Reads the content APIs without applying the frontend's German fallback.
 */
class SitemapTranslationAuditService
{
    private array $catalogs = [];

    private array $serviceIndexes = [];

    public function __construct(private readonly ServiceTranslationResolver $serviceTranslations) {}

    public function reset(): void
    {
        $this->catalogs = [];
        $this->serviceIndexes = [];
    }

    public function inspect(string $url, string $locale, string $module): array
    {
        $slug = $this->slugFromUrl($url, $locale);

        return match ($module) {
            'pages' => $this->inspectPage($slug, $locale),
            'blog' => $this->inspectBlog($slug, $locale),
            'services' => $this->inspectService($slug, $locale, false),
            'locations' => $this->inspectLocation($slug, $locale),
            'serviceLocation' => $this->inspectService($slug, $locale, true),
            default => ['allowed' => false, 'reason' => 'empty_translation'],
        };
    }

    private function inspectPage(string $slug, string $locale): array
    {
        $slug = $slug === '' ? 'startseite' : $slug;
        $page = collect($this->content('pages', 'de'))->first(
            fn ($item) => is_array($item) && $this->sameSlug($item['slug'] ?? null, $slug)
        );
        $translation = is_array($page)
            ? ($this->translation($page, $locale) ?: ($locale === 'de' ? $page : null))
            : null;

        return $this->required($translation, ['name', 'title'], ['content', 'description', 'body']);
    }

    private function inspectBlog(string $slug, string $locale): array
    {
        $localized = collect($this->content('blog-posts', $locale))->first(
            fn ($item) => is_array($item) && $this->recordMatchesSlug($item, $slug)
        );

        if (! is_array($localized)) {
            return ['allowed' => false, 'reason' => 'empty_translation'];
        }

        $required = $this->required($localized, ['title', 'name'], ['content', 'excerpt', 'body']);

        if (! $required['allowed'] || $locale === 'de') {
            return $required;
        }

        // Blog API has no translations[] or resolved-locale marker. Its observed
        // fallback contract returns the complete German title+body unchanged.
        // Comparing the whole core payload avoids rejecting shared single words.
        $german = collect($this->content('blog-posts', 'de'))->first(function ($item) use ($localized, $slug) {
            return is_array($item)
                && (($localized['id'] ?? null) !== null
                    ? (string) ($item['id'] ?? '') === (string) $localized['id']
                    : $this->recordMatchesSlug($item, $slug));
        });

        if (is_array($german) && hash_equals($this->coreFingerprint($german), $this->coreFingerprint($localized))) {
            return ['allowed' => false, 'reason' => 'german_fallback'];
        }

        return ['allowed' => true, 'reason' => null];
    }

    private function inspectService(string $slug, string $locale, bool $locationPage): array
    {
        $indexes = $this->serviceIndexes(! $locationPage);
        $service = $indexes['slug'][$this->normalizeSlug($slug)] ?? null;

        if (! is_array($service)) {
            return ['allowed' => false, 'reason' => 'empty_translation'];
        }

        if ($locationPage && trim((string) ($service['city'] ?? '')) === '') {
            return ['allowed' => false, 'reason' => 'empty_translation'];
        }

        $decision = $this->serviceTranslationDecision($service, $locale);

        if (! $decision['allowed'] || ! $locationPage) {
            return $decision;
        }

        $parentId = $service['parent_id'] ?? null;

        if ($parentId === null || $parentId === '') {
            return ['allowed' => false, 'reason' => 'empty_translation'];
        }

        $parent = $indexes['id'][(string) $parentId] ?? null;

        return is_array($parent)
            ? $this->serviceTranslationDecision($parent, $locale)
            : ['allowed' => false, 'reason' => 'empty_translation'];
    }

    private function inspectLocation(string $slug, string $locale): array
    {
        $services = collect($this->serviceIndexes()['location'][$this->normalizeSlug($slug)] ?? []);

        if ($services->isEmpty()) {
            return ['allowed' => false, 'reason' => 'empty_translation'];
        }

        return $services->contains(fn ($service) => $this->serviceTranslationDecision($service, $locale)['allowed'])
            ? ['allowed' => true, 'reason' => null]
            : ['allowed' => false, 'reason' => 'empty_translation'];
    }

    private function serviceTranslationDecision(array $service, string $locale): array
    {
        if ($this->serviceTranslations->hasUsableTranslation($service, $locale)) {
            return ['allowed' => true, 'reason' => null];
        }

        $translation = $this->translation($service, $locale);
        $german = $locale === 'de' ? null : $this->translation($service, 'de');

        if (is_array($translation) && is_array($german)) {
            $localizedContent = $this->firstText($translation, ['content']);
            $germanContent = $this->firstText($german, ['content']);

            if ($localizedContent !== '' && $localizedContent === $germanContent) {
                return ['allowed' => false, 'reason' => 'german_fallback'];
            }
        }

        return ['allowed' => false, 'reason' => 'empty_translation'];
    }

    private function serviceIndexes(bool $rootsOnly = false): array
    {
        $key = $rootsOnly ? 'roots' : 'all';

        if (isset($this->serviceIndexes[$key])) {
            return $this->serviceIndexes[$key];
        }

        $indexes = ['slug' => [], 'id' => [], 'location' => []];

        foreach ($this->content('services', 'de', $rootsOnly ? ['parent_id' => 'null'] : []) as $service) {
            if (! is_array($service)) {
                continue;
            }

            if (($service['id'] ?? null) !== null) {
                $indexes['id'][(string) $service['id']] = $service;
            }

            foreach ([$service['slug'] ?? null, $service['path'] ?? null] as $candidate) {
                if (is_scalar($candidate) && ($slug = basename(trim((string) parse_url((string) $candidate, PHP_URL_PATH), '/'))) !== '') {
                    $indexes['slug'][$this->normalizeSlug($slug)] = $service;
                }
            }

            foreach (is_array($service['translations'] ?? null) ? $service['translations'] : [] as $translation) {
                $candidate = is_array($translation) ? ($translation['slug'] ?? $translation['path'] ?? null) : null;

                if (is_scalar($candidate) && ($slug = basename(trim((string) parse_url((string) $candidate, PHP_URL_PATH), '/'))) !== '') {
                    $indexes['slug'][$this->normalizeSlug($slug)] = $service;
                }
            }

            foreach ([$service['city'] ?? null, $service['district'] ?? null] as $location) {
                if (is_scalar($location) && trim((string) $location) !== '') {
                    $indexes['location'][$this->normalizeSlug((string) $location)][] = $service;
                }
            }
        }

        return $this->serviceIndexes[$key] = $indexes;
    }

    private function content(string $path, string $locale, array $extraQuery = []): array
    {
        $key = $path.':'.$locale.':'.sha1(json_encode($extraQuery));

        if (array_key_exists($key, $this->catalogs)) {
            return $this->catalogs[$key];
        }

        $tenant = OmrConfig::tenantForSharedContent();
        $cacheKey = 'sitemap:live:'.$tenant.':raw-translations:v3:'.sha1($key);
        $cached = Cache::get($cacheKey);
        $stale = Cache::get($cacheKey.':stale');

        if (is_array($cached) && $cached !== []) {
            return $this->catalogs[$key] = $cached;
        }

        $page = 1;
        $lastPage = 1;
        $items = [];

        do {
            try {
                $response = Http::withoutVerifying()
                    ->connectTimeout(5)
                    ->timeout(20)
                    ->retry((int) config('sitemap.retry_count', 2), (int) config('sitemap.retry_sleep_ms', 750), throw: false)
                    ->withHeaders(['Accept' => 'application/json', 'X-Tenant-ID' => $tenant])
                    ->get(OmrConfig::apiUrl($path), array_merge($extraQuery, [
                        'tenant' => $tenant,
                        'locale' => LocaleMapper::toApi($locale),
                        'lang' => LocaleMapper::toApi($locale),
                        'page' => $page,
                        'per_page' => 500,
                    ]));
            } catch (\Throwable $e) {
                if (is_array($stale) && $stale !== []) {
                    return $this->catalogs[$key] = $stale;
                }

                throw new RuntimeException("Translation API request failed for [{$path}:{$locale}:{$page}]: {$e->getMessage()}", previous: $e);
            }

            if (! $response->successful()) {
                if (is_array($stale) && $stale !== []) {
                    return $this->catalogs[$key] = $stale;
                }

                throw new RuntimeException("Translation API returned HTTP {$response->status()} for [{$path}:{$locale}:{$page}].");
            }

            $json = $response->json();
            $rows = is_array($json) ? ($json['data'] ?? []) : [];

            if (! is_array($rows)) {
                throw new RuntimeException("Translation API returned an invalid collection for [{$path}:{$locale}:{$page}].");
            }

            $items = array_merge($items, array_values(array_filter($rows, 'is_array')));
            $lastPage = max(1, (int) (data_get($json, 'meta.last_page') ?? data_get($json, 'pagination.last_page') ?? 1));
            $page++;
        } while ($page <= $lastPage && $page <= (int) config('sitemap.max_api_pages', 1000));

        if ($lastPage >= $page && $page > (int) config('sitemap.max_api_pages', 1000)) {
            throw new RuntimeException("Translation API pagination exceeds the configured limit for [{$path}:{$locale}].");
        }

        if ($items === []) {
            throw new RuntimeException("Translation API returned no records for [{$path}:{$locale}].");
        }

        Cache::put($cacheKey, $items, now()->addSeconds((int) config('sitemap.live_cache_seconds', 21600)));
        Cache::put($cacheKey.':stale', $items, now()->addDays(30));

        return $this->catalogs[$key] = $items;
    }

    private function translation(array $record, string $locale): ?array
    {
        $translations = $record['translations'] ?? null;

        if (! is_array($translations) || $translations === []) {
            return null;
        }

        foreach ($translations as $key => $translation) {
            if (! is_array($translation) || $translation === []) {
                continue;
            }

            $code = $translation['language_code'] ?? $translation['locale'] ?? $translation['lang'] ?? $key;

            if (is_scalar($code) && LocaleMapper::toWeb((string) $code) === $locale) {
                return $translation;
            }
        }

        return null;
    }

    private function required(?array $record, array $titleFields, array $contentFields): array
    {
        if (! is_array($record) || $record === []) {
            return ['allowed' => false, 'reason' => 'empty_translation'];
        }

        $title = $this->firstText($record, $titleFields);
        $content = $this->firstText($record, $contentFields);

        return $title !== '' && $content !== ''
            ? ['allowed' => true, 'reason' => null]
            : ['allowed' => false, 'reason' => 'empty_translation'];
    }

    private function firstText(array $record, array $fields): string
    {
        foreach ($fields as $field) {
            $value = $record[$field] ?? null;

            if (is_scalar($value) && trim(strip_tags((string) $value)) !== '') {
                return trim(strip_tags((string) $value));
            }
        }

        return '';
    }

    private function coreFingerprint(array $record): string
    {
        return hash('sha256', mb_strtolower($this->firstText($record, ['title', 'name']))."\n".$this->firstText($record, ['content', 'excerpt', 'body']));
    }

    private function recordMatchesSlug(array $record, string $slug): bool
    {
        foreach ([$record['slug'] ?? null, $record['path'] ?? null] as $candidate) {
            if ($this->sameSlug($candidate, $slug)) {
                return true;
            }
        }

        foreach (is_array($record['translations'] ?? null) ? $record['translations'] : [] as $translation) {
            if (is_array($translation) && $this->sameSlug($translation['slug'] ?? $translation['path'] ?? null, $slug)) {
                return true;
            }
        }

        return false;
    }

    private function sameSlug(mixed $candidate, string $slug): bool
    {
        if (! is_scalar($candidate)) {
            return false;
        }

        $candidate = basename(trim((string) parse_url((string) $candidate, PHP_URL_PATH), '/'));

        return $this->normalizeSlug($candidate) === $this->normalizeSlug($slug);
    }

    private function normalizeSlug(string $value): string
    {
        return Str::slug(rawurldecode(trim($value, '/')));
    }

    private function slugFromUrl(string $url, string $locale): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        if ($path === $locale) {
            return '';
        }

        return basename($path);
    }
}
