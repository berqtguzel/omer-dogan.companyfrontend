<?php

namespace App\Http\Controllers;

use App\Services\GlobalSiteDataService;
use App\Support\LocaleMapper;
use App\Support\OmrConfig;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index()
    {
        $locale = LocaleMapper::toWeb(request()->route('locale') ?? OmrConfig::defaultLocale());
        app()->setLocale($locale);
        session(['locale' => $locale]);
        $prefixed = LocaleMapper::isSupportedWeb(request()->segment(1));

        $tenant = OmrConfig::tenantId();
        $settings = app(GlobalSiteDataService::class)->settings($locale);
        $sliders = config('corporate_home.content_ready')
            ? SliderController::getSliders($tenant, $locale)
            : [];

        return Inertia::render('Home', [
            'locale' => $locale,
            'settings' => $settings,
            'home' => app(\App\Services\CorporateContentService::class)->data($locale, $prefixed, $sliders, $settings),
        ]);
    }
}
