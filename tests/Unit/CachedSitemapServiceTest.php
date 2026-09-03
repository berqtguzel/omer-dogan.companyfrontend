<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config([
        'cache.default' => 'array',
        'services.omr.main_tenant' => 'cached-sitemap-tenant',
        'services.omr.tenant_id' => 'cached-sitemap-tenant',
        'seo.tenant_domains' => [
            'cached-sitemap-tenant' => ['canonical_url' => 'https://example.test'],
        ],
        'sitemap.source' => 'cache',
        'sitemap.modules' => ['pages'],
    ]);

    Cache::setDefaultDriver('array');
    Cache::store('array')->flush();
    Http::preventStrayRequests();
});

it('builds sitemap only from cached records with real translations', function () {
    $pages = [[
        'id' => 16,
        'slug' => 'uber-uns',
        'updated_at' => '2026-09-03T10:00:00Z',
        'translations' => [
            [
                'language_code' => 'de',
                'name' => 'Über uns',
                'content' => '<p>Deutscher Inhalt</p>',
            ],
            [
                'language_code' => 'en',
                'name' => 'About us',
                'content' => '<p>English content</p>',
            ],
            [
                'language_code' => 'tr',
                'name' => 'Hakkımızda',
                'content' => '',
            ],
        ],
    ]];

    Cache::forever('pages_list_v5_cached-sitemap-tenant_de', $pages);

    $service = app(App\Services\CachedSitemapService::class);
    $root = $service->index();

    expect($root['source'])->toBe('cache')
        ->and($root['xml'])
        ->toContain('https://example.test/de/sitemap-pages.xml')
        ->toContain('https://example.test/en/sitemap-pages.xml')
        ->not->toContain('https://example.test/tr/sitemap-pages.xml');

    $english = $service->module('en', 'pages');

    expect($english['count'])->toBe(1)
        ->and($english['xml'])
        ->toContain('https://example.test/en/uber-uns')
        ->toContain('hreflang="de"')
        ->toContain('hreflang="en"')
        ->not->toContain('hreflang="tr"');

    expect(fn () => $service->module('tr', 'pages'))
        ->toThrow(App\Exceptions\EmptySitemapException::class);

    Http::assertNothingSent();
});

it('serves a cached locale sitemap through HTTP without an API request', function () {
    Cache::forever('pages_list_v5_cached-sitemap-tenant_de', [[
        'slug' => 'uber-uns',
        'translations' => [[
            'language_code' => 'en',
            'name' => 'About us',
            'content' => '<p>English content</p>',
        ]],
    ]]);

    $this->get('/en/sitemap-pages.xml')
        ->assertOk()
        ->assertHeader('X-Sitemap-Source', 'cache')
        ->assertSee('https://example.test/en/uber-uns', false);

    Http::assertNothingSent();
});
