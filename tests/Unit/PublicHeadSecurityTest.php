<?php

use Tests\TestCase;

uses(TestCase::class);

it('renders crawl directives and social metadata in the initial HTML', function () {
    config(['corporate_home.content_ready' => false]);

    $response = $this->get('/de/kontakt')->assertOk();
    $html = $response->getContent();

    expect($html)->toContain('<title inertia="title">')
        ->toContain('name="description"')
        ->toContain('name="robots" content="noindex, follow"')
        ->toContain('property="og:title"')
        ->toContain('name="twitter:card"')
        ->not->toContain('rel="canonical"');
});

it('adds baseline security headers to public responses', function () {
    $this->get('/')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
        ->assertHeader('Content-Security-Policy', "base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'");
});

it('renders trusted canonical alternates and real-data schemas in initial HTML', function () {
    $html = view('app', ['page' => [
        'component' => 'kontakt/index',
        'props' => [
            'locale' => 'en',
            'corporateReady' => true,
            'siteShell' => ['name' => 'Example Group', 'description' => 'Group description', 'logo' => '', 'email' => 'office@example.test', 'phone' => ''],
            'languages' => [['code' => 'de'], ['code' => 'en'], ['code' => 'tr']],
            'tenantSeo' => ['canonicalBaseUrl' => 'https://group.example', 'canonicalUrl' => 'https://group.example/en/kontakt'],
        ],
    ]])->render();

    expect($html)->toContain('rel="canonical" href="https://group.example/en/kontakt"')
        ->toContain('hreflang="de" href="https://group.example/de/kontakt"')
        ->toContain('hreflang="x-default" href="https://group.example/de/kontakt"')
        ->toContain('"@type":"Organization"')
        ->toContain('"@type":"WebSite"')
        ->toContain('"@type":"ContactPage"')
        ->not->toContain('localhost');
});
