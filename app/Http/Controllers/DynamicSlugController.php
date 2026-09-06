<?php

namespace App\Http\Controllers;

use App\Support\LocaleMapper;
use App\Support\LegacyUrlNormalizer;
use App\Support\Omr\OmrCatalog;
use App\Support\OmrConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class DynamicSlugController extends Controller
{
    private array $allowedLocales = [
        'de', 'en', 'tr', 'ru', 'fr', 'es', 'it', 'pt', 'ro', 'pl', 'cs', 'sk', 'bg', 'hr',
    ];

    private function resolveLocale(?string $locale = null): string
    {
        $locale = LocaleMapper::toWeb($locale);

        if ($locale && in_array($locale, $this->allowedLocales, true)) {
            return $locale;
        }

        $default = strtolower((string) (OmrConfig::defaultLocale() ?: config('app.locale', 'de')));

        return in_array($default, $this->allowedLocales, true) ? $default : 'de';
    }

    private function applyLocale(string $locale): void
    {
        app()->setLocale($locale);
        session(['locale' => $locale]);
    }

    private function cooldownKeys(): array
    {
        return collect([
            OmrConfig::tenantId(),
            OmrConfig::tenantForSharedContent(),
        ])
            ->filter()
            ->unique()
            ->map(fn ($id) => 'omr_rate_limited_'.$id)
            ->values()
            ->all();
    }

    private function isCoolingDown(): bool
    {
        foreach ($this->cooldownKeys() as $key) {
            if (Cache::has($key)) {
                return true;
            }
        }

        return false;
    }

    private function markCoolingDown(int $minutes = 15): void
    {
        foreach ($this->cooldownKeys() as $key) {
            Cache::put($key, true, now()->addMinutes($minutes));
        }
    }

    private function failureTtlByStatus(?int $status)
    {
        return match ($status) {
            429 => now()->addMinutes(15),
            404 => now()->addSeconds(60),
            default => now()->addMinutes(2),
        };
    }

    private function emptyRouteIndex(): array
    {
        return [
            'pages' => [],
            'services' => [],
            'serviceCities' => [],
            'servicePrefixes' => [],
        ];
    }

    private function renderNotFound(string $locale, string $slug)
    {
        return Inertia::render('Errors/NotFound', [
            'status' => 404,
            'locale' => $locale,
            'slug' => $slug,
            'page' => [
                'title' => '404 — Seite nicht gefunden',
                'content' => 'Die angeforderte Seite wurde nicht gefunden.',
            ],
        ])->toResponse(request())->setStatusCode(404);
    }

    private function api(string $path, array $params = [])
    {
        $mainTenant = OmrConfig::tenantForSharedContent();

        return Http::withOptions(['verify' => false])
            ->connectTimeout(5)
            ->timeout(20)
            ->withHeaders([
                'X-Tenant-ID' => $mainTenant,
                'Accept' => 'application/json',
            ])
            ->get(
                OmrConfig::baseUrl().$path,
                array_merge(['tenant' => $mainTenant], $params)
            );
    }

    private function normalizeRouteSlug(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $value = urldecode($value);
        $value = preg_replace('#^https?://[^/]+#i', '', $value) ?: $value;

        $path = parse_url($value, PHP_URL_PATH);

        if (is_string($path) && $path !== '') {
            $value = $path;
        }

        $value = trim($value, "/ \t\n\r\0\x0B");

        if ($value === '') {
            return null;
        }

        $segments = array_values(array_filter(explode('/', $value), fn ($segment) => $segment !== ''));

        if (! empty($segments) && in_array(strtolower($segments[0]), $this->allowedLocales, true)) {
            array_shift($segments);
        }

        if (count($segments) !== 1) {
            return null;
        }

        $slug = strtolower(trim((string) $segments[0]));

        if ($slug === '') {
            return null;
        }

        return $slug;
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
        $variants = collect([
            self::normalizeSlug($text),
            self::normalizeGermanSlug($text),
        ])
            ->filter()
            ->unique()
            ->values();

        /**
         * OMR'de bazı slug'lar Almanca umlaut transliteration ile ge-baeude,
         * frontend URL'lerinde ise ge-baude olarak gelebiliyor.
         * /de/gebaudereinigung <-> gebaeudereinigung eşleşmesi için iki
         * varyantı da güvenli şekilde route çözümüne ekliyoruz.
         */
        return $variants
            ->concat($variants->map(fn ($value) => str_replace(
                ['ae', 'oe', 'ue'],
                ['a', 'o', 'u'],
                (string) $value
            )))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function slugsFromItem(array $item): array
    {
        $candidates = [];

        foreach (['slug', 'identifier', 'handle', 'path', 'url', 'permalink', 'route'] as $key) {
            if (! empty($item[$key]) && is_scalar($item[$key])) {
                $candidates[] = (string) $item[$key];
            }
        }

        $translations = $item['translations'] ?? [];

        if (is_array($translations)) {
            foreach ($translations as $translation) {
                if (! is_array($translation)) {
                    continue;
                }

                foreach (['slug', 'identifier', 'handle', 'path', 'url', 'permalink', 'route'] as $key) {
                    if (! empty($translation[$key]) && is_scalar($translation[$key])) {
                        $candidates[] = (string) $translation[$key];
                    }
                }
            }
        }

        return collect($candidates)
            ->map(fn ($value) => $this->normalizeRouteSlug($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function healthSlugs(): array
    {
        return [
            'health',
            'health-check',
            'status',
            'ping',
            'api',
            'api-health',
        ];
    }

    /**
     * Route index boşken/cooldown sırasında da no-in şehir-hizmet URL'lerini yakala.
     * Örn: housekeeping-service-bremerhaven -> housekeeping-service + bremerhaven
     * Bu liste sadece güvenli, gerçek hizmet prefix'lerinden oluşur; tekil /services/{slug}
     * veya /pages/{slug} çağrısı tetiklemez.
     */
    private function staticServicePrefixes(): array
    {
        $prefixes = [
            'housekeeping-service',
            'housekeeping',
            'hotelreinigung',
            'hotel-zimmerreinigung',
            'zimmerreinigung',
            'apartmentreinigung',
            'apartment-reinigung',
            'apartmentsreinigung',
            'apartment-hotelreinigung',
            'gebaudereinigung',
            'gebaeudereinigung',
            'gebäudereinigung',
            'bueroreinigung',
            'büroreinigung',
            'unterhaltsreinigung',
            'grundreinigung',
            'glasreinigung',
            'fensterreinigung',
            'treppenhausreinigung',
            'praxisreinigung',
            'kanzleireinigung',
            'industriereinigung',
            'baureinigung',
            'baustellenreinigung',
            'bauschuttentsorgung',
            'baucontainer-reinigung',
            'bauendreinigung',
            'baufeinreinigung',
            'boden-kristallisierung',
            'bodenreinigung',
            'buroreinigung',
            'einkaufszentrum-reinigung',
            'fassadenreinigung',
            'ferienhausereinigung',
            'gastronomiereinigung',
            'gebaudeservice',
            'hausmeisterservice',
            'hausreinigung',
            'haushaltsauflosungen',
            'hostelreinigung',
            'kuchenreinigung',
            'klinikreinigung',
            'ladenreinigung',
            'matratzenreinigung',
            'polsterreinigung',
            'restaurantreinigung',
            'schwimmbadreinigung',
            'sperrmullentsorgung',
            'tatortreinigung',
            'tiefgaragenreinigung',
            'reinigungsservice',
            'zimmermadchen',
            'zimmermaedchen',
            'zimmermädchen',
            'abriss-abrissarbeit-abbrucharbeit',
            'stewarding',
            'stewarding-service',
            'stewarding-service-spulkuchenreinigung',
        ];

        return collect($prefixes)
            ->flatMap(fn ($value) => self::slugVariants((string) $value))
            ->filter(fn ($value) => is_string($value) && strlen($value) >= 5)
            ->unique()
            ->mapWithKeys(fn ($value) => [$value => true])
            ->all();
    }

    private function splitServiceInCitySlug(string $slug): ?array
    {
        $parts = explode('-', $slug);

        if (count($parts) <= 2 || ! in_array('in', $parts, true)) {
            return null;
        }

        $inIndex = array_search('in', $parts, true);

        if ($inIndex === false || $inIndex === 0 || $inIndex === count($parts) - 1) {
            return null;
        }

        $serviceSlug = implode('-', array_slice($parts, 0, $inIndex));
        $citySlug = implode('-', array_slice($parts, $inIndex + 1));

        if ($serviceSlug === '' || $citySlug === '') {
            return null;
        }

        return [
            'serviceSlug' => $serviceSlug,
            'citySlug' => $citySlug,
        ];
    }

    private function cityCandidateSlugs(array $service): array
    {
        $candidates = [
            $service['city_slug'] ?? null,
            $service['city'] ?? null,
            $service['district'] ?? null,
            $service['tenant_city'] ?? null,
            $service['tenant_district'] ?? null,
            data_get($service, 'location.slug'),
            data_get($service, 'location.city_slug'),
            data_get($service, 'location.city'),
            data_get($service, 'location.district'),
            data_get($service, 'omr.tenant_city'),
            data_get($service, 'omr.tenant_district'),
        ];

        return collect($candidates)
            ->filter(fn ($value) => is_scalar($value) && trim((string) $value) !== '')
            ->flatMap(fn ($value) => self::slugVariants((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function serviceBaseCandidateSlugs(array $service): array
    {
        $candidates = [
            $service['category_slug'] ?? null,
            $service['service_slug'] ?? null,
            data_get($service, 'category.slug'),
            data_get($service, 'category.name'),
            data_get($service, 'service.slug'),
            data_get($service, 'service.name'),
            data_get($service, 'parent.slug'),
            data_get($service, 'parent.name'),
        ];

        return collect($candidates)
            ->filter(fn ($value) => is_scalar($value) && trim((string) $value) !== '')
            ->flatMap(fn ($value) => self::slugVariants((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function serviceCityFromRouteAndItem(array $service, string $routeSlug): ?array
    {
        $routeSlug = self::normalizeSlug($routeSlug);

        foreach ($this->cityCandidateSlugs($service) as $citySlug) {
            foreach (["-in-{$citySlug}", "-{$citySlug}"] as $suffix) {
                if (! str_ends_with($routeSlug, $suffix)) {
                    continue;
                }

                $serviceSlug = trim(substr($routeSlug, 0, -strlen($suffix)), '-');

                if ($serviceSlug === '') {
                    continue;
                }

                return [
                    'serviceSlug' => $serviceSlug,
                    'citySlug' => $citySlug,
                ];
            }
        }

        return null;
    }

    private function generatedServiceCityRoutes(array $service): array
    {
        $routes = [];
        $serviceSlugs = $this->serviceBaseCandidateSlugs($service);
        $citySlugs = $this->cityCandidateSlugs($service);

        foreach ($serviceSlugs as $serviceSlug) {
            foreach ($citySlugs as $citySlug) {
                if ($serviceSlug === '' || $citySlug === '') {
                    continue;
                }

                $routes["{$serviceSlug}-in-{$citySlug}"] = [
                    'serviceSlug' => $serviceSlug,
                    'citySlug' => $citySlug,
                ];

                $routes["{$serviceSlug}-{$citySlug}"] = [
                    'serviceSlug' => $serviceSlug,
                    'citySlug' => $citySlug,
                ];
            }
        }

        return $routes;
    }

    private function splitServiceCityByPrefixes(string $slug, array $servicePrefixes): ?array
    {
        $slug = self::normalizeSlug($slug);

        if ($slug === '' || empty($servicePrefixes)) {
            return null;
        }

        $prefixes = collect(array_keys($servicePrefixes))
            ->map(fn ($value) => self::normalizeSlug((string) $value))
            ->filter()
            ->unique()
            ->sortByDesc(fn ($value) => strlen($value))
            ->values();

        foreach ($prefixes as $serviceSlug) {
            foreach (["{$serviceSlug}-in-", "{$serviceSlug}-"] as $prefix) {
                if (! str_starts_with($slug, $prefix)) {
                    continue;
                }

                $citySlug = trim(substr($slug, strlen($prefix)), '-');

                if ($citySlug === '' || $citySlug === 'in') {
                    continue;
                }

                return [
                    'serviceSlug' => $serviceSlug,
                    'citySlug' => $citySlug,
                ];
            }
        }

        return null;
    }

    private function fetchPagedCollection(string $endpoint, string $locale, array $extraParams = [], int $maxPages = 10): array
    {
        $items = [];
        $perPage = 100;
        $lastStatus = null;

        for ($page = 1; $page <= $maxPages; $page++) {
            if ($this->isCoolingDown()) {
                return [
                    'ok' => false,
                    'status' => 429,
                    'items' => $items,
                ];
            }

            $response = $this->api('/'.OmrConfig::apiVersionSegment().'/'.ltrim($endpoint, '/'), array_merge([
                'locale' => LocaleMapper::toApi($locale),
                'per_page' => $perPage,
                'page' => $page,
            ], $extraParams));

            $lastStatus = $response->status();

            if (! $response->successful()) {
                if ($response->status() === 429) {
                    $this->markCoolingDown(15);
                }

                Log::warning('Dynamic route index API failed', [
                    'endpoint' => $endpoint,
                    'locale' => $locale,
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 300),
                ]);

                return [
                    'ok' => false,
                    'status' => $response->status(),
                    'items' => $items,
                ];
            }

            $json = $response->json();
            $data = $json['data'] ?? [];

            if (! is_array($data) || empty($data)) {
                break;
            }

            $items = array_merge($items, $data);

            $lastPage = (int) (
                data_get($json, 'pagination.last_page') ??
                data_get($json, 'meta.last_page') ??
                0
            );

            if ($lastPage > 0 && $page >= $lastPage) {
                break;
            }

            if (count($data) < $perPage) {
                break;
            }
        }

        return [
            'ok' => true,
            'status' => $lastStatus,
            'items' => $items,
        ];
    }

    private function buildRouteIndex(string $locale): array
    {
        /**
         * Services artık direkt API'den değil OmrCatalog üzerinden gelir.
         * Böylece DynamicSlugController ayrıca /api/v2/services pagination başlatmaz.
         */
        $mainTenant = OmrConfig::tenantForSharedContent();

        $serviceItems = OmrCatalog::rootCategories($mainTenant, $locale);

        $serviceResult = [
            'ok' => true,
            'status' => 200,
            'items' => $serviceItems,
        ];

        $pageResult = $this->fetchPagedCollection('pages', $locale, [], 5);

        $pages = [];
        $services = [];
        $serviceCities = [];
        $servicePrefixes = [];

        foreach ($pageResult['items'] ?? [] as $page) {
            if (! is_array($page)) {
                continue;
            }

            foreach ($this->slugsFromItem($page) as $slug) {
                $pages[$slug] = $page;
            }
        }

        foreach ($serviceResult['items'] ?? [] as $service) {
            if (! is_array($service)) {
                continue;
            }

            foreach ($this->serviceBaseCandidateSlugs($service) as $servicePrefix) {
                if ($servicePrefix !== '') {
                    $servicePrefixes[$servicePrefix] = true;

                    // Root service/category URL'leri için 42+ kategoriyi tek tek route'a yazma.
                    // Örn: /de/gebaudereinigung, /de/bueroreinigung, /de/praxisreinigung
                    // Hepsi API'den gelen category_slug / parent.slug / service.slug üzerinden çözülür.
                    $services[$servicePrefix] = $service;
                }
            }

            foreach ($this->slugsFromItem($service) as $slug) {
                $services[$slug] = $service;
            }
        }

        $serviceOk = $serviceResult['ok'] ?? false;
        $pageOk = $pageResult['ok'] ?? false;

        return [
            'ok' => $serviceOk || $pageOk,
            'complete' => $serviceOk && $pageOk,
            'status' => $serviceOk
                ? ($pageResult['status'] ?? $serviceResult['status'] ?? null)
                : ($serviceResult['status'] ?? $pageResult['status'] ?? null),
            'index' => [
                'pages' => $pages,
                'services' => $services,
                'serviceCities' => $serviceCities,
                'servicePrefixes' => $servicePrefixes,
            ],
        ];
    }

    private function getRouteIndex(string $locale): array
    {
        $mainTenant = OmrConfig::tenantForSharedContent();

        if (! $mainTenant) {
            return $this->emptyRouteIndex();
        }

        $cacheKey = "dynamic_route_index_v11_{$mainTenant}_{$locale}";
        $staleKey = "dynamic_route_index_stale_v11_{$mainTenant}_{$locale}";
        $lockKey = "lock_{$cacheKey}";
        $missing = '__dynamic_route_index_missing__';

        $cached = Cache::get($cacheKey, $missing);

        if ($cached !== $missing) {
            return is_array($cached) ? array_merge($this->emptyRouteIndex(), $cached) : $this->emptyRouteIndex();
        }

        $stale = Cache::get($staleKey);

        if ($this->isCoolingDown()) {
            return is_array($stale) ? array_merge($this->emptyRouteIndex(), $stale) : $this->emptyRouteIndex();
        }

        try {
            return Cache::lock($lockKey, 20)->block(4, function () use (
                $cacheKey,
                $staleKey,
                $missing,
                $stale,
                $locale
            ) {
                $cachedAgain = Cache::get($cacheKey, $missing);

                if ($cachedAgain !== $missing) {
                    return is_array($cachedAgain)
                        ? array_merge($this->emptyRouteIndex(), $cachedAgain)
                        : $this->emptyRouteIndex();
                }

                if ($this->isCoolingDown()) {
                    return is_array($stale)
                        ? array_merge($this->emptyRouteIndex(), $stale)
                        : $this->emptyRouteIndex();
                }

                $built = $this->buildRouteIndex($locale);
                $index = array_merge($this->emptyRouteIndex(), $built['index'] ?? []);

                $hasData = ! empty($index['pages']) ||
                    ! empty($index['services']) ||
                    ! empty($index['serviceCities']) ||
                    ! empty($index['servicePrefixes']);

                if (! ($built['ok'] ?? false)) {
                    Cache::put(
                        $cacheKey,
                        is_array($stale) ? array_merge($this->emptyRouteIndex(), $stale) : $this->emptyRouteIndex(),
                        $this->failureTtlByStatus($built['status'] ?? null)
                    );

                    return is_array($stale)
                        ? array_merge($this->emptyRouteIndex(), $stale)
                        : $this->emptyRouteIndex();
                }

                Cache::put(
                    $cacheKey,
                    $index,
                    ($built['complete'] ?? false) && $hasData
                        ? now()->addDays(7)
                        : $this->failureTtlByStatus($built['status'] ?? null)
                );

                if (($built['complete'] ?? false) && $hasData) {
                    Cache::put($staleKey, $index, now()->addDays(14));
                }

                return $index;
            });
        } catch (\Throwable $e) {
            Log::warning('Dynamic route index cache lock/API exception', [
                'locale' => $locale,
                'tenant' => $mainTenant,
                'error' => $e->getMessage(),
            ]);

            Cache::put(
                $cacheKey,
                is_array($stale) ? array_merge($this->emptyRouteIndex(), $stale) : $this->emptyRouteIndex(),
                now()->addMinutes(2)
            );

            return is_array($stale)
                ? array_merge($this->emptyRouteIndex(), $stale)
                : $this->emptyRouteIndex();
        }
    }

    private function renderCorporatePage(array $page, string $locale, string $slug)
    {
        $path = \App\Support\CorporateRoutes::pagePath($page, $locale);
        $prefixed = \App\Support\LocaleMapper::isSupportedWeb(request()->segment(1));
        $target = ($prefixed ? '/'.$locale : '').$path;
        if ($path !== '/'.trim($slug, '/')) {
            $query = request()->getQueryString();
            return redirect()->to($target.($query ? '?'.$query : ''), 301);
        }
        return Inertia::render('StaticPage', [
            'document' => \App\Data\CorporatePageData::from($page, $locale),
            'slug' => $slug, 'locale' => $locale,
        ]);
    }

    public function handle($locale, $slug)
    {
        $locale = $this->resolveLocale($locale);
        $this->applyLocale($locale);

        if (! config('corporate_home.content_ready')) {
            return $this->renderNotFound($locale, (string) $slug);
        }

        $slug = $this->normalizeRouteSlug((string) $slug) ?: '';

        if ($slug === '') {
            return $this->renderNotFound($locale, $slug);
        }

        if ($canonicalSlug = LegacyUrlNormalizer::serviceSlug($slug) ?? LegacyUrlNormalizer::serviceLocationAlias($slug)) {
            $query = request()->getQueryString();
            $target = "/{$locale}/{$canonicalSlug}".($query ? '?'.$query : '');

            return redirect()->to($target, 301);
        }

        $errorSlugMap = [
            '404' => 404,
            '500' => 500,
            'seite-nichtgefunden-404' => 404,
            'not-found-404' => 404,
            'keine-antwort-500' => 500,
            'not-responding-500' => 500,
        ];

        if (isset($errorSlugMap[$slug])) {
            $status = $errorSlugMap[$slug];

            return Inertia::render('Errors/NotFound', [
                'status' => $status,
                'locale' => $locale,
                'page' => null,
            ])->toResponse(request())->setStatusCode($status);
        }

        if ($slug === 'kontakt') {
            return Inertia::render('kontakt/index', compact('locale'));
        }

        /**
         * Sabit liste/index sayfalarını route index'e sokma.
         * Böylece /de/standorte gibi URL'ler her soğuk istekte /api/v2/pages
         * çağrısı tetiklemez.
         */
        if ($slug === 'standorte') {
            return $this->renderNotFound($locale, $slug);
        }

        if (in_array($slug, ['reinigungsleistungen', 'leistungen', 'services'], true)) {
            return app(ServiceController::class)->index();
        }

        /**
         * /de/gebaudereinigung gibi kök hizmet slug'ları şehir sayfası gibi
         * LocationShowController::show($citySlug) tarafına düşmemeli.
         * Route index boş/cooldown olsa bile direkt service show'a bağlanır.
         */
        if (isset($this->staticServicePrefixes()[$slug])) {
            return app(ServiceShowController::class)->show($locale, $slug);
        }

        if (in_array($slug, $this->healthSlugs(), true)) {
            return response()->json([
                'ok' => true,
                'status' => 'healthy',
            ]);
        }

        /**
         * /de/housekeeping-service-bremerhaven gibi no-in URL'ler route index
         * henüz dolmadan da 404'e düşmesin.
         */
        if ($this->splitServiceCityByPrefixes($slug, $this->staticServicePrefixes())) {
            return $this->renderNotFound($locale, $slug);
        }

        $routeIndex = $this->getRouteIndex($locale);

        $pages = is_array($routeIndex['pages'] ?? null) ? $routeIndex['pages'] : [];
        $services = is_array($routeIndex['services'] ?? null) ? $routeIndex['services'] : [];
        $serviceCities = is_array($routeIndex['serviceCities'] ?? null) ? $routeIndex['serviceCities'] : [];
        $servicePrefixes = is_array($routeIndex['servicePrefixes'] ?? null) ? $routeIndex['servicePrefixes'] : [];

        /**
         * City-service URL'leri önce yakala.
         * Aksi halde housekeeping-service-bremerhaven gibi slug'lar yanlışlıkla
         * ServiceShowController'a gider ve ana hizmet bulunamadığı için 404 üretir.
         */
        if (isset($serviceCities[$slug]) && is_array($serviceCities[$slug])) {
            return $this->renderNotFound($locale, $slug);
        }

        if ($this->splitServiceCityByPrefixes($slug, $servicePrefixes)) {
            return $this->renderNotFound($locale, $slug);
        }

        if (isset($pages[$slug]) && is_array($pages[$slug])) {
            return $this->renderCorporatePage($pages[$slug], $locale, $slug);
        }

        // A service-only partial route index must not turn real localized
        // pages into 404s. Resolve the page from the dedicated pages cache,
        // which can also reuse the source-language record and its translations.
        $staticPage = StaticPageController::getPage($locale, $slug);

        if (is_array($staticPage) && ! empty($staticPage)) {
            return $this->renderCorporatePage($staticPage, $locale, $slug);
        }

        foreach (self::slugVariants($slug) as $slugVariant) {
            if (isset($servicePrefixes[$slugVariant])) {
                return app(ServiceShowController::class)->show($locale, $slugVariant);
            }
        }

        if (isset($services[$slug])) {
            return app(ServiceShowController::class)->show($locale, $slug);
        }

        // Son fallback sadece şehir sayfası içindir. Kök hizmet slug'ları yukarıda yakalandığı için
        // /de/gebaudereinigung artık şehir gibi yorumlanıp LocationShowController::show'a düşmez.
        return $this->renderNotFound($locale, $slug);
    }

    public function handleDefault($slug)
    {
        return $this->handle($this->resolveLocale(), $slug);
    }

    public function handleCategoryAlias($locale, $slug)
    {
        $locale = $this->resolveLocale($locale);
        $this->applyLocale($locale);

        if (! config('corporate_home.content_ready')) {
            return $this->renderNotFound($locale, (string) $slug);
        }

        return app(ServiceShowController::class)->show($locale, $slug);
    }

    public function handleCategoryAliasDefault($slug)
    {
        return $this->handleCategoryAlias($this->resolveLocale(), $slug);
    }
}
