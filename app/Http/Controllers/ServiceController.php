<?php

namespace App\Http\Controllers;

use App\Support\LocaleMapper;
use App\Support\Omr\OmrCatalog;
use App\Support\OmrConfig;
use Inertia\Inertia;

class ServiceController extends Controller
{
    public function index()
    {
        $tenantId = OmrConfig::tenantId();
        $apiTenant = OmrConfig::tenantForSharedContent();

        $locale = request()->route('locale')
            ?? session('locale')
            ?? OmrConfig::defaultLocale();

        $locale = LocaleMapper::toWeb($locale);

        app()->setLocale($locale);
        session(['locale' => $locale]);

        if (! $tenantId || ! $apiTenant) {
            return response()->json(['error' => 'OMR_TENANT_ID missing'], 500);
        }

        $categories = OmrCatalog::rootCategories($apiTenant, $locale);

        if (empty($categories) && $locale !== 'de') {
            $categories = OmrCatalog::rootCategories($apiTenant, 'de');
        }

        return Inertia::render('Services/Index', [
            'count' => count($categories),
            'categories' => $categories,
            'page' => OmrCatalog::isCoolingDown($apiTenant)
                ? null
                : StaticPageController::getPage($locale, 'reinigungsleistungen'),
            'slug' => 'reinigungsleistungen',
            'locale' => $locale,
        ]);
    }
}
