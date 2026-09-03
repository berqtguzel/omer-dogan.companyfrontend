<?php

namespace App\Http\Middleware;

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\LanguagesController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\WidgetController;
use App\Services\TenantCanonicalUrlResolver;
use App\Support\OmrConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    private function cooldownKey(?string $tenant): string
    {
        return 'omr_rate_limited_' . (string) $tenant;
    }

    private function isOmrCoolingDown(?string $tenant): bool
    {
        if (! $tenant) {
            return true;
        }

        return Cache::has($this->cooldownKey($tenant));
    }

    private function emptyMenus(): array
    {
        return [
            'header' => [],
            'footer' => [],
        ];
    }

    private function emptyWidgets(): array
    {
        return [
            'whatsapp' => [],
            'ratings' => [],
            'highlights' => [],
            'service_highlights' => [],
        ];
    }

    private function fallbackLanguages(string $locale): array
    {
        return [
            'languages' => [
                [
                    'code' => $locale ?: 'de',
                    'name' => strtoupper($locale ?: 'de'),
                    'is_default' => true,
                ],
            ],
            'defaultCode' => $locale ?: 'de',
        ];
    }

    public function share(Request $request): array
    {
        $tenantId = OmrConfig::tenantId();
        $mainTenant = OmrConfig::tenantForSharedContent();

        $locale = $request->route('locale')
            ?? session('locale')
            ?? config('app.locale', 'de');

        $locale = strtolower((string) $locale);
        $canonicalResolver = app(TenantCanonicalUrlResolver::class);
        $canonicalBaseUrl = $canonicalResolver->baseUrl($tenantId);
        $canonicalUrl = $canonicalResolver->url($request->getPathInfo(), $locale, $tenantId);

        session(['locale' => $locale]);

        $isCoolingDown = fn (): bool => $this->isOmrCoolingDown($mainTenant);

        $langData = $isCoolingDown()
            ? $this->fallbackLanguages($locale)
            : LanguagesController::getLanguages($tenantId, $locale);

        return array_merge(parent::share($request), [
            'locale' => $locale,
            'tenantId' => $tenantId,
            'currentYear' => (int) date('Y'),
            'languages' => $langData['languages'] ?? [],
            'defaultLang' => $langData['defaultCode'] ?? 'de',
            'tenantSeo' => [
                'canonicalBaseUrl' => $canonicalBaseUrl,
                'canonicalUrl' => $canonicalUrl,
                'canonicalDomainVersion' => $canonicalResolver->cacheScope($tenantId),
            ],

            // SettingsController already handles fresh/stale API data. Do not
            // erase contact details merely because another OMR call hit cooldown.
            'settings' => fn () => SettingsController::getFrontendSettings($tenantId, $locale),

            'menus' => fn () => $isCoolingDown()
                ? $this->emptyMenus()
                : [
                    'header' => MenuController::getFrontendHeaderMenu($locale),
                    'footer' => MenuController::getFrontendFooterMenu($locale),
                ],

            'global' => [
                'locale' => $locale,
                'languages' => $langData['languages'] ?? [],
                'defaultLang' => $langData['defaultCode'] ?? 'de',

                'categories' => fn () => $isCoolingDown()
                    ? []
                    : CategoryController::getNavigationCategories($locale),

                'widgets' => fn () => $isCoolingDown()
                    ? $this->emptyWidgets()
                    : WidgetController::getFrontendWidgets($tenantId, $locale),
            ],

            'ziggy' => fn () => array_merge((new Ziggy)->toArray(), [
                'location' => $request->url(),
            ]),
        ]);
    }
}
