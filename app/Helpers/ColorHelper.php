<?php

use App\Http\Controllers\SettingsController;
use App\Support\OmrConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

if (! function_exists('omrFlattenSettingsRows')) {
    /**
     * Eski kodlarla uyumluluk için bırakıldı.
     *
     * @param  array<string, mixed>|null  $json
     * @return array<string, mixed>
     */
    function omrFlattenSettingsRows(?array $json): array
    {
        if (! is_array($json)) {
            return [];
        }

        $d = data_get($json, 'data');

        if (is_array($d) && isset($d['data']) && is_array($d['data'])) {
            $rows = $d['data'];
        } elseif (is_array($d)) {
            $rows = $d;
        } else {
            $rows = [];
        }

        $flat = [];

        foreach ($rows as $row) {
            if (is_array($row) && isset($row['key'])) {
                $flat[$row['key']] = $row['value'] ?? null;
            }
        }

        return $flat;
    }
}

if (! function_exists('getSiteColors')) {
    function getSiteColors(): array
    {
        $defaults = [
            'site_primary_color' => '#007bff',
            'site_secondary_color' => '#6c757d',
            'site_accent_color' => '#22d3ee',
            'button_color' => '#007bff',
            'text_color' => '#333333',
            'h1_color' => '#111111',
            'h2_color' => '#333333',
            'h3_color' => '#555555',
            'link_color' => '#2563eb',
            'background_color' => '#ffffff',
            'header_background_color' => '#ffffff',
            'footer_background_color' => '#f8f9fa',
        ];

        try {
            $tenantId = OmrConfig::tenantId();

            if (! $tenantId) {
                return $defaults;
            }

            $locale = strtolower(session('locale', OmrConfig::defaultLocale()));
            $cacheKey = "site_colors_v2_{$tenantId}_{$locale}";
            $missing = '__site_colors_cache_missing__';

            $cached = Cache::get($cacheKey, $missing);

            if ($cached !== $missing) {
                return is_array($cached)
                    ? array_merge($defaults, array_intersect_key($cached, $defaults))
                    : $defaults;
            }

            $settings = SettingsController::getFrontendSettings($tenantId, $locale, false);
            $colors = $settings['colors'] ?? [];

            if (! is_array($colors) || empty($colors)) {
                Cache::put($cacheKey, $defaults, now()->addMinutes(10));

                return $defaults;
            }

            $colors = array_intersect_key($colors, $defaults);
            $colors = array_filter($colors, fn ($value) => $value !== null && $value !== '');

            $merged = array_merge($defaults, $colors);

            Cache::put($cacheKey, $merged, now()->addDays(7));

            return $merged;
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch site colors: '.$e->getMessage());
        }

        return $defaults;
    }
}