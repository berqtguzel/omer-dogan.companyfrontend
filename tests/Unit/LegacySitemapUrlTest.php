<?php

use App\Services\SitemapLiveService;
use App\Services\SitemapWarmService;
use Tests\TestCase;

uses(TestCase::class);

it('rewrites duplicated service URLs before publishing them in either sitemap path', function (string $serviceClass) {
    config([
        'app.env' => 'production',
        'services.omr.tenant_id' => 'legacy-sitemap-tenant',
        'seo.tenant_domains' => [
            'legacy-sitemap-tenant' => ['canonical_url' => 'https://oi-clean-teams.de'],
        ],
    ]);

    $service = app($serviceClass);
    $method = new ReflectionMethod($service, 'productionUrl');
    $url = $method->invoke(
        $service,
        'https://panel.example/de/polsterreinigung-in-leverkusen-polsterreinigung-firma-in-leverkusen/',
        'de'
    );

    expect($url)->toBe('https://oi-clean-teams.de/de/polsterreinigung-in-leverkusen');
})->with([
    SitemapLiveService::class,
    SitemapWarmService::class,
]);
