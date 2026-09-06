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

        if (! config('corporate_home.content_ready')) {
            $prefixed = LocaleMapper::isSupportedWeb(request()->segment(1));
            $target = ($prefixed ? '/'.$locale : '').'/geschaeftsbereiche';
            $query = request()->getQueryString();

            return redirect()->to($target.($query ? '?'.$query : ''), 301);
        }

        app()->setLocale($locale);
        session(['locale' => $locale]);

        if (! $tenantId || ! $apiTenant) {
            return response()->json(['error' => 'TENANT_ID missing'], 500);
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
