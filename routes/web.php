<?php

use App\Http\Controllers\BlogController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CorporateCatalogController;
use App\Http\Controllers\CorporatePageController;
use App\Http\Controllers\DynamicSlugController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LegacyUrlRedirectController;
use App\Http\Controllers\MediaCacheController;
use App\Http\Controllers\SeoFilesController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\ApplyLocale;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\MirrorRemoteMedia;
use App\Http\Middleware\PrettyHtmlSource;
use App\Mail\ContactFormMail;
use App\Support\OmrConfig;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

$cacheOnlyXmlMiddleware = [
    ApplyLocale::class,
    HandleInertiaRequests::class,
    MirrorRemoteMedia::class,
    PrettyHtmlSource::class,
];

$webLocalePattern = 'de|en|tr|ru|fr|es|it|pt|ro|pl|cs|sk|bg|hr';

foreach (['unternehmensgruppe', 'ueber-uns', 'karriere', 'impressum', 'datenschutz'] as $corporatePage) {
    Route::get('/'.$corporatePage, [CorporatePageController::class, 'show'])
        ->defaults('corporatePage', $corporatePage)->defaults('locale', OmrConfig::defaultLocale())
        ->name('corporate.page.'.$corporatePage.'.default');
    Route::get('/{locale}/'.$corporatePage, [CorporatePageController::class, 'show'])
        ->defaults('corporatePage', $corporatePage)->where('locale', $webLocalePattern)
        ->middleware('applyLocale')->name('corporate.page.'.$corporatePage);
}

foreach (['geschaeftsbereiche', 'unternehmen', 'projekte'] as $catalog) {
    Route::get('/'.$catalog.'/{item?}', [CorporateCatalogController::class, 'show'])
        ->defaults('catalog', $catalog)->defaults('locale', OmrConfig::defaultLocale())
        ->where('item', '[A-Za-z0-9_-]+')->name('corporate.'.$catalog.'.default');
    Route::get('/{locale}/'.$catalog.'/{item?}', [CorporateCatalogController::class, 'show'])
        ->defaults('catalog', $catalog)->where('locale', $webLocalePattern)
        ->where('item', '[A-Za-z0-9_-]+')->middleware('applyLocale')->name('corporate.'.$catalog);
}

// Repair URLs produced by the former language switcher. The first locale is
// the language the visitor selected; subsequent locale segments are stale.
Route::get('/{locale}/{duplicateLocale}/{path?}', [LegacyUrlRedirectController::class, 'localeChain'])
    ->where([
        'locale' => $webLocalePattern,
        'duplicateLocale' => $webLocalePattern,
        'path' => '.*',
    ]);

// Old deployments sometimes exposed the public directory in canonical URLs.
Route::get('/public/{path?}', [LegacyUrlRedirectController::class, 'publicPath'])
    ->where('path', '.*');

Route::get('/services/{slug}', [LegacyUrlRedirectController::class, 'defaultService'])
    ->where('slug', '[A-Za-z0-9_-]+');

Route::get('/{locale}/services/{slug}', [LegacyUrlRedirectController::class, 'localizedService'])
    ->where([
        'locale' => $webLocalePattern,
        'slug' => '[A-Za-z0-9_-]+',
    ]);

Route::get('/cz/{path?}', function (?string $path = null) {
    $target = '/cs'.($path ? '/'.$path : '');
    $query = request()->getQueryString();

    return redirect($target.($query ? '?'.$query : ''), 301);
})->where('path', '.*');

Route::get('/_mail-preview/contact', function (\Illuminate\Http\Request $request) {
    $isLocalRequest = in_array($request->getHost(), ['localhost', '127.0.0.1', '::1'], true)
        || in_array($request->ip(), ['127.0.0.1', '::1'], true);

    abort_unless(app()->environment(['local', 'testing']) && $isLocalRequest, 404);

    return new ContactFormMail([
        'name' => 'Max Mustermann',
        'company' => 'Mustermann Gebäudeservice GmbH',
        'email' => 'max.mustermann@example.com',
        'phone' => '+49 30 12345678',
        'serviceType' => 'Unterhaltsreinigung',
        'message' => "Guten Tag,\n\nwir interessieren uns für eine regelmäßige Reinigung unserer Büroräume und freuen uns über ein unverbindliches Angebot.",
    ]);
})->name('mail.preview.contact');

Route::get('/media-cache/{path}', [MediaCacheController::class, 'show'])
    ->where('path', '.*')
    ->name('media-cache.show');

Route::get('/storage/media-cache/{path}', [MediaCacheController::class, 'show'])
    ->where('path', '.*')
    ->name('media-cache.storage-fallback');

Route::get('/media-proxy/{token}', [MediaCacheController::class, 'proxy'])
    ->where('token', '[A-Za-z0-9_-]+')
    ->name('media-cache.proxy');

/*
|--------------------------------------------------------------------------
| GLOBAL ROUTES
|--------------------------------------------------------------------------
*/

// Kök adres her zaman Almanca (varsayılan dil) — session/cookie ile /en açılmasın
Route::get('/', [HomeController::class, 'index'])
    ->defaults('locale', OmrConfig::defaultLocale())
    ->name('home.default');

// Global sitemap (locale'siz)
Route::get('/sitemap.xml', [SitemapController::class, 'globalIndex'])
    ->withoutMiddleware($cacheOnlyXmlMiddleware);
Route::get('/favicon.ico', [SeoFilesController::class, 'favicon'])
    ->withoutMiddleware($cacheOnlyXmlMiddleware);
Route::get('/robots.txt', [SeoFilesController::class, 'robots'])
    ->withoutMiddleware($cacheOnlyXmlMiddleware);
Route::get('/humans.txt', [SeoFilesController::class, 'humans']);
Route::get('/llms.txt', [SeoFilesController::class, 'llms']);
Route::get('/manifest.webmanifest', [SeoFilesController::class, 'manifest']);
Route::get('/site.webmanifest', [SeoFilesController::class, 'manifest']);
Route::get('/schema.json', [SeoFilesController::class, 'schema']);
Route::get('/security.txt', [SeoFilesController::class, 'security']);
Route::get('/.well-known/security.txt', [SeoFilesController::class, 'security']);
Route::get('/api/v2/health', function (\Illuminate\Http\Request $request) {
    return response()->json([
        'ok' => true,
        'status' => 'healthy',
        'tenant' => $request->query('tenant'),
    ]);
})->name('api.v2.health');
Route::get('/identity.json', [SeoFilesController::class, 'identity']);
Route::get('/brand.txt', [SeoFilesController::class, 'brand']);
Route::get('/knowledge.json', [SeoFilesController::class, 'knowledge']);
Route::get('/ai.json', [SeoFilesController::class, 'ai']);
Route::get('/.well-known/ai.json', [SeoFilesController::class, 'ai']);
Route::get('/ai/brand.md', [SeoFilesController::class, 'aiBrandMarkdown']);
Route::get('/ai/knowledge.md', [SeoFilesController::class, 'aiKnowledgeMarkdown']);
Route::get('/ai/about.md', [SeoFilesController::class, 'aiAboutMarkdown']);
Route::post('/_seo-files/sync', [SeoFilesController::class, 'sync'])
    ->middleware('throttle:3,1');

Route::get('/reinigungsleistungen', [ServiceController::class, 'index'])
    ->defaults('locale', OmrConfig::defaultLocale())
    ->name('services.default');

Route::get('/blog', [BlogController::class, 'index'])
    ->defaults('locale', OmrConfig::defaultLocale())
    ->name('blog.default');

Route::get('/blog/{category}/{slug}', [BlogController::class, 'show'])
    ->defaults('locale', OmrConfig::defaultLocale())
    ->name('blog.show.category.default');

Route::get('/blog/{slug}', [BlogController::class, 'show'])
    ->defaults('locale', OmrConfig::defaultLocale())
    ->name('blog.show.default');

Route::get('/kontakt', [ContactController::class, 'index'])
    ->defaults('locale', OmrConfig::defaultLocale())
    ->name('kontakt.default');

Route::post('/kontakt', [ContactController::class, 'submit'])
    ->defaults('locale', OmrConfig::defaultLocale())
    ->name('kontakt.submit.default');

// Legacy/API category path alias. It deliberately reuses the normal dynamic
// service resolver; no menu or frontend link is generated for this URL.
Route::get('/categories/{slug}', [DynamicSlugController::class, 'handleCategoryAliasDefault'])
    ->where('slug', '[A-Za-z0-9_-]+')
    ->name('categories.alias.default');

/*
|--------------------------------------------------------------------------
| LOCALE GROUP
|--------------------------------------------------------------------------
*/

Route::group([
    'prefix' => '{locale}',

    'where' => ['locale' => $webLocalePattern],
    'middleware' => ['applyLocale'],
], function () use ($cacheOnlyXmlMiddleware) {

    /*
    |--------------------------------------------------------------------------
    | CORE PAGES
    |--------------------------------------------------------------------------
    */

    Route::get('/', [HomeController::class, 'index'])->name('home');

    Route::get('/reinigungsleistungen', [ServiceController::class, 'index'])
        ->name('services.index');

    Route::get('/blog', [BlogController::class, 'index'])
        ->name('blog.index');

    Route::get('/blog/{category}/{slug}', [BlogController::class, 'show'])
        ->name('blog.show.category');

    Route::get('/blog/{slug}', [BlogController::class, 'show'])
        ->name('blog.show');

    Route::get('/kontakt', [ContactController::class, 'index'])
        ->name('kontakt.index');

    Route::post('/kontakt', [ContactController::class, 'submit'])
        ->name('kontakt.submit');

    Route::get('/categories/{slug}', [DynamicSlugController::class, 'handleCategoryAlias'])
        ->where('slug', '[A-Za-z0-9_-]+')
        ->name('categories.alias');

    /*
    |--------------------------------------------------------------------------
    | SITEMAPS (LOCALE)
    |--------------------------------------------------------------------------
    */

    Route::withoutMiddleware($cacheOnlyXmlMiddleware)->group(function () {
        Route::get('/sitemap.xml', [SitemapController::class, 'index']);
        Route::get('/sitemap-pages.xml', [SitemapController::class, 'pages']);
        Route::get('/sitemap-services.xml', [SitemapController::class, 'services']);
        Route::get('/sitemap-{module}.xml', [SitemapController::class, 'module'])
            ->where('module', '[A-Za-z0-9_-]+');
    });

    /*
    |--------------------------------------------------------------------------
    | DYNAMIC SLUG (EN SONA!)
    |--------------------------------------------------------------------------
    */

    Route::get('/{slug}', [DynamicSlugController::class, 'handle'])
        ->where('slug', '^(?!kontakt|standorte|reinigungsleistungen|blog|sitemap).*') // reserved yolları exclude
        ->name('dynamic.slug');
});

Route::get('/{slug}', [DynamicSlugController::class, 'handleDefault'])
    ->where('slug', '^(?!api$|admin$|tenant$|storage$|build$|assets$|vite$|blog$|sitemap(?:.*)$|robots\.txt$|_mail-preview$).+')
    ->name('dynamic.slug.default');

/*
|--------------------------------------------------------------------------
| FALLBACK
|--------------------------------------------------------------------------
*/

Route::fallback(function () {
    return Inertia::render('Errors/NotFound')
        ->toResponse(request())
        ->setStatusCode(404);
});
