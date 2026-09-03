<?php

namespace App\Http\Controllers;

use App\Support\LocaleMapper;
use App\Support\Omr\OmrCatalog;
use App\Support\OmrConfig;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class LocationController extends Controller
{
    private static function makeSlug(string $text): string
    {
        $text = str_replace(['/', '\\'], '-', $text);
        $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        $text = preg_replace('/[^A-Za-z0-9]+/', '-', $text);

        return trim(strtolower($text), '-');
    }

    private static function replaceImageTenant(?string $imageUrl): ?string
    {
        if (! $imageUrl) {
            return null;
        }

        $mainTenant = OmrConfig::mainTenantId();

        if (! $mainTenant) {
            return $imageUrl;
        }

        $pattern = '/(\/storage\/)([^\/]+)(\/media\/)/';

        if (preg_match($pattern, $imageUrl, $matches)) {
            if ($matches[2] === $mainTenant) {
                return $imageUrl;
            }

            return preg_replace($pattern, '$1'.$mainTenant.'$3', $imageUrl);
        }

        return $imageUrl;
    }

    private static function firstImageUrl(array $item): ?string
    {
        $candidates = [
            $item['image'] ?? null,
            $item['image_url'] ?? null,
            $item['thumbnail'] ?? null,
            $item['thumbnail_url'] ?? null,
            $item['featured_image'] ?? null,
            $item['featured_image_url'] ?? null,
            data_get($item, 'media.url'),
            data_get($item, 'media.image'),
            data_get($item, 'media.0.url'),
            data_get($item, 'media.0.path'),
            data_get($item, 'image.url'),
            data_get($item, 'image.path'),
            data_get($item, 'service.image'),
            data_get($item, 'service.image_url'),
            data_get($item, 'service.media.url'),
            data_get($item, 'service.media.0.url'),
            data_get($item, 'category.image'),
            data_get($item, 'category.image_url'),
            data_get($item, 'category.media.url'),
            data_get($item, 'category.media.0.url'),
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate)) {
                $candidate = $candidate['url'] ?? $candidate['path'] ?? null;
            }

            if (! is_string($candidate)) {
                continue;
            }

            $candidate = trim($candidate);

            if ($candidate !== '') {
                return self::replaceImageTenant($candidate);
            }
        }

        return null;
    }

    private static function firstCoordinate(array $item, string $axis): ?float
    {
        $keys = $axis === 'latitude'
            ? ['latitude', 'lat', 'location.latitude', 'location.lat', 'coordinates.latitude', 'coordinates.lat']
            : ['longitude', 'lng', 'lon', 'location.longitude', 'location.lng', 'location.lon', 'coordinates.longitude', 'coordinates.lng', 'coordinates.lon'];

        foreach ($keys as $key) {
            $value = data_get($item, $key);

            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }

    private static function normalizeDistrict(?string $value): string
    {
        $value = strtolower(trim((string) $value));
        $value = str_replace(['-', '_', '/'], ' ', $value);

        return preg_replace('/\s+/', ' ', $value);
    }

    private static function normalizeCity(?string $value): string
    {
        return strtolower(trim((string) $value));
    }

    private static function humanizeServiceSlug(?string $value): string
    {
        $slug = self::normalizeServiceSlug($value);

        if ($slug === '') {
            return '';
        }

        return collect(explode('-', $slug))
            ->filter()
            ->map(fn ($word) => ucfirst($word))
            ->implode(' ');
    }

    private static function looksLikeSlugTitle(?string $value): bool
    {
        $value = trim((string) $value);

        if ($value === '') {
            return false;
        }

        return str_contains($value, '-')
            || str_contains($value, '_')
            || str_contains($value, '|')
            || ($value === strtolower($value) && ! str_contains($value, ' '));
    }

    private static function localeCodesMatch(?string $candidate, string $locale): bool
    {
        return LocaleMapper::toWeb($candidate) === LocaleMapper::toWeb($locale);
    }

    private static function translatedServiceName(array $service, string $locale): string
    {
        $translationSources = [
            $service['translations'] ?? null,
            data_get($service, 'service.translations'),
            data_get($service, 'category.translations'),
            data_get($service, 'parent.translations'),
        ];

        foreach ($translationSources as $translations) {
            if (! is_array($translations)) {
                continue;
            }

            foreach ($translations as $key => $translation) {
                $translationLocale = is_array($translation)
                    ? ($translation['language_code'] ?? $translation['locale'] ?? $translation['lang'] ?? $key)
                    : $key;

                if (! self::localeCodesMatch(is_scalar($translationLocale) ? (string) $translationLocale : null, $locale)) {
                    continue;
                }

                $candidates = is_array($translation)
                    ? [
                        $translation['name'] ?? null,
                        $translation['title'] ?? null,
                        $translation['meta_title'] ?? null,
                        $translation['seo_title'] ?? null,
                    ]
                    : [$translation];

                foreach ($candidates as $candidate) {
                    if (is_string($candidate) && trim($candidate) !== '') {
                        return trim($candidate);
                    }
                }
            }
        }

        return '';
    }

    private static function firstCleanServiceName(array $service, string $locale = 'de'): string
    {
        $translatedName = self::translatedServiceName($service, $locale);

        if ($translatedName !== '') {
            return $translatedName;
        }

        $preferred = [
            data_get($service, 'category.name'),
            data_get($service, 'category.title'),
            data_get($service, 'service.name'),
            data_get($service, 'service.title'),
            data_get($service, 'parent.name'),
            data_get($service, 'parent.title'),
        ];

        foreach ($preferred as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '' && ! self::looksLikeSlugTitle($candidate)) {
                return trim($candidate);
            }
        }

        $slugCandidates = [
            $service['category_slug'] ?? null,
            $service['service_slug'] ?? null,
            data_get($service, 'category.slug'),
            data_get($service, 'service.slug'),
            data_get($service, 'parent.slug'),
            OmrConfig::locationCategorySlug(),
        ];

        foreach ($slugCandidates as $candidate) {
            $title = self::humanizeServiceSlug(is_scalar($candidate) ? (string) $candidate : null);

            if ($title !== '') {
                return $title;
            }
        }

        foreach ([$service['name'] ?? null, $service['title'] ?? null, $service['meta_title'] ?? null] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '' && ! self::looksLikeSlugTitle($candidate)) {
                return trim($candidate);
            }
        }

        return '';
    }

    private static function formatServiceLocationTitle(array $service, ?string $city, string $locale): ?string
    {
        $city = trim((string) $city);
        $translatedName = self::translatedServiceName($service, $locale);

        if ($city === '') {
            return $translatedName
                ?: self::firstCleanServiceName($service, $locale)
                ?: ($service['meta_title'] ?? $service['title'] ?? $service['name'] ?? null);
        }

        if ($translatedName === '' && ! self::localeCodesMatch($locale, 'de')) {
            return $city;
        }

        $name = $translatedName ?: self::firstCleanServiceName($service, $locale);

        if ($name !== '' && self::normalizeCity($name) !== self::normalizeCity($city)) {
            return str_contains(strtolower($name), strtolower($city))
                ? $name
                : "{$name} – {$city}";
        }

        return $city;
    }

    private static function normalizeServiceSlug(?string $value): string
    {
        $value = str_replace(
            ['Ä', 'Ö', 'Ü', 'ä', 'ö', 'ü', 'ß', '/', '\\'],
            ['Ae', 'Oe', 'Ue', 'ae', 'oe', 'ue', 'ss', '-', '-'],
            (string) $value
        );

        $value = iconv('UTF-8', 'ASCII//TRANSLIT', $value) ?: $value;
        $value = preg_replace('/[^A-Za-z0-9]+/', '-', $value);

        return trim(strtolower($value), '-');
    }

    private static function serviceMatchesConfiguredCategory(array $service, ?string $categorySlug): bool
    {
        if ($categorySlug === null || trim($categorySlug) === '') {
            return true;
        }

        $target = self::normalizeServiceSlug($categorySlug);

        if ($target === '') {
            return true;
        }

        $candidates = [
            $service['category_slug'] ?? null,
            $service['service_slug'] ?? null,
            data_get($service, 'category.slug'),
            data_get($service, 'service.slug'),
            data_get($service, 'parent.slug'),
        ];

        foreach ($candidates as $candidate) {
            if (! is_scalar($candidate) || trim((string) $candidate) === '') {
                continue;
            }

            if (self::normalizeServiceSlug((string) $candidate) === $target) {
                return true;
            }
        }

        /**
         * Bazı kayıtlarda category_slug boş olabilir ama slug şöyle gelebilir:
         * gebaudereinigung-berlin
         * gebaudereinigung-in-berlin
         */
        $slug = self::normalizeServiceSlug($service['slug'] ?? '');

        if ($slug !== '') {
            return $slug === $target
                || str_starts_with($slug, "{$target}-")
                || str_starts_with($slug, "{$target}-in-");
        }

        return false;
    }

    public static function getLocations(string $locale, bool $mirror = true): array
    {
        $tenantDistrict = (string) config('services.omr.tenant_district', '');
        $tenantCity = (string) config('services.omr.tenant_city', '');
        $mainTenant = OmrConfig::tenantForSharedContent();

        if (! $mainTenant) {
            return ['data' => []];
        }

        $normalizedTenantDistrict = self::normalizeDistrict($tenantDistrict);
        $normalizedTenantCity = self::normalizeCity($tenantCity);
        $locationCategorySlug = OmrConfig::locationCategorySlug() ?: 'gebaudereinigung';
        $normalizedLocationCategorySlug = self::normalizeServiceSlug($locationCategorySlug);
        $locationParentId = OmrConfig::locationParentId();
        $normalizedLocationParentId = $locationParentId !== null ? preg_replace('/[^0-9A-Za-z_-]+/', '_', $locationParentId) : 'all';

        $cacheKey = "locations_from_services_v17_{$mainTenant}_{$locale}_{$normalizedLocationCategorySlug}_parent_{$normalizedLocationParentId}_{$normalizedTenantDistrict}_{$normalizedTenantCity}";

        $cachedLocations = Cache::get($cacheKey);

        if (is_array($cachedLocations)) {
            return $mirror ? mirror_media($cachedLocations) : $cachedLocations;
        }

        $locations = (function () use (
            $mainTenant,
            $locale,
            $normalizedTenantDistrict,
            $normalizedTenantCity,
            $locationCategorySlug,
            $locationParentId
        ) {
            $serviceQuery = [
                'category_slug' => $locationCategorySlug,
                '_max_pages' => 1,
                '_per_page' => 500,
            ];

            if ($locationParentId !== null) {
                $serviceQuery['parent_id'] = $locationParentId;
                unset($serviceQuery['category_slug']);
            }

            $allServices = collect(OmrCatalog::services($mainTenant, $locale, $serviceQuery))
                ->filter(fn ($s) => is_array($s))
                ->filter(function ($s) use ($normalizedTenantDistrict, $normalizedTenantCity) {
                    $serviceCity = self::normalizeCity(
                        $s['city']
                        ?? data_get($s, 'location.city')
                        ?? data_get($s, 'omr.tenant_city')
                        ?? $s['tenant_city']
                        ?? ''
                    );

                    $serviceDistrict = self::normalizeDistrict(
                        $s['district']
                        ?? data_get($s, 'location.district')
                        ?? data_get($s, 'omr.tenant_district')
                        ?? $s['tenant_district']
                        ?? ''
                    );

                    if ($serviceCity === '' && $serviceDistrict === '') {
                        return false;
                    }

                    if ($normalizedTenantDistrict !== '') {
                        return $serviceDistrict === $normalizedTenantDistrict;
                    }

                    if ($normalizedTenantCity !== '') {
                        return $serviceCity === $normalizedTenantCity;
                    }

                    return true;
                })
                ->values();

            if ($allServices->isNotEmpty()) {
                $validLocales = ['de', 'de_DE', 'en', 'en_US'];
                $collator = new \Collator(in_array($locale, $validLocales, true) ? $locale : 'de_DE');

                return [
                    '_source' => 'services',
                    'data' => $allServices
                        ->groupBy(function ($s) {
                            return self::makeSlug(
                                $s['city']
                                ?? data_get($s, 'location.city')
                                ?? data_get($s, 'omr.tenant_city')
                                ?? $s['tenant_city']
                                ?? $s['district']
                                ?? data_get($s, 'location.district')
                                ?? data_get($s, 'omr.tenant_district')
                                ?? $s['tenant_district']
                                ?? ''
                            );
                        })
                        ->map(function ($items, $slug) {
                            $firstItem = $items->first();
                            $image = $items
                                ->map(fn ($item) => is_array($item) ? self::firstImageUrl($item) : null)
                                ->filter()
                                ->first();
                            $latitude = $items
                                ->map(fn ($item) => is_array($item) ? self::firstCoordinate($item, 'latitude') : null)
                                ->filter(fn ($value) => $value !== null)
                                ->first();
                            $longitude = $items
                                ->map(fn ($item) => is_array($item) ? self::firstCoordinate($item, 'longitude') : null)
                                ->filter(fn ($value) => $value !== null)
                                ->first();

                            $city = $firstItem['city']
                                ?? data_get($firstItem, 'location.city')
                                ?? data_get($firstItem, 'omr.tenant_city')
                                ?? $firstItem['tenant_city']
                                ?? $firstItem['district']
                                ?? data_get($firstItem, 'location.district')
                                ?? data_get($firstItem, 'omr.tenant_district')
                                ?? $firstItem['tenant_district']
                                ?? null;

                            return [
                                'city' => $city,
                                'slug' => $slug,
                                'services_count' => $items->count(),
                                'image' => $image,
                                'latitude' => $latitude,
                                'longitude' => $longitude,
                                'services' => $items->values()->toArray(),
                            ];
                        })
                        ->filter(fn ($item) => ! empty($item['city']) && ! empty($item['slug']))
                        ->sort(fn ($a, $b) => $collator->compare($a['city'], $b['city']))
                        ->values()
                        ->toArray(),
                ];
            }

            return [
                '_source' => 'empty',
                'data' => [],
            ];
        })();

        $source = $locations['_source'] ?? 'services';
        unset($locations['_source']);

        if ($source === 'services') {
            Cache::put($cacheKey, $locations, now()->addHours(24));
        }

        return $mirror ? mirror_media($locations) : $locations;
    }

    public static function getFrontendLocations(string $locale, bool $mirror = true): array
    {
        $locations = self::getLocations($locale, false);

        $data = collect($locations['data'] ?? [])
            ->map(function ($location) use ($locale) {
                $firstService = $location['services'][0] ?? [];
                $city = $location['city'] ?? null;

                return array_filter([
                    'city' => $city,
                    'slug' => $location['slug'] ?? null,
                    'services_count' => $location['services_count'] ?? null,
                    'image' => $location['image'] ?? null,
                    'latitude' => $location['latitude'] ?? null,
                    'longitude' => $location['longitude'] ?? null,
                    'title' => self::formatServiceLocationTitle($firstService, $city, $locale),
                ], fn ($value) => $value !== null && $value !== '');
            })
            ->values()
            ->all();

        return $mirror ? mirror_media(['data' => $data]) : ['data' => $data];
    }

    public function index()
    {
        $locale = session('locale', OmrConfig::defaultLocale());
        $response = self::getLocations($locale);

        return Inertia::render('Locations/Index', [
            'locations' => $response['data'] ?? [],
            'locale' => $locale,
        ]);
    }

    private static function extractCityFromSlug(string $slug): string
    {
        if (str_contains($slug, '-in-')) {
            return explode('-in-', $slug)[1];
        }

        return $slug;
    }

    public function show(string $slug)
    {
        $locale = session('locale', OmrConfig::defaultLocale());
        $response = self::getLocations($locale);

        $citySlug = self::extractCityFromSlug($slug);

        $city = collect($response['data'])->first(function ($item) use ($citySlug) {
            return str_contains($item['slug'], $citySlug);
        });

        if (! $city) {
            abort(404);
        }

        return Inertia::render('Locations/Show', [
            'locale' => $locale,
            'city' => $city,
            'services' => $city['services'] ?? [],
        ]);
    }
}
