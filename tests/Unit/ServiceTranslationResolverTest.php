<?php

use App\Services\ServiceTranslationResolver;
use App\Services\SitemapLiveService;
use App\Services\SitemapTranslationAuditService;
use App\Services\SitemapWarmService;
use App\Support\LocaleMapper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

function serviceFixture(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 48,
        'slug' => 'hotelreinigung-aalen',
        'updated_at' => '2026-08-01T10:00:00Z',
        'name' => 'English top-level name',
        'content' => 'English top-level content',
        'meta_title' => 'Mixed top-level meta title',
        'translations' => [
            [
                'language_code' => 'de',
                'name' => 'Hotelreinigung Aalen',
                'content' => '<h2>Deutscher Inhalt</h2>',
                'short_description' => 'Deutsche Kurzbeschreibung',
                'meta_title' => 'Deutscher Metatitel',
                'meta_description' => 'Deutsche Metabeschreibung',
            ],
            [
                'language_code' => 'tr',
                'name' => 'Aalen Otel Temizliği',
                'content' => '<h2>Türkçe içerik</h2>',
            ],
            [
                'language_code' => 'cz',
                'name' => 'Úklid hotelu Aalen',
                'content' => '<h2>Český obsah</h2>',
            ],
        ],
    ], $overrides);
}

beforeEach(function () {
    config(['cache.default' => 'array']);
    Cache::flush();
});

test('uses German and Turkish API translations', function (string $locale, string $expected) {
    $result = (new ServiceTranslationResolver)->resolve(serviceFixture(), $locale, 'tenant-a');

    expect($result['name'])->toBe($expected)
        ->and(data_get($result, '_translation.origin'))->toBe('api')
        ->and(data_get($result, '_translation.indexable'))->toBeTrue();
})->with([
    ['de', 'Hotelreinigung Aalen'],
    ['tr', 'Aalen Otel Temizliği'],
]);

test('maps web Czech cs to API Czech cz', function () {
    $service = serviceFixture();
    $service['translations'][2]['slug'] = 'uklid-hotelu-aalen';
    $resolver = new ServiceTranslationResolver;
    $result = $resolver->resolve($service, 'cs', 'tenant-a');

    expect(LocaleMapper::toApi('cs'))->toBe('cz')
        ->and(LocaleMapper::toWeb('cz'))->toBe('cs')
        ->and(LocaleMapper::toWeb('cs_CZ'))->toBe('cs')
        ->and($result['name'])->toBe('Úklid hotelu Aalen')
        ->and($result['_resolved_locale'])->toBe('cs')
        ->and($resolver->publicSlug($service, 'cs'))->toBe('uklid-hotelu-aalen');
});

test('treats missing or empty-html required fields as unusable', function () {
    $service = serviceFixture();
    $service['translations'][] = ['language_code' => 'fr', 'name' => 'Nom', 'content' => '<p> &nbsp; </p>'];
    $resolver = new ServiceTranslationResolver;

    expect($resolver->hasUsableTranslation($service, 'fr'))->toBeFalse()
        ->and($resolver->hasUsableTranslation($service, 'es'))->toBeFalse();
});

test('missing translations use German noindex fallback without external translation', function () {
    $result = (new ServiceTranslationResolver)->resolve(serviceFixture(), 'fr', 'tenant-a');

    expect($result['name'])->toBe('Hotelreinigung Aalen')
        ->and(data_get($result, '_translation.origin'))->toBe('fallback')
        ->and(data_get($result, '_translation.indexable'))->toBeFalse()
        ->and(data_get($result, '_translation.reason'))->toBe('translation_unavailable');
});

test('missing service translations permanently redirect to the German service URL', function () {
    $tenant = 'service-redirect-'.uniqid();
    config([
        'services.omr.main_tenant' => $tenant,
        'services.omr.tenant_id' => $tenant,
        'services.omr.api_version' => 2,
        'services.omr.retry_count' => 0,
    ]);

    $service = serviceFixture([
        'slug' => 'fassadenreinigung',
        'category_slug' => 'fassadenreinigung',
        'parent_id' => null,
        'translations' => [[
            'language_code' => 'de',
            'slug' => 'fassadenreinigung',
            'name' => 'Fassadenreinigung',
            'content' => '<p>Deutscher Inhalt</p>',
        ], [
            'language_code' => 'ru',
            'slug' => 'fassadenreinigung',
            'name' => 'Fassadenreinigung',
            'content' => '<p>Deutscher Inhalt</p>',
        ]],
    ]);

    Http::fake(fn () => Http::response([
        'data' => [$service],
        'pagination' => ['last_page' => 1],
    ]));

    expect(App\Support\Omr\OmrCatalog::warmServices($tenant, 'ru', [
        'parent_id' => 'null',
        '_max_pages' => 1,
    ])['ok'])->toBeTrue();

    $response = app(App\Http\Controllers\DynamicSlugController::class)
        ->handleCategoryAlias('ru', 'fassadenreinigung');

    expect($response->getStatusCode())->toBe(301)
        ->and($response->getTargetUrl())->toEndWith('/de/fassadenreinigung');
});

test('category alias renders a real locale translation', function () {
    $this->withoutMiddleware(App\Http\Middleware\ApplyLocale::class);

    $tenant = 'category-alias-'.uniqid();
    config([
        'services.omr.main_tenant' => $tenant,
        'services.omr.tenant_id' => $tenant,
        'services.omr.api_version' => 2,
        'services.omr.retry_count' => 0,
        'inertia.ssr.enabled' => false,
    ]);

    $service = serviceFixture([
        'slug' => 'apartmentreinigung',
        'category_slug' => 'apartmentreinigung',
        'parent_id' => null,
        'translations' => [[
            'language_code' => 'de',
            'slug' => 'apartmentreinigung',
            'name' => 'Apartmentreinigung',
            'content' => '<p>Deutscher Inhalt</p>',
        ], [
            'language_code' => 'es',
            'slug' => 'apartmentreinigung',
            'name' => 'Limpieza de apartamentos',
            'content' => '<p>Contenido español</p>',
        ]],
    ]);

    Http::fake(fn () => Http::response([
        'data' => [$service],
        'pagination' => ['last_page' => 1],
    ]));

    expect(App\Support\Omr\OmrCatalog::warmServices($tenant, 'es', [
        'parent_id' => 'null',
        '_max_pages' => 1,
    ])['ok'])->toBeTrue();

    $this->get('/es/categories/apartmentreinigung')->assertOk();
});

test('unexpected API shapes do not throw and become a safe noindex fallback', function () {
    $service = ['id' => 99, 'translations' => null, 'name' => null, 'content' => []];
    $result = (new ServiceTranslationResolver)->resolve($service, 'fr', 'tenant-a');

    expect(data_get($result, '_translation.indexable'))->toBeFalse()
        ->and(data_get($result, '_translation.reason'))->toBe('missing_source');
});

test('available locales contain only usable API translations', function () {
    $service = serviceFixture();
    $service['translations'][] = ['language_code' => 'fr', 'name' => 'Nom', 'content' => '<p> </p>'];

    expect((new ServiceTranslationResolver)->availableWebLocales($service, 'tenant-a'))
        ->toBe(['de', 'tr', 'cs']);
});

test('localized service sitemap excludes URLs without an API translation', function () {
    $service = app(SitemapWarmService::class);
    $method = new ReflectionMethod($service, 'translationAllowed');
    $item = serviceFixture([
        '_translation' => [
            'indexable' => false,
            'origin' => 'fallback',
        ],
    ]);

    expect($method->invoke(
        $service,
        $item,
        'https://oi-clean.de/fr/hotelreinigung-aalen',
        'fr',
        'services'
    ))->toBeFalse();
});

test('live sitemap uses the same core service translation decision as the rendered page', function () {
    $audit = app(SitemapTranslationAuditService::class);
    $method = new ReflectionMethod($audit, 'serviceTranslationDecision');
    $germanContent = '<p>Nur deutscher Inhalt</p>';
    $service = [
        'translations' => [
            ['language_code' => 'de', 'name' => 'Reinigung', 'content' => $germanContent],
            [
                'language_code' => 'bg',
                'name' => 'Почистване',
                'content' => '',
                'short_description' => 'Формално попълнено, но без основно съдържание',
            ],
            ['language_code' => 'cz', 'name' => 'Úklid', 'content' => $germanContent],
        ],
    ];

    expect($method->invoke($audit, $service, 'bg'))
        ->toBe(['allowed' => false, 'reason' => 'empty_translation'])
        ->and($method->invoke($audit, $service, 'cs'))
        ->toBe(['allowed' => false, 'reason' => 'german_fallback']);
});

test('sitemap XML remains valid and public Czech URLs use cs', function () {
    config(['sitemap.base_url' => 'https://oi-clean.de']);
    $service = app(SitemapWarmService::class);
    $urlMethod = new ReflectionMethod($service, 'productionUrl');
    $xmlMethod = new ReflectionMethod($service, 'buildUrlsetXml');
    $url = $urlMethod->invoke($service, 'https://example.test/cz/hotelreinigung-aalen', 'cs');
    $xml = $xmlMethod->invoke($service, [[
        'loc' => $url,
        'lastmod' => null,
        'freq' => null,
        'priority' => null,
    ]]);

    expect($url)->toBe('https://oi-clean.de/cs/hotelreinigung-aalen')
        ->and(simplexml_load_string($xml))->not->toBeFalse();
});

test('German sitemap normalizes the retired gastronomy cleaning slug only', function () {
    config(['sitemap.base_url' => 'https://oi-clean.de']);
    $live = app(SitemapLiveService::class);
    $warm = app(SitemapWarmService::class);
    $liveUrl = new ReflectionMethod($live, 'productionUrl');
    $warmUrl = new ReflectionMethod($warm, 'productionUrl');
    $old = 'https://panel.example/de/gastronomy-cleaning-aalen';

    expect($liveUrl->invoke($live, $old, 'de'))
        ->toBe('https://oi-clean.de/de/gastronomiereinigung-aalen')
        ->and($warmUrl->invoke($warm, $old, 'de'))
        ->toBe('https://oi-clean.de/de/gastronomiereinigung-aalen')
        ->and($liveUrl->invoke($live, 'https://panel.example/en/gastronomy-cleaning-aalen', 'en'))
        ->toBe('https://oi-clean.de/en/gastronomy-cleaning-aalen');
});
