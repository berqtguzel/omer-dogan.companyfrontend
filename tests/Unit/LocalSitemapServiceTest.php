<?php

use App\Services\LocalSitemapService;
use Tests\TestCase;

uses(TestCase::class);

it('builds route based sitemaps without a remote API request', function () {
    config([
        'sitemap.source' => 'local',
        'sitemap.base_url' => 'https://example.test',
        'sitemap.locales' => ['de', 'en'],
        'sitemap.modules' => ['pages'],
    ]);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('X-Sitemap-Source', 'local')
        ->assertSee('https://example.test/de/sitemap-pages.xml', false)
        ->assertSee('https://example.test/en/sitemap-pages.xml', false);

    $this->get('/de/sitemap-pages.xml')
        ->assertOk()
        ->assertHeader('X-Sitemap-Source', 'local')
        ->assertSee('https://example.test/de/kontakt', false)
        ->assertDontSee('reinigungsleistungen', false)
        ->assertSee('https://example.test/de/kontakt', false);
});
