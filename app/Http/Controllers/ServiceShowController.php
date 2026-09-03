<?php

namespace App\Http\Controllers;

use App\Services\ServiceTranslationResolver;
use App\Support\LocaleMapper;
use App\Support\Omr\OmrCatalog;
use App\Support\OmrConfig;
use Inertia\Inertia;

class ServiceShowController extends Controller
{
    private static function normalizeSlug(?string $text): string
    {
        $text = str_replace(['/', '\\'], '-', (string) $text);
        $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text) ?: $text;
        $text = preg_replace('/[^A-Za-z0-9]+/', '-', $text);

        return trim(strtolower($text), '-');
    }

    private static function normalizeGermanSlug(?string $text): string
    {
        $text = str_replace(
            ['Ä', 'Ö', 'Ü', 'ä', 'ö', 'ü', 'ß', '/', '\\'],
            ['Ae', 'Oe', 'Ue', 'ae', 'oe', 'ue', 'ss', '-', '-'],
            (string) $text
        );

        $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text) ?: $text;
        $text = preg_replace('/[^A-Za-z0-9]+/', '-', $text);

        return trim(strtolower($text), '-');
    }

    private static function slugVariants(?string $text): array
    {
        $variants = collect([
            self::normalizeSlug($text),
            self::normalizeGermanSlug($text),
        ])
            ->filter()
            ->unique()
            ->values();

        return $variants
            ->concat($variants->map(fn ($value) => str_replace(
                ['ae', 'oe', 'ue'],
                ['a', 'o', 'u'],
                (string) $value
            )))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function slugsFromItem(array $item): array
    {
        $candidates = [];

        foreach (['slug', 'identifier', 'handle', 'path', 'url', 'permalink', 'route'] as $key) {
            if (! empty($item[$key]) && is_scalar($item[$key])) {
                $candidates[] = (string) $item[$key];
            }
        }

        $translations = $item['translations'] ?? [];

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
            ->flatMap(fn ($value) => self::slugVariants((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function rootCandidateSlugs(array $item): array
    {
        $values = [
            $item['slug'] ?? null,
            $item['category_slug'] ?? null,
            $item['service_slug'] ?? null,
            $item['identifier'] ?? null,
            $item['handle'] ?? null,
            data_get($item, 'category.slug'),
            data_get($item, 'service.slug'),
            data_get($item, 'parent.slug'),
            data_get($item, 'category.name'),
            data_get($item, 'category.title'),
            data_get($item, 'service.name'),
            data_get($item, 'service.title'),
            data_get($item, 'parent.name'),
            data_get($item, 'parent.title'),
            $item['name'] ?? null,
            $item['title'] ?? null,
        ];

        return collect($values)
            ->filter(fn ($value) => is_scalar($value) && trim((string) $value) !== '')
            ->flatMap(fn ($value) => self::slugVariants((string) $value))
            ->merge($this->slugsFromItem($item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function isRootParent(array $item): bool
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

    private function findMainService(array $rootCategories, string $serviceSlug): ?array
    {
        $targetVariants = self::slugVariants($serviceSlug);

        return collect($rootCategories)->first(function ($item) use ($targetVariants) {
            if (! is_array($item)) {
                return false;
            }

            if (! $this->isRootParent($item)) {
                return false;
            }

            return ! empty(array_intersect($this->rootCandidateSlugs($item), $targetVariants));
        });
    }

    /**
     * Some category records expose only SEO/entity copy while their localized
     * content fields are empty. Keep the detail page useful without inventing
     * copy while keeping the new service-only site independent of old records.
     */
    private function ensurePresentationContent(array $service, string $serviceSlug): array
    {
        $name = collect([
            $service['name'] ?? null,
            $service['title'] ?? null,
            $service['category_name'] ?? null,
            data_get($service, 'category.name'),
        ])->first(fn ($value) => is_string($value) && trim($value) !== '');

        if (! $name) {
            $metaTitle = trim((string) (data_get($service, 'meta.title') ?? $service['meta_title'] ?? ''));
            $name = trim(explode('|', $metaTitle)[0] ?? '');
        }

        if (! $name) {
            $name = str($serviceSlug)->replace('-', ' ')->title()->toString();
        }

        $content = collect([
            $service['content'] ?? null,
            $service['description'] ?? null,
            $service['long_description'] ?? null,
            $service['body'] ?? null,
            $service['details'] ?? null,
            data_get($service, 'meta.description'),
            $service['meta_description'] ?? null,
            data_get($service, 'entity.expertise'),
            data_get($service, 'entity.about'),
        ])->first(function ($value) {
            return is_string($value)
                && trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '') !== '';
        });

        if ($content && trim($content) === strip_tags($content)) {
            $content = '<p>'.e(trim($content)).'</p>';
        }

        return array_merge($service, [
            'name' => $name,
            'content' => $content ?: '',
            'short_description' => $service['short_description']
                ?? data_get($service, 'meta.description')
                ?? $service['meta_description']
                ?? '',
        ]);
    }

    public function show($locale, $serviceSlug)
    {
        $mainTenant = OmrConfig::tenantForSharedContent();
        $locale = LocaleMapper::toWeb($locale);
        $serviceSlug = self::normalizeSlug($serviceSlug);

        if (! $mainTenant || $serviceSlug === '') {
            abort(404);
        }

        $rootCategories = OmrCatalog::rootCategories($mainTenant, $locale);
        $mainService = $this->findMainService($rootCategories, $serviceSlug);

        if (! $mainService && $locale !== 'de') {
            $fallbackRootCategories = OmrCatalog::rootCategories($mainTenant, 'de');
            $mainService = $this->findMainService($fallbackRootCategories, $serviceSlug);
        }

        if (! $mainService) {
            abort(404);
        }

        $resolver = app(ServiceTranslationResolver::class);
        $sourceService = $mainService;
        $mainService = $resolver->resolve($mainService, $locale, $mainTenant);
        $mainService = $this->ensurePresentationContent($mainService, $serviceSlug);
        $indexable = (bool) data_get($mainService, '_translation.indexable', false);
        $germanSlug = $resolver->publicSlug($sourceService, 'de', $serviceSlug);

        if ($locale !== 'de' && ! $indexable) {
            return redirect()->to("/de/{$germanSlug}", 301);
        }

        $availableLocales = $resolver->availableWebLocales($sourceService, $mainTenant);

        $canonicalLocale = $indexable ? $locale : 'de';
        $canonicalSlug = $resolver->publicSlug($sourceService, $canonicalLocale, $serviceSlug);
        $alternates = collect($availableLocales)->map(function ($code) use ($resolver, $sourceService, $serviceSlug) {
            $slug = $resolver->publicSlug($sourceService, $code, $serviceSlug);

            return [
                'code' => $code,
                'href' => "/{$code}/{$slug}",
            ];
        })->values()->all();
        return Inertia::render('Services/Show', [
            'service' => $mainService,
            'services' => [],
            'category' => $mainService['category'] ?? null,
            'slug' => $serviceSlug,
            'locale' => $locale,
            'seo' => [
                'indexable' => $indexable,
                'canonical' => "/{$canonicalLocale}/{$canonicalSlug}",
                'alternates' => $alternates,
                'x_default' => "/de/{$germanSlug}",
            ],
        ]);
    }
}
