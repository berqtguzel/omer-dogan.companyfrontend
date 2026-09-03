<?php

namespace App\Services;

use App\Exceptions\EmptySitemapException;
use App\Support\LocaleMapper;

final class LocalSitemapService
{
    public function index(?string $locale = null): array
    {
        $locales = $locale ? [LocaleMapper::toWeb($locale)] : config('sitemap.locales', []);
        $entries = collect($locales)
            ->filter(fn ($code) => LocaleMapper::isSupportedWeb($code))
            ->map(fn ($code) => $this->baseUrl()."/{$code}/sitemap-pages.xml")
            ->values()
            ->all();

        if ($entries === []) {
            throw new EmptySitemapException('No local sitemap locales are configured.');
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($entries as $url) {
            $xml .= "\n<sitemap><loc>".$this->escape($url).'</loc></sitemap>';
        }
        $xml .= "\n</sitemapindex>";

        return $this->document($xml, count($entries));
    }

    public function module(string $locale, string $module, ?int $page = null): array
    {
        $locale = LocaleMapper::toWeb($locale);
        if (! LocaleMapper::isSupportedWeb($locale) || $module !== 'pages' || ($page !== null && $page > 1)) {
            throw new EmptySitemapException('Unknown local sitemap module.');
        }

        $paths = ['', '/reinigungsleistungen', '/kontakt', '/blog'];
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($paths as $path) {
            $url = $this->baseUrl()."/{$locale}{$path}";
            $xml .= "\n<url><loc>".$this->escape($url).'</loc></url>';
        }
        $xml .= "\n</urlset>";

        return $this->document($xml, count($paths));
    }

    private function document(string $xml, int $count): array
    {
        return [
            'xml' => $xml,
            'count' => $count,
            'etag' => '"'.hash('sha256', $xml).'"',
            'source' => 'local',
        ];
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('sitemap.base_url'), '/');
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
