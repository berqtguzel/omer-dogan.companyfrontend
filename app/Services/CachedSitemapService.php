<?php

namespace App\Services;

use App\Exceptions\EmptySitemapException;
use App\Http\Controllers\StaticPageController;
use App\Support\LocaleMapper;
use App\Support\Omr\OmrCatalog;
use App\Support\OmrConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Builds sitemap XML exclusively from application caches and snapshots.
 * No method in this class performs an HTTP request.
 */
class CachedSitemapService
{
    private ?array $pages = null;

    private ?array $services = null;

    private array $moduleEntries = [];

    public function __construct(
        private readonly ServiceTranslationResolver $serviceTranslations,
        private readonly TenantCanonicalUrlResolver $canonicalUrls,
    ) {}

    public function index(?string $onlyLocale = null): array
    {
        $locales = $onlyLocale ? [LocaleMapper::toWeb($onlyLocale)] : LocaleMapper::WEB_LOCALES;
        $sitemaps = [];

        foreach ($locales as $locale) {
            if (! LocaleMapper::isSupportedWeb($locale)) {
                continue;
            }

            foreach (config('sitemap.modules', []) as $module) {
                if ($this->entries($locale, (string) $module) === []) {
                    continue;
                }

                $sitemaps[] = [
                    'loc' => $this->url("/{$locale}/sitemap-{$module}.xml"),
                ];
            }
        }

        if ($sitemaps === []) {
            throw new EmptySitemapException('No cached sitemap records are available.');
        }

        return $this->document($this->indexXml($sitemaps), count($sitemaps));
    }

    public function module(string $locale, string $module, ?int $page = null): array
    {
        $locale = LocaleMapper::toWeb($locale);

        if (! LocaleMapper::isSupportedWeb($locale) || ($page !== null && $page > 1)) {
            throw new EmptySitemapException('Cached sitemap page is empty.');
        }

        $entries = $this->entries($locale, $module);

        if ($entries === []) {
            throw new EmptySitemapException("No cached sitemap records exist for [{$locale}:{$module}].");
        }

        return $this->document($this->urlsetXml($entries), count($entries));
    }

    private function entries(string $locale, string $module): array
    {
        $key = $locale.':'.$module;

        if (array_key_exists($key, $this->moduleEntries)) {
            return $this->moduleEntries[$key];
        }

        return $this->moduleEntries[$key] = match ($module) {
            'pages' => $this->pageEntries($locale),
            'services' => $this->serviceEntries($locale),
            'blog' => $this->blogEntries($locale),
            default => [],
        };
    }

    private function pageEntries(string $locale): array
    {
        $entries = [];

        foreach ($this->cachedPages() as $page) {
            if (! is_array($page) || ! $this->indexable($page)) {
                continue;
            }

            $slug = $this->pageSlug($page);

            // This route is intentionally disabled by DynamicSlugController.
            if ($slug === '' || $slug === 'standorte') {
                continue;
            }

            $translations = $this->pageTranslations($page);

            if (! isset($translations[$locale])) {
                continue;
            }

            $alternates = [];

            foreach ($translations as $translationLocale => $translation) {
                $translationSlug = $this->translatedSlug($translation, $slug);
                $alternates[$translationLocale] = $this->url('/'.$translationLocale.'/'.$this->pageRouteSlug($translationSlug));
            }

            $entries[] = [
                'loc' => $alternates[$locale],
                'lastmod' => $this->lastmod($translations[$locale], $page),
                'alternates' => $alternates,
            ];
        }

        return $this->uniqueEntries($entries);
    }

    private function serviceEntries(string $locale): array
    {
        $entries = [];

        foreach ($this->cachedServices() as $service) {
            if (! is_array($service) || ! $this->indexable($service)) {
                continue;
            }

            $alternates = [];

            foreach (LocaleMapper::WEB_LOCALES as $translationLocale) {
                if (! $this->serviceTranslations->hasUsableTranslation($service, $translationLocale)) {
                    continue;
                }

                $slug = $this->serviceTranslations->publicSlug($service, $translationLocale);

                if ($slug !== '') {
                    $alternates[$translationLocale] = $this->url('/'.$translationLocale.'/'.$slug);
                }
            }

            if (! isset($alternates[$locale])) {
                continue;
            }

            $entries[] = [
                'loc' => $alternates[$locale],
                'lastmod' => $this->lastmod($this->translation($service, $locale) ?? [], $service),
                'alternates' => $alternates,
            ];
        }

        return $this->uniqueEntries($entries);
    }

    private function blogEntries(string $locale): array
    {
        $entries = [];

        foreach ($this->cachedBlogPosts($locale) as $post) {
            if (! is_array($post) || ! $this->indexable($post) || ! $this->hasText($post, ['title', 'name'])
                || ! $this->hasText($post, ['content', 'excerpt', 'body'])) {
                continue;
            }

            $path = $this->blogPath($post);

            if ($path === null) {
                continue;
            }

            $entries[] = [
                'loc' => $this->url('/'.$locale.'/'.$path),
                'lastmod' => $this->lastmod($post),
                'alternates' => [],
            ];
        }

        return $this->uniqueEntries($entries);
    }

    private function cachedPages(): array
    {
        if ($this->pages !== null) {
            return $this->pages;
        }

        foreach (array_merge(['de'], LocaleMapper::WEB_LOCALES) as $locale) {
            $pages = StaticPageController::getCachedPages($locale);

            if ($pages !== []) {
                return $this->pages = $pages;
            }
        }

        return $this->pages = [];
    }

    private function cachedServices(): array
    {
        if ($this->services !== null) {
            return $this->services;
        }

        $tenant = OmrConfig::tenantForSharedContent();

        return $this->services = $tenant === '' ? [] : OmrCatalog::rootCategories($tenant, 'de');
    }

    private function cachedBlogPosts(string $locale): array
    {
        $tenant = OmrConfig::tenantId();
        $posts = [];

        for ($page = 1; $page <= 100; $page++) {
            $key = 'blog_posts_v4_'.md5($tenant.'|'.$locale.'|all|'.$page);
            $payload = Cache::get($key);

            if (! is_array($payload)) {
                break;
            }

            $rows = is_array($payload['data'] ?? null) ? $payload['data'] : [];
            $posts = array_merge($posts, array_values(array_filter($rows, 'is_array')));
            $lastPage = max(1, (int) data_get($payload, 'meta.last_page', 1));

            if ($page >= $lastPage) {
                break;
            }
        }

        return $posts;
    }

    private function pageTranslations(array $page): array
    {
        $translations = [];

        foreach (is_array($page['translations'] ?? null) ? $page['translations'] : [] as $key => $translation) {
            if (! is_array($translation)) {
                continue;
            }

            $locale = LocaleMapper::toWeb((string) ($translation['language_code'] ?? $translation['locale'] ?? $key));

            if (LocaleMapper::isSupportedWeb($locale)
                && $this->hasText($translation, ['name', 'title'])
                && $this->hasText($translation, ['content', 'description', 'body'])) {
                $translations[$locale] = $translation;
            }
        }

        return $translations;
    }

    private function translation(array $record, string $locale): ?array
    {
        foreach (is_array($record['translations'] ?? null) ? $record['translations'] : [] as $key => $translation) {
            if (! is_array($translation)) {
                continue;
            }

            $code = LocaleMapper::toWeb((string) ($translation['language_code'] ?? $translation['locale'] ?? $key));

            if ($code === $locale) {
                return $translation;
            }
        }

        return null;
    }

    private function pageSlug(array $page): string
    {
        foreach (['slug', 'identifier', 'handle'] as $field) {
            $slug = trim((string) ($page[$field] ?? ''), '/');

            if ($slug !== '') {
                return Str::slug(rawurldecode(basename($slug)));
            }
        }

        return '';
    }

    private function translatedSlug(array $translation, string $fallback): string
    {
        foreach (['slug', 'identifier', 'handle', 'path'] as $field) {
            $slug = trim((string) ($translation[$field] ?? ''), '/');

            if ($slug !== '') {
                return Str::slug(rawurldecode(basename($slug)));
            }
        }

        return $fallback;
    }

    private function pageRouteSlug(string $slug): string
    {
        return match ($slug) {
            'services' => 'reinigungsleistungen',
            default => $slug,
        };
    }

    private function blogPath(array $post): ?string
    {
        $rawPath = trim((string) ($post['path'] ?? ''), '/');

        if ($rawPath !== '') {
            $rawPath = preg_replace('#^[a-z]{2}/#', '', $rawPath) ?: $rawPath;
            $rawPath = preg_replace('#^blog/#', '', $rawPath) ?: $rawPath;

            return 'blog/'.$rawPath;
        }

        $slug = trim((string) ($post['slug'] ?? ''), '/');

        if ($slug === '') {
            return null;
        }

        $category = trim((string) data_get($post, 'category.slug', $post['category_slug'] ?? ''), '/');

        return 'blog/'.($category !== '' ? $category.'/' : '').$slug;
    }

    private function indexable(array $record): bool
    {
        if (! empty($record['deleted_at']) || ! empty($record['trashed'])) {
            return false;
        }

        foreach (['active', 'is_active', 'published', 'is_published', 'indexable', 'seo_index'] as $field) {
            if (array_key_exists($field, $record) && ! filter_var($record[$field], FILTER_VALIDATE_BOOLEAN)) {
                return false;
            }
        }

        $status = strtolower(trim((string) ($record['status'] ?? '')));
        $robots = strtolower((string) ($record['robots'] ?? data_get($record, 'seo.robots', '')));

        return ! in_array($status, ['inactive', 'draft', 'deleted', 'trashed', 'archived', 'disabled'], true)
            && ! filter_var($record['noindex'] ?? false, FILTER_VALIDATE_BOOLEAN)
            && ! str_contains($robots, 'noindex');
    }

    private function hasText(array $record, array $fields): bool
    {
        foreach ($fields as $field) {
            $value = $record[$field] ?? null;

            if (is_scalar($value) && trim(preg_replace('/[\s\x{00A0}]+/u', ' ', strip_tags((string) $value)) ?? '') !== '') {
                return true;
            }
        }

        return false;
    }

    private function lastmod(array ...$records): ?string
    {
        foreach ($records as $record) {
            foreach (['updated_at', 'published_at', 'created_at'] as $field) {
                $value = $record[$field] ?? null;

                if (is_scalar($value) && ($timestamp = strtotime((string) $value)) !== false) {
                    return gmdate('Y-m-d', $timestamp);
                }
            }
        }

        return null;
    }

    private function uniqueEntries(array $entries): array
    {
        return collect($entries)->unique('loc')->sortBy('loc')->values()->all();
    }

    private function document(string $xml, int $count): array
    {
        return [
            'xml' => $xml,
            'etag' => '"'.hash('sha256', $xml).'"',
            'count' => $count,
            'source' => 'cache',
        ];
    }

    private function urlsetXml(array $entries): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">';

        foreach ($entries as $entry) {
            $xml .= "\n<url>\n<loc>".$this->escape($entry['loc']).'</loc>';

            if (! empty($entry['lastmod'])) {
                $xml .= "\n<lastmod>".$this->escape($entry['lastmod']).'</lastmod>';
            }

            foreach ($entry['alternates'] ?? [] as $locale => $url) {
                $xml .= "\n<xhtml:link rel=\"alternate\" hreflang=\"".$this->escape($locale).'" href="'.$this->escape($url).'" />';
            }

            if (! empty($entry['alternates']['de'])) {
                $xml .= "\n<xhtml:link rel=\"alternate\" hreflang=\"x-default\" href=\"".$this->escape($entry['alternates']['de']).'" />';
            }

            $xml .= "\n</url>";
        }

        return $xml."\n</urlset>";
    }

    private function indexXml(array $entries): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($entries as $entry) {
            $xml .= "\n<sitemap>\n<loc>".$this->escape($entry['loc'])."</loc>\n</sitemap>";
        }

        return $xml."\n</sitemapindex>";
    }

    private function url(string $path): string
    {
        return $this->canonicalUrls->url($path)
            ?? throw new \RuntimeException('Tenant canonical domain is missing.');
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
