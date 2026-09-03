<?php

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

function sitemapIndexFixture(string $child): string
{
    return '<?xml version="1.0" encoding="UTF-8"?>'
        .'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
        .'<sitemap><loc>'.$child.'</loc></sitemap></sitemapindex>';
}

function urlsetFixture(string $page): string
{
    return '<?xml version="1.0" encoding="UTF-8"?>'
        .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
        .'<url><loc>'.$page.'</loc></url></urlset>';
}

it('passes when sitemap XML page status and canonical are valid', function () {
    Http::fake([
        'https://tenant.test/sitemap.xml' => Http::response(
            sitemapIndexFixture('https://tenant.test/de/sitemap-pages.xml'),
            200,
            ['Content-Type' => 'application/xml']
        ),
        'https://tenant.test/de/sitemap-pages.xml' => Http::response(
            urlsetFixture('https://tenant.test/de/berlin'),
            200,
            ['Content-Type' => 'application/xml']
        ),
        'https://tenant.test/de/berlin' => Http::response(
            '<!doctype html><html><head><link rel="canonical" href="https://tenant.test/de/berlin"></head><body></body></html>',
            200,
            ['Content-Type' => 'text/html']
        ),
    ]);

    $this->artisan('sitemap:validate', ['--base-url' => 'https://tenant.test'])
        ->expectsOutputToContain('PASS:')
        ->assertExitCode(0);
});

it('fails when a URL listed in a sitemap returns 404', function () {
    Http::fake([
        'https://tenant.test/sitemap.xml' => Http::response(
            sitemapIndexFixture('https://tenant.test/de/sitemap-pages.xml'),
            200
        ),
        'https://tenant.test/de/sitemap-pages.xml' => Http::response(
            urlsetFixture('https://tenant.test/de/missing'),
            200
        ),
        'https://tenant.test/de/missing' => Http::response('', 404),
    ]);

    $this->artisan('sitemap:validate', ['--base-url' => 'https://tenant.test'])
        ->expectsOutputToContain('Validation failed')
        ->expectsOutputToContain('PAGE_HTTP')
        ->assertExitCode(1);
});
