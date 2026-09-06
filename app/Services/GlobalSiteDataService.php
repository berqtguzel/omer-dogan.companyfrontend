<?php

namespace App\Services;

use App\Http\Controllers\MenuController;
use App\Http\Controllers\SettingsController;
use App\Support\OmrConfig;

final class GlobalSiteDataService
{
    private array $settings = [];
    private array $menus = [];

    public function settings(string $locale): array
    {
        if (! config('corporate_home.content_ready')) {
            if (config('corporate_home.preview_content')) {
                return \App\Support\CorporatePreviewContent::settings($locale);
            }
            return ['general' => ['site_name' => config('corporate_home.site_name')]];
        }
        $tenant = OmrConfig::tenantId();
        $key = $tenant.'|'.$locale;

        return $this->settings[$key] ??= SettingsController::getFrontendSettings($tenant, $locale);
    }

    public function menus(string $locale): array
    {
        if (! config('corporate_home.content_ready')) {
            if (config('corporate_home.preview_content')) {
                return \App\Support\CorporatePreviewContent::menus($locale);
            }
            return ['header' => [], 'footer' => []];
        }
        $key = OmrConfig::tenantForSharedContent().'|'.$locale;

        // Let OmrCachedClient serve fresh/stale data during upstream cooldown.
        return $this->menus[$key] ??= [
            'header' => MenuController::getFrontendHeaderMenu($locale),
            'footer' => MenuController::getFrontendFooterMenu($locale),
        ];
    }
}
