<?php

namespace App\Data;

use App\Support\LocaleMapper;
use Illuminate\Support\Str;

final class HomePageData
{
    private array $pages = [];

    public function __construct(
        array $pages,
        private string $locale,
        private bool $prefixed,
        private string $origin,
    ) {
        foreach ($pages as $page) {
            if (! is_array($page) || ! in_array($page['status'] ?? 'active', ['active', 'published', 1, true], true)) {
                continue;
            }
            $translation = collect($page['translations'] ?? [])->first(fn ($item) =>
                is_array($item) && LocaleMapper::toWeb($item['language_code'] ?? '') === $locale
            );
            // The catalog can return German fallback records. Do not expose
            // those as translated corporate content in another locale.
            if ($locale !== 'de' && ! $translation) {
                continue;
            }
            $slug = NavigationData::text($page['slug'] ?? '');
            if ($slug !== '') {
                $this->pages[$slug] = $locale === 'de' ? array_replace($page, $translation ?? []) : ($translation ?? []);
                $this->pages[$slug]['slug'] = $this->pages[$slug]['slug'] ?? $slug;
                $this->pages[$slug]['alternates'] = \App\Support\CorporateRoutes::alternates($page);
            }
        }
    }

    public function build(array $sliders, array $sources, array $settings = []): array
    {
        $slide = collect($sliders['sliders'] ?? [])->first(fn ($item) => is_array($item)) ?? [];
        $group = $this->pages[$sources['group_page'] ?? ''] ?? [];
        $career = $this->pages[$sources['career_page'] ?? ''] ?? [];
        $areas = [];
        foreach ($sources['business_area_pages'] ?? [] as $key => $slug) {
            $page = $this->pages[$slug] ?? [];
            if (! $page || NavigationData::text($page['name'] ?? '') === '') {
                continue;
            }
            $areas[] = [
                'id' => $key,
                'alternates' => $page['alternates'],
                'title' => NavigationData::text($page['name'] ?? ''),
                'description' => $this->summary($page['content'] ?? ''),
                'body' => $this->summary($page['content'] ?? '', 20000),
                'image' => $this->image($page['image'] ?? ''),
                'link' => $page ? $this->link('/geschaeftsbereiche/'.$key) : null,
                'count' => null,
            ];
        }
        $primary = $this->link($slide['buttonUrl'] ?? '');
        if (in_array($primary['href'], ['', '#'], true)) {
            $primary = $this->link($group ? '#group-vision' : '/kontakt');
        }

        return [
            'seo' => [
                'title' => NavigationData::text($settings['seo']['meta_title'] ?? ''),
                'description' => NavigationData::text($settings['seo']['meta_description'] ?? ''),
                'keywords' => NavigationData::text($settings['seo']['meta_keywords'] ?? ''),
                'image' => $this->image($settings['seo']['og_image'] ?? ''),
                'ogTitle' => NavigationData::text($settings['seo']['og_title'] ?? ''),
                'ogDescription' => NavigationData::text($settings['seo']['og_description'] ?? ''),
            ],
            'hero' => [
                'title' => NavigationData::text($slide['title'] ?? $settings['general']['site_name'] ?? ''),
                'description' => $this->summary($slide['description'] ?? '', 360),
                'image' => $this->image($slide['video_poster'] ?? '') ?: $this->image($slide['image'] ?? ''),
                'video' => $this->image($slide['video_url'] ?? ''),
                'primary' => $primary,
                'primaryLabel' => $primary['href'] === '#group-vision' ? '' : NavigationData::text($slide['buttonLabel'] ?? ''),
            ],
            'businessAreas' => $areas,
            'companies' => $this->cards($sources['company_pages'] ?? [], true),
            'projects' => $this->cards($sources['project_pages'] ?? [], false),
            // No country dataset has been confirmed. Never infer operations
            // from contact addresses, available languages or service cities.
            'countries' => [],
            'metrics' => [],
            'vision' => [
                'title' => NavigationData::text($group['name'] ?? ''),
                'description' => $this->summary($group['content'] ?? '', 360),
                'image' => $this->image($group['image'] ?? ''),
                'values' => [],
                'link' => $group ? $this->link('/'.$group['slug']) : null,
            ],
            'careerLink' => $career ? $this->link('/'.$career['slug']) : null,
            'contactLink' => $this->link('/kontakt'),
        ];
    }

    private function cards(array $slugs, bool $company): array
    {
        $cards = [];
        foreach ($slugs as $slug) {
            $page = $this->pages[$slug] ?? null;
            if (! $page || NavigationData::text($page['name'] ?? '') === '') {
                continue;
            }
            $cards[] = [
                'id' => $slug,
                'alternates' => $page['alternates'],
                'name' => NavigationData::text($page['name']),
                'description' => $this->summary($page['content'] ?? ''),
                'body' => $this->summary($page['content'] ?? '', 20000),
                'sector' => '',
                'location' => '',
                // A page image is not automatically a company logo.
                'image' => $company ? '' : $this->image($page['image'] ?? ''),
                'link' => $this->link('/'.($company ? 'unternehmen' : 'projekte').'/'.$slug),
            ];
        }

        return $cards;
    }

    private function summary(mixed $html, int $length = 190): string
    {
        if (! is_string($html)) {
            return '';
        }
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html);
        $html = preg_replace('#</(?:p|h[1-6]|li|div)>|<br\s*/?>#i', ' ', $html);

        return Str::limit(preg_replace('/\s+/u', ' ', NavigationData::text($html)), $length);
    }

    private function image(mixed $value): string
    {
        $url = is_array($value) ? ($value['url'] ?? '') : $value;

        return is_string($url) && preg_match('#^(https?://|/(?!/))#i', $url) ? $url : '';
    }

    private function link(mixed $value): array
    {
        $link = NavigationData::link($value, $this->locale, $this->prefixed, $this->origin);

        return [...$link, 'newTab' => $link['external']];
    }
}
