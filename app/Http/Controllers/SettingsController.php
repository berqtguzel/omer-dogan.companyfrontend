<?php

namespace App\Http\Controllers;

use App\Support\OmrConfig;
use App\Support\MediaUrl;
use App\Support\Omr\OmrCachedClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SettingsController extends Controller
{
    private static array $runtimeSettings = [];

    private static function settingsTenant(?string $tenantId = null): string
    {
        return OmrConfig::mainTenantId()
            ?: ($tenantId ?: OmrConfig::tenantId());
    }

    private static function applySharedSettingsSections(array $settings, string $tenantId, string $locale): array
    {
        $mainTenant = self::settingsTenant($tenantId);

        if ($mainTenant === $tenantId) {
            return $settings;
        }

        $sharedSettings = self::getSettings($mainTenant, $locale, false);

        foreach (['general', 'branding', 'contact'] as $section) {
            if (! empty($sharedSettings[$section]) && is_array($sharedSettings[$section])) {
                $settings[$section] = $sharedSettings[$section];
            }
        }

        return $settings;
    }

    private static function onlyKeys(array $data, array $keys): array
    {
        $out = [];

        foreach ($keys as $target => $source) {
            if (is_int($target)) {
                $target = $source;
            }

            if (array_key_exists($source, $data)) {
                $out[$target] = $data[$source];
            }
        }

        return $out;
    }

    private static function flatSettingsFromApiData($data): array
    {
        if (! is_array($data)) {
            return [];
        }

        $flat = [];

        foreach ($data as $key => $item) {
            if (is_array($item) && array_key_exists('key', $item)) {
                $flat[(string) $item['key']] = $item['value'] ?? null;
                continue;
            }

            if (is_string($key)) {
                $flat[$key] = $item;
            }
        }

        return $flat;
    }

    private static function flatSettingsToSections(array $flat): array
    {
        $settings = [
            'general' => self::onlyKeys($flat, [
                'site_name',
                'site_description',
                'site_keywords',
                'cache_duration',
            ]),
            'seo' => self::onlyKeys($flat, [
                'sitemap_enabled',
                'sitemap_include_pages',
                'sitemap_include_services',
                'sitemap_include_categories',
                'sitemap_include_sliders',
                'sitemap_include_rooms',
                'sitemap_include_staff',
                'meta_title',
                'meta_description',
                'meta_keywords',
                'og_title',
                'og_description',
                'og_image',
            ]),
            'branding' => self::onlyKeys($flat, [
                'site_name',
                'site_logo',
                'site_logo_url',
                'site_dark_logo',
                'site_dark_logo_url',
                'site_favicon',
                'site_favicon_url',
                'logo' => 'site_logo',
                'logo_url' => 'site_logo_url',
                'dark_logo' => 'site_dark_logo',
                'dark_logo_url' => 'site_dark_logo_url',
                'favicon' => 'site_favicon',
                'favicon_url' => 'site_favicon_url',
            ]),
            'colors' => self::onlyKeys($flat, [
                'site_primary_color',
                'site_secondary_color',
                'site_accent_color',
                'button_color',
                'text_color',
                'h1_color',
                'h2_color',
                'h3_color',
                'link_color',
                'background_color',
                'header_background_color',
                'footer_background_color',
            ]),
            'social' => self::onlyKeys($flat, [
                'facebook_url',
                'instagram_url',
                'twitter_url',
                'linkedin_url',
                'youtube_url',
                'tiktok_url',
                'social_facebook' => 'facebook_url',
                'social_instagram' => 'instagram_url',
                'social_twitter' => 'twitter_url',
                'social_linkedin' => 'linkedin_url',
                'social_youtube' => 'youtube_url',
                'social_tiktok' => 'tiktok_url',
            ]),
            'footer' => self::onlyKeys($flat, [
                'footer_text',
                'footer_copyright',
            ]),
            'analytics' => self::onlyKeys($flat, [
                'google_tag_manager',
                'google_tag_manager_id',
                'google_analytics',
                'google_analytics_id',
                'google_search_console',
                'google_search_console_property',
                'google_ads_conversion',
                'hotjar_id',
            ]),
        ];

        foreach ($settings as $section => $values) {
            $settings[$section] = array_filter(
                $values,
                fn ($value) => $value !== null && $value !== ''
            );
        }

        return $settings;
    }

    private static function mergeSectionFallback(array $primary, array $fallback): array
    {
        foreach ($fallback as $key => $value) {
            if (! array_key_exists($key, $primary) || $primary[$key] === null || $primary[$key] === '') {
                $primary[$key] = $value;
                continue;
            }

            if (is_array($primary[$key]) && is_array($value)) {
                $primary[$key] = self::mergeSectionFallback($primary[$key], $value);
            }
        }

        return $primary;
    }

    private static function replaceImageTenant(?string $imageUrl, ?string $targetTenant = null): ?string
    {
        if (!$imageUrl) {
            return null;
        }

        $targetTenant = $targetTenant ?: OmrConfig::tenantId();

        if (! $targetTenant) {
            return $imageUrl;
        }

        $pattern = '/(\/storage\/)([^\/]+)(\/media\/)/';

        if (preg_match($pattern, $imageUrl, $matches)) {
            $currentTenant = $matches[2];

            if ($currentTenant === $targetTenant) {
                return $imageUrl;
            }

            return preg_replace($pattern, '$1' . $targetTenant . '$3', $imageUrl);
        }

        return $imageUrl;
    }

    private static function mediaUrlFromId(string $mediaId, ?string $tenantId = null): ?string
    {
        if (! ctype_digit($mediaId)) {
            return null;
        }

        $tenant = $tenantId ?: OmrConfig::tenantId();
        $cacheKey = "settings_media_url_{$tenant}_{$mediaId}";
        $missing = '__settings_media_url_cache_missing__';
        $cached = Cache::get($cacheKey, $missing);

        if ($cached !== $missing) {
            return is_string($cached) && $cached !== '' ? $cached : null;
        }

        if (OmrCachedClient::isCoolingDown($tenant)) {
            return null;
        }

        try {
            $response = Http::withoutVerifying()
                ->connectTimeout(5)
                ->timeout(20)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-Tenant-ID' => $tenant,
                ])
                ->get(OmrConfig::apiUrl("media/{$mediaId}"), [
                    'tenant' => $tenant,
                ]);

            if ($response->successful()) {
                $url = $response->json('data.url') ?? $response->json('url');
                $resolved = is_string($url) ? self::replaceImageTenant($url, $tenant) : null;

                if (is_string($resolved) && $resolved !== '') {
                    Cache::put($cacheKey, $resolved, now()->addDays(7));

                    return $resolved;
                }
            } elseif ($response->serverError() || $response->status() === 429) {
                OmrCachedClient::markCoolingDown($tenant, 2);
            }
        } catch (\Throwable $e) {
            OmrCachedClient::markCoolingDown($tenant, 2);

            Log::warning('Settings media resolve failed', [
                'media_id' => $mediaId,
                'error' => $e->getMessage(),
            ]);
        }

        Cache::put($cacheKey, false, now()->addMinutes(10));

        return null;
    }

    private static function logoUrlFields(): array
    {
        return [
            'site_logo' => 'site_logo_url',
            'site_dark_logo' => 'site_dark_logo_url',
            'site_favicon' => 'site_favicon_url',
            'logo' => 'logo_url',
            'dark_logo' => 'dark_logo_url',
            'logo_dark' => 'logo_dark_url',
            'favicon' => 'favicon_url',
        ];
    }

    private static function resolveMediaUrl($value, ?string $tenantId = null): ?string
    {
        if (is_array($value)) {
            if (isset($value['url']) && is_string($value['url'])) {
                return self::replaceImageTenant($value['url'], $tenantId);
            }

            if (isset($value['id'])) {
                return self::mediaUrlFromId((string) $value['id'], $tenantId);
            }
        }

        if (is_int($value) || is_float($value) || (is_string($value) && ctype_digit(trim($value)))) {
            return self::mediaUrlFromId((string) (int) $value, $tenantId);
        }

        if (is_string($value)) {
            return self::replaceImageTenant($value, $tenantId);
        }

        $resolved = MediaUrl::resolve($value);

        return is_string($resolved) ? self::replaceImageTenant($resolved, $tenantId) : null;
    }

    private static function replaceLogoImages($data, ?string $tenantId = null)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $data[$key] = self::replaceLogoImages($value, $tenantId);
                }
            }

            foreach (self::logoUrlFields() as $valueKey => $urlKey) {
                $resolved = null;

                if (array_key_exists($urlKey, $data)) {
                    $resolved = self::resolveMediaUrl($data[$urlKey], $tenantId);
                }

                if (! $resolved && array_key_exists($valueKey, $data)) {
                    $resolved = self::resolveMediaUrl($data[$valueKey], $tenantId);
                }

                if ($resolved) {
                    $data[$urlKey] = $resolved;
                } elseif (array_key_exists($valueKey, $data) && ! array_key_exists($urlKey, $data)) {
                    $data[$urlKey] = null;
                }
            }
        }

        return $data;
    }

    private static function onlyAllowedSettings(array $settings): array
    {
        $frontend = [
            'general' => self::onlyKeys($settings['general'] ?? [], [
                'site_name',
                'site_description',
                'site_keywords',
            ]),
            'seo' => self::onlyKeys($settings['seo'] ?? [], [
                'meta_title',
                'meta_description',
                'meta_keywords',
                'og_title',
                'og_description',
                'og_image',
            ]),
            'branding' => self::onlyKeys($settings['branding'] ?? [], [
                'site_name',
                'site_logo',
                'site_logo_url',
                'site_dark_logo',
                'site_dark_logo_url',
                'site_favicon',
                'site_favicon_url',
                'logo',
                'logo_url',
                'dark_logo',
                'dark_logo_url',
                'favicon',
                'favicon_url',
            ]),
            'colors' => self::onlyKeys($settings['colors'] ?? [], [
                'site_primary_color',
                'site_secondary_color',
                'site_accent_color',
                'button_color',
                'text_color',
                'h1_color',
                'h2_color',
                'h3_color',
                'link_color',
                'background_color',
                'header_background_color',
                'footer_background_color',
            ]),
            'social' => self::onlyKeys($settings['social'] ?? [], [
                'facebook_url',
                'instagram_url',
                'twitter_url',
                'linkedin_url',
                'youtube_url',
                'tiktok_url',
                'social_facebook',
                'social_instagram',
                'social_twitter',
                'social_linkedin',
                'social_youtube',
                'social_tiktok',
            ]),
            'footer' => self::onlyKeys($settings['footer'] ?? [], [
                'footer_text',
                'footer_copyright',
            ]),
            'analytics' => self::onlyKeys($settings['analytics'] ?? [], [
                'google_tag_manager',
                'google_tag_manager_id',
                'google_analytics',
                'google_analytics_id',
                'google_search_console',
                'google_search_console_property',
                'google_ads_conversion',
                'hotjar_id',
            ]),
            'contact' => [
                'contact_infos' => array_values(array_filter(
                    $settings['contact']['contact_infos'] ?? [],
                    fn ($item) => is_array($item) && ! empty($item)
                )),
                '_meta' => $settings['contact']['_meta'] ?? [],
            ],
        ];

        return array_filter(
            $frontend,
            fn ($section) => is_array($section) && ! empty($section)
        );
    }

    private static function normalizeContactSettings($payload): array
    {
        $data = is_array($payload) ? ($payload['data'] ?? $payload) : [];

        if (! is_array($data)) {
            return [];
        }

        $contactInfos = $data['contact_infos'] ?? [];

        if (! is_array($contactInfos)) {
            $contactInfos = [];
        }

        $contactInfos = collect($contactInfos)
            ->filter(fn ($item) => is_array($item) && ! empty($item))
            ->map(function (array $item) {
                $address = trim((string) ($item['address'] ?? ''));
                $postalCode = trim((string) ($item['postal_code'] ?? ''));
                $city = trim((string) ($item['city'] ?? ''));
                $country = trim((string) ($item['country'] ?? ''));

                $lineTwo = trim($postalCode . ' ' . $city);
                $formatted = collect([$address, $lineTwo, $country])
                    ->filter(fn ($value) => $value !== '')
                    ->implode('<br/>');

                if ($formatted !== '' && empty($item['formatted_address'])) {
                    $item['formatted_address'] = $formatted;
                }

                return array_filter($item, fn ($value) => $value !== null && $value !== '');
            })
            ->values()
            ->all();

        return array_filter([
            'contact_infos' => $contactInfos,
            '_meta' => is_array($data['_meta'] ?? null) ? $data['_meta'] : [],
        ], fn ($value) => is_array($value) ? ! empty($value) : $value !== null && $value !== '');
    }

    private static function cooldownKeys(?string $tenant = null): array
    {
        return collect([
            $tenant,
            OmrConfig::tenantId(),
            OmrConfig::tenantForSharedContent(),
        ])
            ->filter()
            ->unique()
            ->map(fn ($id) => 'omr_rate_limited_' . $id)
            ->values()
            ->all();
    }

    private static function isCoolingDown(?string $tenant = null): bool
    {
        return OmrCachedClient::isCoolingDown($tenant);
    }

    private static function markCoolingDown(?string $tenant = null, int $minutes = 15): void
    {
        OmrCachedClient::markCoolingDown($tenant, $minutes);
    }

    private static function fetchContactSettings(string $tenantId, string $locale, ?string $dashboardSiteId = null): array
    {
        if (self::isCoolingDown($tenantId)) {
            return [];
        }

        $response = OmrCachedClient::get(
            'settings_contact',
            'settings/contact',
            self::settingsQuery($tenantId, $locale, $dashboardSiteId),
            $tenantId,
            [
                'success_ttl' => now()->addDays(7),
                'stale_ttl' => now()->addDays(14),
                'failure_ttl' => now()->addMinutes(10),
                'rate_limit_ttl' => now()->addMinutes(15),
                'cooldown_minutes' => 15,
            ]
        );

        return $response['ok']
            ? self::normalizeContactSettings($response['json'] ?? [])
            : [];
    }

    public static function getFrontendSettings(string $tenantId, string $locale, bool $mirror = true): array
    {
        $settings = self::getSettings($tenantId, $locale, false);
        $settings = self::applySharedSettingsSections($settings, $tenantId, $locale);
        $settings = self::onlyAllowedSettings($settings);

        return $mirror ? mirror_media($settings) : $settings;
    }

    public static function getSitemapSettings(string $tenantId, string $locale): array
    {
        $settings = self::getSettings($tenantId, $locale, false);

        return self::onlyKeys($settings['seo'] ?? [], [
            'sitemap_enabled',
            'sitemap_include_pages',
            'sitemap_include_services',
            'sitemap_include_categories',
            'sitemap_include_sliders',
            'sitemap_include_rooms',
            'sitemap_include_staff',
        ]);
    }

    private static function pickFirst(array $data, array $keys)
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                return $data[$key];
            }
        }

        return null;
    }

    private static function normalizeMailSettings(array $flat): array
    {
        $mail = [
            'mailer' => self::pickFirst($flat, ['MAIL_MAILER', 'mail_mailer', 'mailer', 'smtp_mailer']),
            'host' => self::pickFirst($flat, ['MAIL_HOST', 'mail_host', 'host', 'smtp_host']),
            'port' => self::pickFirst($flat, ['MAIL_PORT', 'mail_port', 'port', 'smtp_port']),
            'username' => self::pickFirst($flat, ['MAIL_USERNAME', 'mail_username', 'username', 'smtp_username']),
            'password' => self::pickFirst($flat, ['MAIL_PASSWORD', 'mail_password', 'password', 'smtp_password']),
            'encryption' => self::pickFirst($flat, ['MAIL_ENCRYPTION', 'mail_encryption', 'encryption', 'smtp_encryption']),
            'from_address' => self::pickFirst($flat, ['MAIL_FROM_ADDRESS', 'mail_from_address', 'from_address', 'from_email', 'sender_email']),
            'from_name' => self::pickFirst($flat, ['MAIL_FROM_NAME', 'mail_from_name', 'from_name', 'sender_name']),
            'admin_email' => self::pickFirst($flat, ['MAIL_TO_ADDRESS', 'mail_to_address', 'admin_email', 'recipient_email', 'to_email']),
        ];

        $mail = array_filter($mail, fn ($value) => $value !== null && $value !== '');

        if (isset($mail['port'])) {
            $mail['port'] = (int) $mail['port'];
        }

        return $mail;
    }

    private static function isMaskedMailPassword($password): bool
    {
        if (! is_string($password)) {
            return false;
        }

        $password = trim($password);

        return $password !== ''
            && preg_match('/^(?:\*+|x+|•+|\.{3,})$/iu', $password) === 1;
    }

    /**
     * Fetch the SMTP password from the private single-setting endpoint.
     *
     * Deliberately bypasses OmrCachedClient so the clear-text secret is never
     * written to its persistent cache snapshot or included in failure logs.
     */
    private static function getPrivateMailPassword(
        string $tenant,
        string $locale,
        ?string $dashboardSiteId = null
    ): ?string {
        try {
            $response = Http::withoutVerifying()
                ->connectTimeout(5)
                ->timeout(20)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-Tenant-ID' => $tenant,
                ])
                ->get(
                    OmrConfig::apiUrl('settings/smtp_password'),
                    self::settingsQuery($tenant, $locale, $dashboardSiteId)
                );

            if (! $response->successful()) {
                Log::warning('Private SMTP password request failed', [
                    'tenant' => $tenant,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $password = $response->json('data.value') ?? $response->json('value');

            if (! is_string($password)
                || trim($password) === ''
                || self::isMaskedMailPassword($password)) {
                Log::warning('Private SMTP password response is empty or masked', [
                    'tenant' => $tenant,
                ]);

                return null;
            }

            return $password;
        } catch (\Throwable $e) {
            Log::warning('Private SMTP password request exception', [
                'tenant' => $tenant,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public static function getMailSettings(string $tenantId, string $locale): array
    {
        $locale = strtolower($locale);
        $tenant = self::settingsTenant($tenantId);
        $dashboardSiteId = OmrConfig::dashboardSiteId();
        $cacheKey = "settings_mail_private_{$tenant}_{$dashboardSiteId}_{$locale}";

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($tenant, $locale, $dashboardSiteId) {
            $flat = [];
            $query = self::settingsQuery($tenant, $locale, $dashboardSiteId);

            foreach (['settings', 'settings/email'] as $path) {
                $response = OmrCachedClient::get('settings_mail', $path, $query, $tenant, [
                    'timeout' => 10,
                    'connect_timeout' => 5,
                    'success_ttl' => now()->addMinutes(15),
                    'stale_ttl' => now()->addDays(2),
                    'failure_ttl' => now()->addMinutes(5),
                    'rate_limit_ttl' => now()->addMinutes(15),
                ]);

                if (! $response['ok']) {
                    continue;
                }

                $payload = data_get($response['json'] ?? [], 'data') ?? ($response['json'] ?? []);
                $flat = array_merge($flat, self::flatSettingsFromApiData($payload));
            }

            return self::normalizeMailSettings($flat);
        });
    }

    public static function applyMailSettings(?string $tenantId = null, ?string $locale = null): array
    {
        $tenantId = self::settingsTenant($tenantId);
        $locale = $locale ?: session('locale', OmrConfig::defaultLocale());
        $settings = self::getMailSettings($tenantId, $locale);

        if ($settings === []) {
            return [];
        }

        $password = $settings['password'] ?? null;

        if (! is_string($password)
            || trim($password) === ''
            || self::isMaskedMailPassword($password)) {
            $password = self::getPrivateMailPassword(
                $tenantId,
                strtolower($locale),
                OmrConfig::dashboardSiteId()
            );
        }

        config([
            'mail.default' => $settings['mailer'] ?? config('mail.default'),
            'mail.mailers.smtp.host' => $settings['host'] ?? config('mail.mailers.smtp.host'),
            'mail.mailers.smtp.port' => $settings['port'] ?? config('mail.mailers.smtp.port'),
            'mail.mailers.smtp.username' => $settings['username'] ?? config('mail.mailers.smtp.username'),
            'mail.mailers.smtp.password' => $password ?? config('mail.mailers.smtp.password'),
            'mail.mailers.smtp.encryption' => $settings['encryption'] ?? config('mail.mailers.smtp.encryption'),
            'mail.from.address' => $settings['from_address'] ?? config('mail.from.address'),
            'mail.from.name' => $settings['from_name'] ?? config('mail.from.name'),
            'mail.admin_email' => $settings['admin_email'] ?? $settings['from_address'] ?? config('mail.admin_email'),
        ]);

        app('mail.manager')->forgetMailers();

        return $settings;
    }

public static function getSettings(string $tenantId, string $locale, bool $mirror = true): array
{
    $locale = strtolower($locale);
    $tenantId = $tenantId ?: OmrConfig::tenantId();
    $dashboardSiteId = OmrConfig::dashboardSiteId();
    $runtimeKey = "{$tenantId}_{$dashboardSiteId}_{$locale}";
    $cacheKey = "settings_v7_frontend_safe_{$tenantId}_{$dashboardSiteId}_{$locale}";
    $staleKey = "settings_v7_frontend_safe_stale_{$tenantId}_{$dashboardSiteId}_{$locale}";
    $lockKey = "lock_{$cacheKey}";
    $missing = '__settings_cache_missing__';

    if (isset(self::$runtimeSettings[$runtimeKey])) {
        $settings = self::replaceLogoImages(self::$runtimeSettings[$runtimeKey], $tenantId);

        return $mirror ? mirror_media($settings) : $settings;
    }

    $cached = Cache::get($cacheKey, $missing);

    if ($cached !== $missing) {
        $settings = is_array($cached) ? $cached : [];
        self::$runtimeSettings[$runtimeKey] = $settings;

        $settings = self::replaceLogoImages($settings, $tenantId);

        return $mirror ? mirror_media($settings) : $settings;
    }

    $stale = Cache::get($staleKey);

    if (self::isCoolingDown($tenantId)) {
        $settings = is_array($stale) ? $stale : [];
        self::$runtimeSettings[$runtimeKey] = $settings;

        $settings = self::replaceLogoImages($settings, $tenantId);

        return $mirror ? mirror_media($settings) : $settings;
    }

    try {
        $settings = Cache::lock($lockKey, 10)->block(5, function () use (
            $cacheKey,
            $staleKey,
            $missing,
            $stale,
            $tenantId,
            $locale,
            $dashboardSiteId
        ) {
            $cachedAgain = Cache::get($cacheKey, $missing);

            if ($cachedAgain !== $missing) {
                return is_array($cachedAgain) ? $cachedAgain : [];
            }

            if (self::isCoolingDown($tenantId)) {
                return is_array($stale) ? $stale : [];
            }

            $query = self::settingsQuery($tenantId, $locale, $dashboardSiteId);

            $response = OmrCachedClient::get('settings', 'settings', $query, $tenantId, [
                'success_ttl' => now()->addDays(7),
                'stale_ttl' => now()->addDays(14),
                'failure_ttl' => now()->addMinutes(10),
                'rate_limit_ttl' => now()->addMinutes(15),
                'cooldown_minutes' => 15,
            ]);

            if (! $response['ok']) {

                    // Önemli: /settings başarısızsa section endpointlerine düşme.
                    // Yoksa settings/general + seo + branding + colors + social + footer
                    // şeklinde 6 ekstra OMR API isteği oluşuyor.
                    Cache::put($cacheKey, is_array($stale) ? $stale : [], now()->addMinutes(10));

                    return is_array($stale) ? $stale : [];
                }

                $flat = self::flatSettingsFromApiData(
                    data_get($response['json'] ?? [], 'data') ?? ($response['json'] ?? [])
                );

                if (empty($flat)) {
                    Cache::put($cacheKey, is_array($stale) ? $stale : [], now()->addMinutes(10));

                    return is_array($stale) ? $stale : [];
                }

                $settings = self::replaceLogoImages(
                    self::flatSettingsToSections($flat),
                    $tenantId
                );

                $contactSettings = self::fetchContactSettings($tenantId, $locale, $dashboardSiteId);

                if (! empty($contactSettings)) {
                    $settings['contact'] = $contactSettings;
                } elseif (is_array($stale) && ! empty($stale['contact'])) {
                    $settings['contact'] = $stale['contact'];
                }

                Cache::put($cacheKey, $settings, now()->addDays(7));
                Cache::put($staleKey, $settings, now()->addDays(14));

                return $settings;
        });
    } catch (\Throwable $e) {
        Log::warning('Settings cache lock exception', [
            'error' => $e->getMessage(),
        ]);

        $settings = is_array($stale) ? $stale : [];
    }

    self::$runtimeSettings[$runtimeKey] = is_array($settings) ? $settings : [];

    $settings = self::replaceLogoImages($settings, $tenantId);

    return $mirror ? mirror_media($settings) : $settings;
}

    private static function settingsQuery(string $tenantId, string $locale, ?string $dashboardSiteId = null): array
    {
        $query = [
            'tenant' => $tenantId,
            'locale' => $locale,
        ];

        if ($dashboardSiteId) {
            $query['site_id'] = $dashboardSiteId;
            $query['website_id'] = $dashboardSiteId;
        }

        return $query;
    }
}
