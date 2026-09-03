<?php

use App\Services\SitemapCacheStore;
use App\Services\SitemapWarmService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

function configureSitemapTest(string $tenant, array $overrides = []): void
{
    config(array_merge([
        'services.omr.base' => 'https://omerdogan.de/api',
        'services.omr.api_version' => 2,
        'services.omr.retry_count' => 0,
        'services.omr.main_tenant' => $tenant,
        'services.omr.tenant_id' => $tenant,
        'sitemap.base_url' => 'https://oi-clean.de',
        'sitemap.source' => 'live',
        'sitemap.locales' => ['de', 'en'],
        'sitemap.modules' => ['pages', 'blog', 'services', 'locations', 'serviceLocation'],
        'sitemap.snapshot_path' => storage_path('framework/testing/sitemaps'),
        'sitemap.max_urls_per_file' => 50000,
        // Legacy fixtures expose only sitemap rows. Dedicated strict-filter tests
        // below provide the raw content API contracts explicitly.
        'sitemap.require_raw_translations' => false,
    ], $overrides));
}

function sitemapApiResponse($request)
{
    $path = parse_url($request->url(), PHP_URL_PATH);

    return match ($path) {
        '/api/v2/sitemap' => Http::response([
            'data' => [
                'locales' => ['de', 'en'],
                'locales_detail' => [
                    ['locale' => 'de', 'modules' => [
                        ['url' => 'https://oi-clean.de/de/sitemap-pages.xml'],
                        ['url' => 'https://oi-clean.de/de/sitemap-blog.xml'],
                    ]],
                    ['locale' => 'en', 'modules' => [
                        ['url' => 'https://oi-clean.de/en/sitemap-pages.xml'],
                        ['url' => 'https://oi-clean.de/en/sitemap-blog.xml'],
                    ]],
                ],
            ],
        ]),
        '/api/v2/sitemap/de' => Http::response([
            'data' => ['modules' => [
                ['url' => 'https://oi-clean.de/de/sitemap-pages.xml'],
                ['url' => 'https://oi-clean.de/de/sitemap-blog.xml'],
            ]],
        ]),
        '/api/v2/sitemap/en' => Http::response([
            'data' => ['modules' => [
                ['url' => 'https://oi-clean.de/en/sitemap-pages.xml'],
                ['url' => 'https://oi-clean.de/en/sitemap-blog.xml'],
            ]],
        ]),
        '/api/v2/sitemap/de/pages' => Http::response(['data' => ['urls' => [
            ['loc' => 'https://panel.example/de', 'lastmod' => '2026-08-01T10:00:00Z'],
            ['loc' => 'https://panel.example/de/kontakt'],
        ]]]),
        '/api/v2/sitemap/de/blog' => Http::response(['data' => ['urls' => [
            ['loc' => 'https://panel.example/de/kontakt'],
            ['loc' => 'https://panel.example/de/blog/post-a', 'updated_at' => '2026-07-01T12:00:00Z'],
        ]]]),
        '/api/v2/sitemap/en/pages' => Http::response(['data' => ['urls' => [
            ['loc' => 'https://panel.example/en', 'locale' => 'en'],
        ]]]),
        '/api/v2/sitemap/en/blog' => Http::response(['data' => ['urls' => [
            ['loc' => 'https://panel.example/en/blog/german-fallback', 'language_code' => 'de'],
            ['loc' => 'https://panel.example/en/blog/english-post', 'language_code' => 'en'],
        ]]]),
        default => Http::response(['message' => "Unexpected test endpoint {$path}"], 404),
    };
}

it('can still build a legacy sitemap snapshot manually', function () {
    $tenant = 'sitemap-flat-'.uniqid();
    configureSitemapTest($tenant);
    Http::fake(fn ($request) => sitemapApiResponse($request));

    $result = app(SitemapWarmService::class)->warm(force: true);

    expect($result['ok'])->toBeTrue()
        ->and($result['locales'])->toBe(['de', 'en'])
        ->and($result['sitemap_count'])->toBe(4)
        ->and($result['url_count'])->toBe(5)
        ->and($result['duplicates_removed'])->toBe(1)
        ->and($result['translations_removed'])->toBe(1);

    $store = app(SitemapCacheStore::class);

    expect($store->document('index')['xml'])
        ->toContain('https://oi-clean.de/de/sitemap-pages.xml')
        ->toContain('https://oi-clean.de/en/sitemap-blog.xml')
        ->not->toContain('panel.example');
});

it('caches the discovered non-empty index and fetches only the requested child sitemap', function () {
    $tenant = 'sitemap-live-'.uniqid();
    configureSitemapTest($tenant);
    Http::fake(fn ($request) => sitemapApiResponse($request));

    $root = $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertHeader('X-Sitemap-Source', 'live');

    expect(simplexml_load_string($root->getContent()))->not->toBeFalse()
        ->and($root->headers->get('Cache-Control'))->toContain('no-store')
        ->and($root->getContent())->toContain('https://oi-clean.de/de/sitemap-pages.xml')
        ->toContain('https://oi-clean.de/en/sitemap-blog.xml')
        ->not->toContain('/de/sitemap.xml')
        ->and(Http::recorded())->toHaveCount(1);

    $this->get('/sitemap.xml')->assertOk();
    expect(Http::recorded())->toHaveCount(1);

    $module = $this->get('/de/sitemap-pages.xml')
        ->assertOk()
        ->assertHeader('X-Sitemap-Source', 'live');

    expect($module->getContent())
        ->toContain('<urlset')
        ->toContain('https://oi-clean.de/de')
        ->not->toContain('panel.example')
        ->and(Http::recorded())->toHaveCount(2);

    $this->get('/de/sitemap-pages.xml')->assertOk();
    expect(Http::recorded())->toHaveCount(2);
});

it('keeps the previous active version when a forced rebuild fails', function () {
    $tenant = 'sitemap-stale-'.uniqid();
    configureSitemapTest($tenant);
    $upstreamDown = false;
    Http::fake(function ($request) use (&$upstreamDown) {
        return $upstreamDown
            ? Http::response(['message' => 'upstream unavailable'], 503)
            : sitemapApiResponse($request);
    });

    $first = app(SitemapWarmService::class)->warm(force: true);
    $store = app(SitemapCacheStore::class);
    $oldVersion = $store->activeVersion();
    $oldXml = $store->document('index')['xml'];

    $upstreamDown = true;
    $failed = app(SitemapWarmService::class)->warm(force: true);

    expect($first['ok'])->toBeTrue()
        ->and($failed['ok'])->toBeFalse()
        ->and($failed['stale_preserved'])->toBeTrue()
        ->and($store->activeVersion())->toBe($oldVersion)
        ->and($store->document('index')['xml'])->toBe($oldXml);
});

it('does not mistake a fresh partial locale build for a complete warm', function () {
    $tenant = 'sitemap-partial-fresh-'.uniqid();
    configureSitemapTest($tenant);
    Http::fake(fn ($request) => sitemapApiResponse($request));

    $partial = app(SitemapWarmService::class)->warm('de', force: true);
    $complete = app(SitemapWarmService::class)->warm();

    expect($partial['ok'])->toBeTrue()
        ->and($partial['locales'])->toBe(['de'])
        ->and($complete['ok'])->toBeTrue()
        ->and($complete['skipped'] ?? false)->toBeFalse()
        ->and($complete['locales'])->toBe(['de', 'en']);
});

it('omits a module when all non-empty API records fail indexability validation', function () {
    $tenant = 'sitemap-filtered-module-'.uniqid();
    configureSitemapTest($tenant, ['sitemap.locales' => ['de']]);

    Http::fake(function ($request) {
        $path = parse_url($request->url(), PHP_URL_PATH);

        return match ($path) {
            '/api/v2/sitemap' => Http::response(['data' => ['locales' => ['de']]]),
            '/api/v2/sitemap/de' => Http::response(['data' => ['modules' => [
                ['url' => 'https://oi-clean.de/de/sitemap-pages.xml'],
                ['url' => 'https://oi-clean.de/de/sitemap-blog.xml'],
            ]]]),
            '/api/v2/sitemap/de/pages' => Http::response(['data' => ['urls' => [
                ['loc' => 'https://oi-clean.de/de'],
            ]]]),
            '/api/v2/sitemap/de/blog' => Http::response(['data' => ['urls' => [
                ['loc' => 'https://oi-clean.de/de/blog/fallback', 'noindex' => true],
            ]]]),
            default => Http::response([], 404),
        };
    });

    $result = app(SitemapWarmService::class)->warm(force: true);
    $index = app(SitemapCacheStore::class)->document('index')['xml'];

    expect($result['ok'])->toBeTrue()
        ->and($result['empty_modules_skipped'])->toBe(1)
        ->and($result['sitemap_count'])->toBe(1)
        ->and($index)->toContain('/de/sitemap-pages.xml')
        ->not->toContain('/de/sitemap-blog.xml');
});

it('follows API pagination and dynamically chunks service-location sitemaps', function () {
    $tenant = 'sitemap-pagination-'.uniqid();
    configureSitemapTest($tenant, [
        'sitemap.locales' => ['de'],
        'sitemap.max_urls_per_file' => 2,
    ]);

    Http::fake(function ($request) {
        $path = parse_url($request->url(), PHP_URL_PATH);
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        if ($path === '/api/v2/sitemap') {
            return Http::response(['data' => ['locales' => ['de']]]);
        }

        if ($path === '/api/v2/sitemap/de') {
            return Http::response(['data' => ['modules' => [[
                'url' => 'https://oi-clean.de/de/sitemap-serviceLocation-1.xml',
            ]]]]);
        }

        if ($path === '/api/v2/sitemap/de/serviceLocation' && (int) ($query['page'] ?? 1) === 1) {
            return Http::response([
                'data' => [
                    'page' => 1,
                    'total' => 3,
                    'per_page' => 2,
                    'total_pages' => 2,
                    'urls' => [
                        ['loc' => 'https://oi-clean.de/de/service-a-berlin'],
                        ['loc' => 'https://oi-clean.de/de/service-b-berlin'],
                    ],
                ],
            ]);
        }

        if ($path === '/api/v2/sitemap/de/serviceLocation' && (int) ($query['page'] ?? 1) === 2) {
            return Http::response([
                'data' => [
                    'page' => 2,
                    'total' => 3,
                    'per_page' => 2,
                    'total_pages' => 2,
                    'urls' => [
                        ['loc' => 'https://oi-clean.de/de/service-c-hamburg'],
                    ],
                ],
            ]);
        }

        return Http::response([], 404);
    });

    $result = app(SitemapWarmService::class)->warm(force: true);
    $store = app(SitemapCacheStore::class);

    expect($result['ok'])->toBeTrue()
        ->and($result['api_pages'])->toBe(2)
        ->and($result['sitemap_count'])->toBe(2)
        ->and($store->document('de:serviceLocation:1')['count'])->toBe(2)
        ->and($store->document('de:serviceLocation:2')['count'])->toBe(1)
        ->and($store->document('index')['xml'])->toContain('sitemap-serviceLocation-2.xml');
});

it('serves a deterministic cached index after the first API call', function () {
    $tenant = 'sitemap-bootstrap-'.uniqid();
    configureSitemapTest($tenant);
    Http::fake(fn ($request) => sitemapApiResponse($request));

    $first = $this->get('/sitemap.xml')->assertOk();
    $second = $this->get('/sitemap.xml')->assertOk();

    expect($first->getContent())->toBe($second->getContent())
        ->and($first->getContent())->toContain('https://oi-clean.de/de/sitemap-pages.xml')
        ->toContain('https://oi-clean.de/en/sitemap-pages.xml')
        ->not->toContain('sitemap-services.xml')
        ->and(Http::recorded()->count())->toBe(1);
});

it('includes every configured language and module in the root index', function () {
    $tenant = 'sitemap-all-locales-'.uniqid();
    configureSitemapTest($tenant, [
        'sitemap.locales' => ['de', 'en', 'tr', 'ru', 'fr', 'es', 'it', 'pt', 'ro', 'pl', 'cs', 'sk', 'bg', 'hr'],
    ]);
    Http::fake(function ($request) {
        $path = parse_url($request->url(), PHP_URL_PATH);

        if ($path !== '/api/v2/sitemap') {
            return Http::response([], 404);
        }

        $details = collect(['de', 'en', 'tr', 'ru', 'fr', 'es', 'it', 'pt', 'ro', 'pl', 'cz', 'sk', 'bg', 'hr'])
            ->map(fn ($locale) => [
                'locale' => $locale,
                'modules' => collect(['pages', 'blog', 'services', 'locations', 'serviceLocation'])
                    ->map(fn ($module) => [
                        'url' => "https://oi-clean.de/{$locale}/sitemap-{$module}".($module === 'serviceLocation' ? '-1' : '').'.xml',
                    ])->all(),
            ])->all();

        return Http::response(['data' => ['locales_detail' => $details]]);
    });

    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
    $document = simplexml_load_string(trim($xml));

    expect($document)->not->toBeFalse()
        ->and($document->sitemap)->toHaveCount(70)
        ->and($xml)->toContain('https://oi-clean.de/ru/sitemap-blog.xml')
        ->toContain('https://oi-clean.de/cs/sitemap-serviceLocation-1.xml')
        ->toContain('https://oi-clean.de/hr/sitemap-services.xml')
        ->and(Http::recorded())->toHaveCount(1);
});

it('removes a sitemap from the cached index when its child response is empty', function () {
    $tenant = 'sitemap-empty-child-'.uniqid();
    configureSitemapTest($tenant, [
        'sitemap.locales' => ['de'],
        'sitemap.empty_cache_seconds' => 60,
    ]);
    $blogHasContent = false;

    Http::fake(function ($request) use (&$blogHasContent) {
        $path = parse_url($request->url(), PHP_URL_PATH);

        return match ($path) {
            '/api/v2/sitemap' => Http::response(['data' => ['locales_detail' => [[
                'locale' => 'de',
                'modules' => [
                    ['url' => 'https://oi-clean.de/de/sitemap-pages.xml'],
                    ['url' => 'https://oi-clean.de/de/sitemap-blog.xml'],
                ],
            ]]]]),
            '/api/v2/sitemap/de/blog' => Http::response(['data' => ['urls' => $blogHasContent
                ? [['loc' => 'https://panel.example/de/blog/neuer-beitrag']]
                : [],
            ]]),
            default => Http::response([], 404),
        };
    });

    $first = $this->get('/sitemap.xml')->assertOk();
    expect($first->getContent())->toContain('/de/sitemap-blog.xml');

    $this->get('/de/sitemap-blog.xml')->assertNotFound();

    $second = $this->get('/sitemap.xml')->assertOk();
    expect($second->getContent())
        ->toContain('/de/sitemap-pages.xml')
        ->not->toContain('/de/sitemap-blog.xml');

    $blogHasContent = true;
    $this->travel(61)->seconds();

    $third = $this->get('/sitemap.xml')->assertOk();
    expect($third->getContent())->toContain('/de/sitemap-blog.xml');

    $this->get('/de/sitemap-blog.xml')
        ->assertOk()
        ->assertSee('/de/blog/neuer-beitrag', false);
});

it('publishes only the canonical root sitemap in robots.txt', function () {
    $tenant = 'sitemap-robots-'.uniqid();
    configureSitemapTest($tenant);
    Http::fake();

    $response = $this->get('/robots.txt')->assertOk();

    expect($response->getContent())
        ->toContain('User-agent: *')
        ->toContain('Allow: /')
        ->toContain('Sitemap: https://oi-clean.de/sitemap.xml')
        ->not->toContain('/de/sitemap.xml');
});

it('filters foreign sitemap URLs by raw translation records and reports fallback reasons', function () {
    $tenant = 'sitemap-strict-'.uniqid();
    configureSitemapTest($tenant, ['sitemap.require_raw_translations' => true]);

    Http::fake(function ($request) {
        $path = parse_url($request->url(), PHP_URL_PATH);
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        if ($path === '/api/v2/sitemap') {
            return Http::response(['data' => ['locales_detail' => collect(['de', 'en'])->map(fn ($locale) => [
                'locale' => $locale,
                'modules' => [
                    ['url' => "https://oi-clean.de/{$locale}/sitemap-pages.xml"],
                    ['url' => "https://oi-clean.de/{$locale}/sitemap-blog.xml"],
                    ['url' => "https://oi-clean.de/{$locale}/sitemap-serviceLocation-1.xml"],
                ],
            ])->all()]]);
        }

        $moduleRows = [
            '/api/v2/sitemap/de/pages' => ['/de/startseite', '/de/only-german'],
            '/api/v2/sitemap/de/blog' => ['/de/blog/fallback-post', '/de/blog/english-post'],
            '/api/v2/sitemap/en/pages' => ['/en/startseite', '/en/only-german'],
            '/api/v2/sitemap/en/blog' => ['/en/blog/fallback-post', '/en/blog/english-post'],
        ];

        if (isset($moduleRows[$path])) {
            return Http::response(['data' => ['urls' => collect($moduleRows[$path])
                ->map(fn ($url) => ['loc' => 'https://panel.example'.$url])->all()]]);
        }

        if ($path === '/api/v2/pages') {
            return Http::response(['data' => [
                ['slug' => 'startseite', 'translations' => [
                    ['language_code' => 'de', 'name' => 'Startseite', 'content' => 'Deutsch'],
                    ['language_code' => 'en', 'name' => 'Home', 'content' => 'English'],
                ]],
                ['slug' => 'only-german', 'translations' => [
                    ['language_code' => 'de', 'name' => 'Nur Deutsch', 'content' => 'Deutsch'],
                    ['language_code' => 'en', 'name' => '', 'content' => null],
                ]],
            ], 'meta' => ['last_page' => 1]]);
        }

        if ($path === '/api/v2/blog-posts') {
            $english = ($query['locale'] ?? 'de') === 'en';

            return Http::response(['data' => [
                ['id' => 1, 'slug' => 'fallback-post', 'title' => 'German title', 'content' => 'German body'],
                ['id' => 2, 'slug' => 'english-post', 'title' => $english ? 'English title' : 'Deutscher Titel', 'content' => $english ? 'English body' : 'Deutscher Text'],
            ], 'meta' => ['last_page' => 1]]);
        }

        return Http::response([], 404);
    });

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('/de/sitemap-pages.xml', false)
        ->assertDontSee('/en/sitemap-pages.xml', false)
        ->assertDontSee('/en/sitemap-blog.xml', false)
        ->assertDontSee('/en/sitemap-serviceLocation-1.xml', false)
        ->assertSee('/de/sitemap-serviceLocation-1.xml', false);

    $this->get('/en/sitemap-serviceLocation-1.xml')->assertNotFound();

    $this->get('/en/sitemap-pages.xml')
        ->assertOk()
        ->assertSee('<loc>https://oi-clean.de/en/</loc>', false)
        ->assertDontSee('/en/startseite', false)
        ->assertDontSee('/en/only-german', false);

    $this->get('/en/sitemap-blog.xml')
        ->assertOk()
        ->assertSee('/en/blog/english-post', false)
        ->assertDontSee('/en/blog/fallback-post', false);

    $this->get('/de/sitemap-pages.xml')
        ->assertOk()
        ->assertSee('/de/only-german', false);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('/en/sitemap-pages.xml', false)
        ->assertSee('/en/sitemap-blog.xml', false);
});

it('uses cs for sitemap endpoints and serves German service locations without reloading the full catalog', function () {
    $tenant = 'sitemap-cs-fast-'.uniqid();
    configureSitemapTest($tenant, ['sitemap.require_raw_translations' => true]);

    Http::fake(function ($request) {
        $path = parse_url($request->url(), PHP_URL_PATH);

        return match ($path) {
            '/api/v2/sitemap' => Http::response(['data' => ['locales_detail' => [
                ['locale' => 'cs', 'modules' => [
                    ['url' => 'https://oi-clean.de/cs/sitemap-pages.xml'],
                ]],
                ['locale' => 'de', 'modules' => [
                    ['url' => 'https://oi-clean.de/de/sitemap-serviceLocation-1.xml'],
                ]],
            ]]]),
            '/api/v2/sitemap/cs/pages' => Http::response(['data' => ['urls' => [
                ['loc' => 'https://panel.example/cs/startseite'],
            ]]]),
            '/api/v2/pages' => Http::response(['data' => [[
                'slug' => 'startseite',
                'translations' => [[
                    'language_code' => 'cz',
                    'name' => 'Domov',
                    'content' => 'Cesky obsah',
                ]],
            ]], 'meta' => ['last_page' => 1]]),
            '/api/v2/sitemap/de/serviceLocation' => Http::response(['data' => ['urls' => [
                ['loc' => 'https://panel.example/de/housekeeping-service-bayreuth'],
                ['loc' => 'https://panel.example/de/praxisreinigung-straubing'],
            ], 'total_pages' => 1]]),
            default => Http::response(['message' => "Unexpected test endpoint {$path}"], 404),
        };
    });

    $this->get('/cs/sitemap-pages.xml')
        ->assertOk()
        ->assertSee('https://oi-clean.de/cs/', false);

    $this->get('/de/sitemap-serviceLocation-1.xml')
        ->assertOk()
        ->assertSee('/de/housekeeping-service-bayreuth', false)
        ->assertSee('/de/praxisreinigung-straubing', false);

    Http::assertSent(fn ($request) => parse_url($request->url(), PHP_URL_PATH) === '/api/v2/sitemap/cs/pages');
    Http::assertNotSent(fn ($request) => parse_url($request->url(), PHP_URL_PATH) === '/api/v2/sitemap/cz/pages');
    Http::assertNotSent(fn ($request) => parse_url($request->url(), PHP_URL_PATH) === '/api/v2/services');
});
