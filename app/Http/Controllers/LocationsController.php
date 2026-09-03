<?php

namespace App\Http\Controllers;

use App\Support\OmrConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class LocationsController extends Controller
{
    public function index()
    {
        $tenantId = OmrConfig::tenantId();
        $locale = request()->route('locale')
            ?? session('locale')
            ?? OmrConfig::defaultLocale();
        session(['locale' => $locale]);

        $cacheKey = "frontend_locations_list_v2_{$locale}";


        $response = Cache::remember($cacheKey, now()->addDays(7), function () use ($locale) {
            return LocationController::getFrontendLocations($locale);
        });

        /**
         * /standorte liste sayfası için OMR /pages çağrısı yapma.
         * 429 dönemlerinde bu sayfanın açılması API'yi daha fazla sıkıştırmasın.
         */
        $page = [
            'title' => 'Standorte',
            'content' => null,
            'slug' => 'standorte',
        ];

        return Inertia::render('Locations/Index', [
            'page' => $page,
            'slug' => 'standorte',
            'locations' => [
                'data' => $response['data'] ?? [],
                'meta' => $response['meta'] ?? ($response['pagination'] ?? []),
            ],
            'meta' => $response['meta'] ?? ($response['pagination'] ?? []),
            'maps' => MapController::getFrontendMaps($tenantId, $locale),
            'locale' => $locale,
        ]);
    }

    public function show(string $slug)
    {
        $locale = request()->route('locale')
            ?? session('locale')
            ?? OmrConfig::defaultLocale();

        session(['locale' => $locale]);

        /**
         * Eski show metodu burada tekrar /services?city=... çağırıyordu.
         * Artık şehir detayını LocationShowController çözsün; aynı all_services_v4 cache'i kullanılır.
         */
        return app(LocationShowController::class)->show($locale, $slug);
    }
}
