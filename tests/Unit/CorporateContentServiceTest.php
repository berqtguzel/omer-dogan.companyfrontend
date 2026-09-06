<?php

use App\Services\CorporateContentService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

it('reuses the existing tenant locale page cache without new API modules', function () {
    config(['cache.default' => 'array', 'services.omr.tenant_id' => 'corporate-source', 'services.omr.main_tenant' => 'corporate-source']);
    Cache::flush();
    $pages = [['slug' => 'profile', 'name' => 'Confirmed profile']];
    Cache::put('pages_list_v5_corporate-source_de', $pages, 300);
    Http::preventStrayRequests();
    $service = app(CorporateContentService::class);
    expect($service->pages('de'))->toBe($pages);
    Cache::forget('pages_list_v5_corporate-source_de');
    expect($service->pages('de'))->toBe($pages);
    Http::assertNothingSent();
});

it('does not inherit source language fields missing from a partial translation', function () {
    $data = (new \App\Data\HomePageData([[
        'slug' => 'profile', 'name' => 'German', 'content' => 'German body',
        'translations' => [['language_code' => 'tr', 'name' => 'Translated title']],
    ]], 'tr', true, ''))->build([], ['company_pages' => ['profile']]);
    expect($data['companies'][0]['description'])->toBe('')->and($data['companies'][0]['body'])->toBe('');
});
