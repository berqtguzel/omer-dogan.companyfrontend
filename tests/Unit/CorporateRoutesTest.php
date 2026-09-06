<?php

use App\Support\CorporateRoutes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

it('maps configured pages to stable catalog paths and only advertises usable translations', function () {
    config(['corporate_home.company_pages' => ['confirmed-company']]);
    $page = ['slug' => 'confirmed-company', 'translations' => [
        ['language_code' => 'en', 'slug' => 'english-name', 'name' => 'Company', 'content' => 'Confirmed description'],
        ['language_code' => 'tr', 'name' => 'Title only', 'content' => ''],
    ]];
    expect(CorporateRoutes::alternates($page))->toBe(['en' => '/en/unternehmen/confirmed-company']);
    expect(CorporateRoutes::pagePath(['slug' => 'other', 'translations' => [
        ['language_code' => 'en', 'slug' => 'translated'],
    ]], 'en'))->toBe('/translated');
});

it('maps cached sitemap records to the same catalog URL without any HTTP calls', function () {
    config([
        'cache.default' => 'array', 'services.omr.tenant_id' => 'catalog-seo', 'services.omr.main_tenant' => 'catalog-seo',
        'seo.tenant_domains' => ['catalog-seo' => ['canonical_url' => 'https://example.test']],
        'corporate_home.company_pages' => ['confirmed-company'],
    ]);
    Cache::flush();
    Cache::forever('pages_list_v5_catalog-seo_de', [[
        'slug' => 'confirmed-company', 'translations' => [
            ['language_code' => 'en', 'name' => 'Company', 'content' => '<p>Confirmed description</p>'],
        ],
    ]]);
    Http::preventStrayRequests();
    $xml = app(App\Services\CachedSitemapService::class)->module('en', 'pages')['xml'];
    expect($xml)->toContain('https://example.test/en/unternehmen/confirmed-company');
    Http::assertNothingSent();
});
