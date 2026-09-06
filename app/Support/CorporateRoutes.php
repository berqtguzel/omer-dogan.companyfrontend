<?php

namespace App\Support;

use App\Data\NavigationData;

final class CorporateRoutes
{
    public static function configuredPath(string $slug): ?string
    {
        foreach (config('corporate_home.business_area_pages', []) as $id => $source) {
            if ($slug === $source) return '/geschaeftsbereiche/'.$id;
        }
        foreach (['company_pages' => 'unternehmen', 'project_pages' => 'projekte'] as $key => $prefix) {
            if (in_array($slug, config('corporate_home.'.$key, []), true)) return '/'.$prefix.'/'.$slug;
        }
        foreach (config('corporate_home.static_pages', []) as $route => $source) {
            if ($slug === $source) return '/'.$route;
        }
        return null;
    }

    public static function pagePath(array $page, string $locale): string
    {
        $slug = NavigationData::text($page['slug'] ?? '');
        if ($configured = self::configuredPath($slug)) return $configured;
        $translation = collect($page['translations'] ?? [])->first(fn ($row) =>
            is_array($row) && LocaleMapper::toWeb($row['language_code'] ?? '') === $locale
        );
        $translated = NavigationData::text($translation['slug'] ?? $slug);
        return '/'.trim($translated, '/');
    }

    public static function alternates(array $page): array
    {
        if (! in_array($page['status'] ?? 'active', ['active', 'published', 1, true], true)) return [];
        $result = [];
        foreach ($page['translations'] ?? [] as $row) {
            if (! is_array($row)) continue;
            $locale = LocaleMapper::toWeb($row['language_code'] ?? '');
            if (! LocaleMapper::isSupportedWeb($locale) || NavigationData::text($row['name'] ?? '') === '' || NavigationData::text($row['content'] ?? '') === '') continue;
            $result[$locale] = '/'.$locale.self::pagePath($page, $locale);
        }
        if (! isset($result['de']) && NavigationData::text($page['name'] ?? '') !== '' && NavigationData::text($page['content'] ?? '') !== '') {
            $result['de'] = '/de'.self::pagePath($page, 'de');
        }
        return $result;
    }
}
