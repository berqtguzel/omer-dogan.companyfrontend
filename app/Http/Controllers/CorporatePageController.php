<?php

namespace App\Http\Controllers;

use App\Data\CorporatePageData;
use App\Support\LocaleMapper;
use App\Support\OmrConfig;
use Inertia\Inertia;

class CorporatePageController extends Controller
{
    public function show()
    {
        $locale = LocaleMapper::toWeb(request()->route('locale') ?? OmrConfig::defaultLocale());
        app()->setLocale($locale);
        $kind = request()->route('corporatePage');
        $slug = config('corporate_home.static_pages.'.$kind, $kind);
        $page = $slug && config('corporate_home.content_ready')
            ? StaticPageController::getPage($locale, $slug)
            : null;
        $document = ! config('corporate_home.content_ready') && config('corporate_home.preview_content')
            ? \App\Support\CorporatePreviewContent::document($locale, $kind)
            : CorporatePageData::from($page, $locale);
        return Inertia::render('StaticPage', [
            'locale' => $locale, 'pageKind' => $kind,
            'localizedUrls' => \App\Support\CorporateRoutes::alternates($page ?? []),
            'document' => $document,
        ]);
    }
}
