<?php

namespace App\Http\Controllers;

use App\Support\MediaUrl;
use App\Support\Omr\OmrCatalog;
use App\Support\OmrConfig;

class CategoryController extends Controller
{
    private static function localizedName(array $item, string $locale): string
    {
        $locale = strtolower($locale);
        $localeBase = strtok($locale, '-') ?: $locale;
        $translations = $item['translations'] ?? [];

        if (is_array($translations)) {
            $translation = collect($translations)->first(function ($translation) use ($locale) {
                return strtolower((string) ($translation['language_code'] ?? '')) === $locale;
            }) ?: collect($translations)->first(function ($translation) use ($localeBase) {
                return str_starts_with(strtolower((string) ($translation['language_code'] ?? '')), $localeBase);
            }) ?: collect($translations)->first(function ($translation) {
                return strtolower((string) ($translation['language_code'] ?? '')) === 'de';
            }) ?: collect($translations)->first(function ($translation) {
                return str_starts_with(strtolower((string) ($translation['language_code'] ?? '')), 'de');
            }) ?: collect($translations)->first();

            $name = is_array($translation)
                ? ($translation['name'] ?? $translation['title'] ?? null)
                : null;

            if (is_string($name) && trim($name) !== '') {
                return trim($name);
            }
        }

        return trim((string) ($item['name'] ?? $item['title'] ?? $item['slug'] ?? ''));
    }

    public static function compactForNavigation(array $categories, string $locale): array
    {
        return collect($categories)
            ->map(function ($category) use ($locale) {
                if (! is_array($category)) {
                    return null;
                }

                return array_filter([
                    'id' => $category['id'] ?? null,
                    'name' => self::localizedName($category, $locale),
                    'slug' => $category['slug'] ?? null,
                    'parent_id' => $category['parent_id'] ?? null,
                    'order' => $category['order'] ?? null,
                    'image' => MediaUrl::resolve($category['image'] ?? null),
                ], fn ($value) => $value !== null && $value !== '');
            })
            ->filter(fn ($category) => is_array($category) && ! empty($category['id']))
            ->sortBy('order')
            ->values()
            ->all();
    }

    public static function getNavigationCategories(string $locale): array
    {
        return self::compactForNavigation(self::getCategories($locale), $locale);
    }

    public static function getCategories($locale, bool $mirror = true)
    {
        $locale = strtolower((string) $locale);
        $mainTenant = OmrConfig::tenantForSharedContent();

        if (! $mainTenant) {
            return [];
        }

        $categories = OmrCatalog::rootCategories($mainTenant, $locale);

        /**
         * cz/sk/pl/tr gibi locale'lerde API içerik döndürmüyorsa sayfayı boş bırakma.
         * Aynı catalog cache'inin Almanca listesini kullan; UI locale yine korunur.
         */
        if (empty($categories) && $locale !== 'de') {
            $categories = OmrCatalog::rootCategories($mainTenant, 'de');
        }

        return $mirror ? mirror_media($categories) : $categories;
    }
}