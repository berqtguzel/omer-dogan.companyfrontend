<?php

namespace App\Http\Controllers;

use App\Support\LocaleMapper;
use App\Support\Omr\OmrCachedClient;
use App\Support\OmrConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LanguagesController extends Controller
{
    public static function getLanguages($tenantId, $locale): array
    {
        $tenantId = OmrConfig::mainTenantId()
            ?: ($tenantId ?: OmrConfig::tenantId());
        $locale = LocaleMapper::toWeb($locale);
        $url = OmrConfig::baseUrl().'/global/settings/languages';
        $cacheKey = "languages_v3_{$tenantId}_{$locale}";

        return Cache::remember($cacheKey, now()->addHour(), function () use ($url, $tenantId, $locale) {
            try {
                $response = Http::connectTimeout(5)
                    ->timeout(20)
                    ->withOptions(['verify' => false])
                    ->accept('application/json')
                    ->get($url, [
                        'tenant' => $tenantId,
                        'locale' => LocaleMapper::toApi($locale),
                    ])
                    ->json();
            } catch (\Throwable $e) {
                OmrCachedClient::markCoolingDown($tenantId, 2);

                Log::warning('Languages API failed; using fallback languages', [
                    'tenant' => $tenantId,
                    'error' => $e->getMessage(),
                ]);
                $response = null;
            }

            return self::filterAvailableLanguages(
                self::normalize($response),
                $tenantId
            );
        });
    }

    private static function filterAvailableLanguages(array $languageData, string $tenantId): array
    {
        $response = OmrCachedClient::get(
            'settings_general_languages',
            'settings/general',
            ['tenant' => $tenantId],
            $tenantId,
            [
                'success_ttl' => now()->addHour(),
                'stale_ttl' => now()->addDays(7),
                'failure_ttl' => now()->addMinutes(10),
                'rate_limit_ttl' => now()->addMinutes(15),
            ]
        );

        $available = data_get($response['json'] ?? [], 'data._meta.available_languages', []);

        if (! is_array($available) || $available === []) {
            return $languageData;
        }

        $available = collect($available)
            ->map(fn ($code) => self::normalizeCode($code))
            ->filter()
            ->unique()
            ->values();

        $knownLanguages = collect($languageData['languages'] ?? [])->keyBy('code');
        $languages = $available
            ->map(fn ($code) => $knownLanguages->get($code, [
                'code' => $code,
                'label' => strtoupper($code),
            ]))
            ->values()
            ->all();

        $default = self::normalizeCode(
            data_get($response['json'] ?? [], 'data._meta.default_language')
                ?: ($languageData['defaultCode'] ?? '')
        );
        $codes = collect($languages)->pluck('code');

        if (! $codes->contains($default)) {
            $default = (string) ($codes->first() ?: OmrConfig::defaultLocale());
        }

        return [
            'languages' => $languages,
            'defaultCode' => $default,
            '_fallback' => false,
        ];
    }

    private static function normalize($response): array
    {
        if (! is_array($response)) {
            return self::fallback();
        }

        $languages = $response['data']['languages'] ?? [];

        if (! is_array($languages) || $languages === []) {
            return self::fallback();
        }

        $normalized = collect($languages)
            ->filter(fn ($language) => is_array($language))
            ->map(function (array $language) {
                $code = self::normalizeCode($language['locale'] ?? '');

                return [
                    'code' => $code,
                    'label' => $language['name'] ?? strtoupper($code),
                ];
            })
            ->filter(fn ($language) => $language['code'] !== '')
            ->unique('code')
            ->values()
            ->all();

        return [
            'languages' => $normalized,
            'defaultCode' => self::normalizeCode($response['data']['default']['locale'] ?? 'de'),
            '_fallback' => false,
        ];
    }

    private static function normalizeCode($code): string
    {
        $code = strtolower(trim((string) $code));
        $code = explode('-', str_replace('_', '-', $code))[0];

        return LocaleMapper::toWeb($code);
    }

    private static function fallback(): array
    {
        return [
            'languages' => [
                ['code' => 'de', 'label' => 'Deutsch'],
                ['code' => 'en', 'label' => 'English'],
                ['code' => 'tr', 'label' => 'Türkçe'],
            ],
            'defaultCode' => 'de',
            '_fallback' => true,
        ];
    }
}
