<?php

namespace App\Http\Controllers;

use App\Support\DemoContent;
use App\Support\LocaleMapper;
use App\Support\OmrConfig;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index()
    {
        $tenantId = OmrConfig::tenantId();

        $locale = request()->route('locale')
            ?? session('locale')
            ?? OmrConfig::defaultLocale();
        $locale = LocaleMapper::toWeb($locale);
        app()->setLocale($locale);
        session(['locale' => $locale]);

        $headerMenu = [];
        $footerMenu = [];
        $settings = [];
        $widgets = [];
        $sliders = [];
        $reviews = ['items' => [], 'summary' => ['count' => 0, 'average' => null]];
        $faq = ['title' => '', 'items' => [], 'is_demo' => false];

        try {

            $headerMenu = MenuController::getFrontendHeaderMenu($locale) ?? [];
            $footerMenu = MenuController::getFrontendFooterMenu($locale) ?? [];

            $settings = SettingsController::getFrontendSettings($tenantId, $locale) ?? [];

            $widgets = WidgetController::getFrontendWidgets($tenantId, $locale) ?? [];
            $sliders = SliderController::getSliders($tenantId, $locale) ?? [];
            $reviews = ReviewController::getFrontendReviews($tenantId, $locale) ?? $reviews;
            $faq = FaqController::getFrontendFaq($tenantId, $locale) ?? $faq;

        } catch (\Throwable $e) {
            Log::error('HomeController Error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

        }

        // API'de kayıt yoksa bölümler boş kalmasın (bkz. OMR_DEMO_CONTENT)
        $reviews = DemoContent::reviews($reviews, $locale);
        $faq = DemoContent::faq($faq, $locale);

        return Inertia::render('Home', [
            'locale' => $locale,
            'currentYear' => (int) date('Y'),
            'menus' => [
                'header' => $headerMenu,
                'footer' => $footerMenu,
            ],
            'settings' => $settings,
            'widgets' => $widgets,
            'sliders' => $sliders,
            'reviews' => $reviews,
            'faq' => $faq,
        ]);
    }
}
