<?php

namespace App\Services;

use App\Data\HomePageData;
use App\Http\Controllers\StaticPageController;
use App\Support\OmrConfig;

final class CorporateContentService
{
    private array $pages = [];

    public function pages(string $locale): array
    {
        if (! config('corporate_home.content_ready')) {
            return [];
        }
        $key = OmrConfig::tenantId().'|'.OmrConfig::tenantForSharedContent().'|'.$locale;
        // Request memoization only. The existing controller owns fresh/stale
        // caches, tenant selection, snapshots and upstream cooldown behavior.
        return $this->pages[$key] ??= StaticPageController::getPages($locale);
    }

    public function data(string $locale, bool $prefixed, array $sliders = [], array $settings = []): array
    {
        if (! config('corporate_home.content_ready') && config('corporate_home.preview_content')) {
            return \App\Support\CorporatePreviewContent::home($locale, $prefixed);
        }
        return (new HomePageData($this->pages($locale), $locale, $prefixed,
            app(TenantCanonicalUrlResolver::class)->baseUrl(OmrConfig::tenantId())
        ))->build($sliders, config('corporate_home'), $settings);
    }
}
