<?php

namespace App\Providers;

use App\Http\Controllers\LanguagesController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\SettingsController;
use App\Support\OmrConfig;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Inertia::share('global', function () {
            $locale = session('locale', OmrConfig::defaultLocale());
            $tenantId = OmrConfig::tenantId();

            $defaults = [
                'site_primary_color' => '#0d6efd',
                'site_secondary_color' => '#6c757d',
                'site_accent_color' => '#f59e0b',
                'button_color' => '#0d6efd',
                'text_color' => '#111827',
                'background_color' => '#ffffff',
            ];

            try {
                $settings = SettingsController::getFrontendSettings($tenantId, $locale) ?? [];
                $mergedColors = array_merge($defaults, $settings['colors'] ?? []);
            } catch (\Throwable $e) {
                $mergedColors = $defaults;
            }

            try {
                $headerMenu = MenuController::getFrontendHeaderMenu($locale) ?? [];
                $footerMenu = MenuController::getFrontendFooterMenu($locale) ?? [];
            } catch (\Throwable $e) {
                $headerMenu = [];
                $footerMenu = [];
            }

            try {
                $langData = LanguagesController::getLanguages($tenantId, $locale) ?? [];
                $languages = $langData['languages'] ?? [];
                $defaultLang = $langData['defaultCode'] ?? $locale;
            } catch (\Throwable $e) {
                $languages = [];
                $defaultLang = $locale;
            }

            return [
                'tenantId' => $tenantId,
                'locale' => $locale,
                'colors' => $mergedColors,
                'settings' => $settings,
                'languages' => $languages,
                'defaultLang' => $defaultLang,
                'menus' => [
                    'header' => $headerMenu,
                    'footer' => $footerMenu,
                ],
            ];
        });
    }
}
