<?php

namespace App\Data;

use App\Support\LocaleMapper;

final class CorporatePageData
{
    public static function from(?array $page, string $locale): array
    {
        $page ??= [];
        $alternates = \App\Support\CorporateRoutes::alternates($page);
        if (! in_array($page['status'] ?? 'active', ['active', 'published', 1, true], true)) {
            $page = [];
        }
        $translation = collect($page['translations'] ?? [])->first(fn ($row) =>
            is_array($row) && LocaleMapper::toWeb($row['language_code'] ?? '') === $locale
        );
        if ($locale !== 'de' && ! $translation) {
            $page = [];
        } else {
            $page = $locale === 'de' ? array_replace($page, $translation ?? []) : ($translation ?? []);
        }
        $seo = is_array($page['seo'] ?? null) ? $page['seo'] : [];
        $content = is_string($page['content'] ?? null) ? $page['content'] : '';
        $image = $page['image'] ?? '';
        $image = is_array($image) ? ($image['url'] ?? '') : $image;
        $title = NavigationData::text($page['name'] ?? '');
        return [
            'title' => $title, 'content' => $content,
            'alternates' => $alternates,
            'image' => is_string($image) && preg_match('#^(https?://|/(?!/))#i', $image) ? $image : '',
            'available' => $title !== '' || trim(strip_tags($content)) !== '',
            'seo' => [
                'title' => NavigationData::text($page['meta_title'] ?? $seo['meta_title'] ?? $title),
                'description' => NavigationData::text($page['meta_description'] ?? $seo['meta_description'] ?? ''),
                'keywords' => NavigationData::text($page['meta_keywords'] ?? $seo['meta_keywords'] ?? ''),
            ],
        ];
    }
}
