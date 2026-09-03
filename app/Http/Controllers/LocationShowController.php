<?php

namespace App\Http\Controllers;

use App\Services\ServiceTranslationResolver;
use App\Support\LocaleMapper;
use App\Support\Omr\OmrCatalog;
use App\Support\OmrConfig;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class LocationShowController extends Controller
{
    private function renderTranslatedServicePage(
        string $locale,
        string $citySlug,
        array $primaryService,
        array $services,
        string $tenant
    ) {
        $resolver = app(ServiceTranslationResolver::class);
        $sourceService = $primaryService;
        $primaryService = $resolver->resolve($primaryService, $locale, $tenant);
        $services = collect($services)
            ->filter(fn ($service) => is_array($service))
            ->map(fn ($service) => $resolver->resolve($service, $locale, $tenant))
            ->values()
            ->all();

        $indexable = (bool) data_get($primaryService, '_translation.indexable', false);
        $path = '/'.ltrim(request()->path(), '/');
        $pathFor = static function (string $code) use ($path): string {
            if (preg_match('#^/[a-z]{2}(?=/|$)#', $path)) {
                return preg_replace('#^/[a-z]{2}(?=/|$)#', "/{$code}", $path, 1) ?: $path;
            }

            return "/{$code}{$path}";
        };
        $availableLocales = $resolver->availableWebLocales($sourceService, $tenant);

        return Inertia::render('Locations/Show', [
            'city' => ucfirst(str_replace('-', ' ', $citySlug)),
            'primaryService' => $primaryService,
            'services' => $services,
            'locale' => $locale,
            'slug' => $citySlug,
            'seo' => [
                'indexable' => $indexable,
                'canonical' => $pathFor($indexable ? $locale : 'de'),
                'alternates' => collect($availableLocales)->map(fn ($code) => [
                    'code' => $code,
                    'href' => $pathFor($code),
                ])->values()->all(),
                'x_default' => $pathFor('de'),
            ],
        ]);
    }

    private static function normalizeSlug(?string $text): string
    {
        $text = str_replace(['/', '\\'], '-', (string) $text);
        $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text) ?: $text;
        $text = preg_replace('/[^A-Za-z0-9]+/', '-', $text);

        return trim(strtolower($text), '-');
    }

    private static function normalizeGermanSlug(?string $text): string
    {
        $text = str_replace(
            ['Ä', 'Ö', 'Ü', 'ä', 'ö', 'ü', 'ß', '/', '\\'],
            ['Ae', 'Oe', 'Ue', 'ae', 'oe', 'ue', 'ss', '-', '-'],
            (string) $text
        );

        $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text) ?: $text;
        $text = preg_replace('/[^A-Za-z0-9]+/', '-', $text);

        return trim(strtolower($text), '-');
    }

    private static function slugVariants(?string $text): array
    {
        return collect([
            self::normalizeSlug($text),
            self::normalizeGermanSlug($text),
        ])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private static function slugMatches(?string $candidate, string $targetSlug): bool
    {
        $candidateVariants = self::slugVariants($candidate);
        $targetVariants = self::slugVariants($targetSlug);

        return ! empty(array_intersect($candidateVariants, $targetVariants));
    }

    private static function citySlugMatches(?string $candidate, string $targetSlug): bool
    {
        $candidateVariants = self::slugVariants($candidate);
        $targetVariants = self::slugVariants($targetSlug);

        foreach ($candidateVariants as $candidateVariant) {
            foreach ($targetVariants as $targetVariant) {
                if (
                    $candidateVariant === $targetVariant ||
                    (
                        strlen($candidateVariant) >= 6 &&
                        (
                            str_contains($targetVariant, $candidateVariant) ||
                            str_contains(str_replace('-', '', $targetVariant), str_replace('-', '', $candidateVariant))
                        )
                    )
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function cooldownKeys(?string $tenant = null): array
    {
        return collect([
            $tenant,
            OmrConfig::tenantId(),
            OmrConfig::tenantForSharedContent(),
        ])
            ->filter()
            ->unique()
            ->map(fn ($id) => 'omr_rate_limited_'.$id)
            ->values()
            ->all();
    }

    private function isCoolingDown(?string $tenant = null): bool
    {
        return OmrCatalog::isCoolingDown($tenant);
    }

    private function renderTemporarilyUnavailable(string $locale)
    {
        return Inertia::render('Errors/NotFound', [
            'status' => 503,
            'locale' => $locale,
            'page' => [
                'title' => '503 — Dienst vorübergehend nicht verfügbar',
                'content' => 'Die Seite wird gerade aktualisiert. Bitte versuchen Sie es in wenigen Minuten erneut.',
            ],
        ])->toResponse(request())->setStatusCode(503);
    }

    private function serviceCollectionQuery(?string $categorySlug): array
    {
        if ($categorySlug === 'housekeeping-service') {
            $query = [
                'category_slug' => 'reinigungsservice',
                '_max_pages' => 10,
            ];
        } elseif ($categorySlug === 'praxisreinigung') {
            $query = [
                'parent_id' => 11,
                '_max_pages' => 10,
            ];
        } else {
            $query = [
                'category_slug' => $categorySlug,
                '_max_pages' => 1,
            ];
        }

        if (! in_array($categorySlug, ['housekeeping-service', 'praxisreinigung'], true)
            && OmrConfig::locationParentId() !== null) {
            $query['parent_id'] = OmrConfig::locationParentId();
            unset($query['category_slug']);
        }

        return array_filter($query, fn ($value) => $value !== null && $value !== '');
    }

    private function fetchAllServices(string $tenant, string $locale, ?string $categorySlug = null): array
    {
        $query = $this->serviceCollectionQuery($categorySlug);

        return OmrCatalog::services(
            $tenant,
            $locale,
            $query
        );
    }

    private function itemCityMatches(array $item, string $citySlug): bool
    {
        $citySlug = self::normalizeSlug($citySlug);
        $cityVariants = self::slugVariants($citySlug);

        $cityCandidates = [
            $item['city_slug'] ?? null,
            $item['city'] ?? null,
            $item['tenant_city'] ?? null,
            data_get($item, 'location.slug'),
            data_get($item, 'location.city_slug'),
            data_get($item, 'location.city'),
            data_get($item, 'omr.tenant_city'),
        ];
        $districtCandidates = [
            $item['district'] ?? null,
            $item['tenant_district'] ?? null,
            data_get($item, 'location.district'),
            data_get($item, 'omr.tenant_district'),
        ];
        $hasExplicitCity = false;

        foreach ($cityCandidates as $candidate) {
            if (! is_scalar($candidate) || trim((string) $candidate) === '') {
                continue;
            }

            $hasExplicitCity = true;

            if (self::citySlugMatches((string) $candidate, $citySlug)) {
                return true;
            }
        }

        // Bir kaydın açık city alanı varsa slug sonundan veya district alanından
        // ikinci kez şehir tahmini yapma. Örn. "Neuenhagen bei Berlin" kaydı
        // yalnız slug'ı "-berlin" ile bittiği için /berlin altında gösterilmemeli.
        if ($hasExplicitCity) {
            return false;
        }

        foreach ($districtCandidates as $candidate) {
            if (! is_scalar($candidate) || trim((string) $candidate) === '') {
                continue;
            }

            if (self::citySlugMatches((string) $candidate, $citySlug)) {
                return true;
            }
        }

        $slugVariants = self::slugVariants($item['slug'] ?? '');

        foreach ($slugVariants as $slug) {
            foreach ($cityVariants as $cityVariant) {
                if (
                    preg_match('/(^|-)in-'.preg_quote($cityVariant, '/').'$/', $slug) ||
                    preg_match('/-'.preg_quote($cityVariant, '/').'$/', $slug)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function itemServiceMatches(array $item, string $serviceSlug): bool
    {
        $serviceSlug = self::normalizeSlug($serviceSlug);
        $serviceVariants = self::slugVariants($serviceSlug);

        $serviceCandidates = [
            $item['category_slug'] ?? null,
            $item['service_slug'] ?? null,
            data_get($item, 'category.slug'),
            data_get($item, 'service.slug'),
            data_get($item, 'parent.slug'),
        ];

        foreach ($serviceCandidates as $candidate) {
            if (! is_scalar($candidate) || trim((string) $candidate) === '') {
                continue;
            }

            if (self::slugMatches((string) $candidate, $serviceSlug)) {
                return true;
            }
        }

        $slugVariants = self::slugVariants($item['slug'] ?? '');

        foreach ($slugVariants as $slug) {
            foreach ($serviceVariants as $serviceVariant) {
                if (
                    $slug === $serviceVariant ||
                    str_starts_with($slug, "{$serviceVariant}-") ||
                    str_starts_with($slug, "{$serviceVariant}-in-")
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function filterCityServices(array $services, string $citySlug): array
    {
        return collect($services)
            ->filter(fn ($item) => is_array($item) && $this->itemCityMatches($item, $citySlug))
            ->unique(fn ($item) => $item['id'] ?? $item['slug'] ?? md5(json_encode($item)))
            ->sortBy(fn ($item) => $item['order'] ?? $item['sort_order'] ?? $item['id'] ?? 999999)
            ->values()
            ->toArray();
    }

    private function findServiceByCityAndServiceSlug(array $services, string $citySlug, string $serviceSlug): ?array
    {
        return collect($services)->first(function ($item) use ($citySlug, $serviceSlug) {
            return is_array($item) &&
                $this->itemCityMatches($item, $citySlug) &&
                $this->itemServiceMatches($item, $serviceSlug);
        });
    }

    private function relatedCityServices(
        array $cityServices,
        string $primaryServiceSlug,
        ?int $primaryId
    ): array {
        return collect($cityServices)
            ->filter(function ($item) use ($primaryServiceSlug, $primaryId) {
                if (! is_array($item)) {
                    return false;
                }

                if ($primaryId && (int) ($item['id'] ?? 0) === $primaryId) {
                    return false;
                }

                if ($this->itemServiceMatches($item, $primaryServiceSlug)) {
                    return false;
                }

                return true;
            })
            ->unique(fn ($item) => $item['id'] ?? $item['slug'] ?? md5(json_encode($item)))
            ->take(24)
            ->values()
            ->toArray();
    }

    private function relatedSameServiceLocations(
        array $allServices,
        string $citySlug,
        string $serviceSlug,
        ?int $primaryId
    ): array {
        return collect($allServices)
            ->filter(function ($item) use ($citySlug, $serviceSlug, $primaryId) {
                if (! is_array($item)) {
                    return false;
                }

                if ($primaryId && (int) ($item['id'] ?? 0) === $primaryId) {
                    return false;
                }

                if (! $this->itemServiceMatches($item, $serviceSlug)) {
                    return false;
                }

                // Aynı hizmetin farklı şehirlerini göster. Böylece explicit
                // /gebaudereinigung-in-bautzen sayfasında Gastronomiereinigung,
                // Grundreinigung gibi başka kategoriler karışmaz.
                return ! $this->itemCityMatches($item, $citySlug);
            })
            ->unique(fn ($item) => $item['id'] ?? $item['slug'] ?? md5(json_encode($item)))
            ->take(24)
            ->values()
            ->toArray();
    }

    public function show(string $locale, string $citySlug)
    {
        $defaultServiceSlug = OmrConfig::locationCategorySlug() ?: 'gebaudereinigung';

        return $this->loadCityServices($locale, $citySlug, $defaultServiceSlug, true);
    }

    public function service(string $locale, string $serviceSlug, string $citySlug)
    {
        return $this->loadCityServices($locale, $citySlug, $serviceSlug, false);
    }

    private function loadCityServices(
        string $locale,
        string $citySlug,
        ?string $serviceSlug = null,
        bool $allowFallbackPrimary = false
    ) {
        $mainTenant = OmrConfig::tenantForSharedContent();

        session(['locale' => $locale]);

        $locale = LocaleMapper::toWeb($locale);
        $citySlug = self::normalizeSlug($citySlug);
        $serviceSlug = self::normalizeSlug($serviceSlug ?: (OmrConfig::locationCategorySlug() ?: 'gebaudereinigung'));
        $locationParentId = OmrConfig::locationParentId();
        $locationParentKey = $locationParentId !== null ? preg_replace('/[^0-9A-Za-z_-]+/', '_', $locationParentId) : 'all';

        if (! $mainTenant || $citySlug === '') {
            abort(404);
        }

        $pageCacheKey = "location_show_v13_{$mainTenant}_{$locale}_{$citySlug}_{$serviceSlug}_parent_{$locationParentKey}_".($allowFallbackPrimary ? 'city' : 'service');
        $pageMissing = '__location_show_missing__';

        $cachedPage = Cache::get($pageCacheKey, $pageMissing);

        if ($cachedPage !== $pageMissing) {
            if (is_array($cachedPage) && ! empty($cachedPage['primaryService'])) {
                return $this->renderTranslatedServicePage(
                    $locale,
                    $citySlug,
                    $cachedPage['primaryService'],
                    $cachedPage['services'] ?? [],
                    $mainTenant
                );
            }

            if ($this->isCoolingDown($mainTenant)) {
                return $this->renderTemporarilyUnavailable($locale);
            }

            abort(404);
        }

        $allServices = $this->fetchAllServices($mainTenant, $locale, $serviceSlug);

        if (empty($allServices) && $locale !== 'de') {
            $allServices = $this->fetchAllServices($mainTenant, 'de', $serviceSlug);
        }

        if (empty($allServices)) {
            Cache::put($pageCacheKey, [], now()->addMinutes(2));

            if ($this->isCoolingDown($mainTenant)) {
                return $this->renderTemporarilyUnavailable($locale);
            }

            abort(404);
        }

        $cityServices = $this->filterCityServices($allServices, $citySlug);

        $primaryService = $this->findServiceByCityAndServiceSlug(
            $cityServices,
            $citySlug,
            $serviceSlug
        );

        /**
         * Almanca slug'lar farklı locale altında gelirse tekil endpoint denemiyoruz.
         * Sadece de locale /services liste cache'inden fallback yapıyoruz.
         */
        if ((! $primaryService || empty($cityServices)) && $locale !== 'de') {
            $fallbackServices = $this->fetchAllServices($mainTenant, 'de', $serviceSlug);
            $fallbackCityServices = $this->filterCityServices($fallbackServices, $citySlug);

            $fallbackPrimaryService = $this->findServiceByCityAndServiceSlug(
                $fallbackCityServices,
                $citySlug,
                $serviceSlug
            );

            if ($fallbackPrimaryService || (! empty($fallbackCityServices) && $allowFallbackPrimary)) {
                $cityServices = $fallbackCityServices;
                $primaryService = $fallbackPrimaryService ?: ($fallbackCityServices[0] ?? null);
            }
        }

        /**
         * /de/bautzen gibi şehir sayfasında default hizmet bulunamazsa,
         * o şehirdeki ilk uygun hizmeti primary yapıyoruz.
         * Ama /de/hotelreinigung-in-bautzen gibi explicit service URL'de
         * yanlış hizmet göstermemek için fallback kapalı.
         */
        if (! $primaryService && $allowFallbackPrimary) {
            $primaryService = $cityServices[0] ?? null;
        }

        if (! $primaryService) {
            Cache::put($pageCacheKey, [], now()->addMinutes(10));

            if ($this->isCoolingDown($mainTenant)) {
                return $this->renderTemporarilyUnavailable($locale);
            }

            abort(404);
        }

        $otherServices = $allowFallbackPrimary
            ? $this->relatedCityServices(
                $cityServices,
                $serviceSlug,
                $primaryService['id'] ?? null
            )
            : $this->relatedSameServiceLocations(
                $allServices,
                $citySlug,
                $serviceSlug,
                $primaryService['id'] ?? null
            );

        $pageData = [
            'primaryService' => $primaryService,
            'services' => $otherServices,
        ];

        Cache::put($pageCacheKey, $pageData, now()->addDays(7));

        return $this->renderTranslatedServicePage(
            $locale,
            $citySlug,
            $primaryService,
            $otherServices,
            $mainTenant
        );
    }
}
