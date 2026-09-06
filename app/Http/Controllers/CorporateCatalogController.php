<?php

namespace App\Http\Controllers;

use App\Data\NavigationData;
use App\Support\LocaleMapper;
use App\Support\OmrConfig;
use Inertia\Inertia;

class CorporateCatalogController extends Controller
{
    public function show()
    {
        $locale = LocaleMapper::toWeb(request()->route('locale') ?? OmrConfig::defaultLocale());
        app()->setLocale($locale);
        $prefixed = LocaleMapper::isSupportedWeb(request()->segment(1));
        $kind = request()->route('catalog');
        $key = ['geschaeftsbereiche' => 'businessAreas', 'unternehmen' => 'companies', 'projekte' => 'projects'][$kind];
        $data = app(\App\Services\CorporateContentService::class)->data($locale, $prefixed);
        $items = array_values(array_filter($data[$key], fn ($item) => ! empty($item['link'])));
        $slug = request()->route('item');
        $item = $slug === null ? null : collect($items)->firstWhere('id', $slug);
        if ($slug !== null && ! $item) {
            return Inertia::render('Errors/NotFound', ['locale' => $locale])->toResponse(request())->setStatusCode(404);
        }
        $related = []; // No confirmed panel relationship field.
        return Inertia::render('Corporate/Catalog', [
            'locale' => $locale,
            'localizedUrls' => $item['alternates'] ?? null,
            'catalog' => [
                'kind' => $key, 'title' => '', 'description' => '',
                'items' => $items, 'item' => $item, 'related' => $related,
                'indexLink' => [...NavigationData::link('/'.$kind, $locale, $prefixed, ''), 'newTab' => false],
            ],
        ]);
    }
}
