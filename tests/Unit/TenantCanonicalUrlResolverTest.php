<?php

use App\Services\TenantCanonicalUrlResolver;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config([
        'app.env' => 'production',
        'services.omr.tenant_id' => 'tenant_a',
        'services.omr.tenant_id_fallback' => '',
        'seo.canonical_base_url' => null,
        'seo.tenant_domains' => [
            'tenant_a' => [
                'canonical_url' => 'https://oi-clean.de',
                'aliases' => ['www.oi-clean.de'],
            ],
            'tenant_b' => [
                'canonical_url' => 'https://oi-clean-hessen.de',
                'aliases' => ['www.oi-clean-hessen.de'],
            ],
            'tenant_c' => [
                'canonical_url' => 'https://hotelreinigung-hamburg.de',
                'aliases' => ['www.hotelreinigung-hamburg.de'],
            ],
        ],
    ]);
});

it('keeps canonical URLs isolated across three tenants', function () {
    $resolver = app(TenantCanonicalUrlResolver::class);

    expect($resolver->url('/de/berlin', 'de', 'tenant_a'))
        ->toBe('https://oi-clean.de/de/berlin')
        ->and($resolver->url('/de/frankfurt', 'de', 'tenant_b'))
        ->toBe('https://oi-clean-hessen.de/de/frankfurt')
        ->not->toContain('oi-clean.de/de')
        ->and($resolver->url('/de/hotelreinigung', 'de', 'tenant_c'))
        ->toBe('https://hotelreinigung-hamburg.de/de/hotelreinigung');
});

it('uses primary config rather than an alias or request host', function () {
    $resolver = app(TenantCanonicalUrlResolver::class);

    expect($resolver->url('https://www.oi-clean-hessen.de/en/frankfurt?utm_source=test#map', 'en', 'tenant_b'))
        ->toBe('https://oi-clean-hessen.de/en/frankfurt');
});

it('normalizes public paths query strings slashes and homepage aliases', function () {
    $resolver = app(TenantCanonicalUrlResolver::class);

    expect($resolver->url('/public//de/hotelreinigung?utm_source=google', 'de', 'tenant_a'))
        ->toBe('https://oi-clean.de/de/hotelreinigung')
        ->and($resolver->url('/de/startseite', 'de', 'tenant_a'))
        ->toBe('https://oi-clean.de/de/')
        ->and($resolver->url('/', 'tr', 'tenant_a'))
        ->toBe('https://oi-clean.de/tr/');
});

it('omits canonical when a tenant has no configured primary domain', function () {
    $resolver = app(TenantCanonicalUrlResolver::class);

    expect($resolver->baseUrl('tenant_missing'))->toBeNull()
        ->and($resolver->url('/de/berlin', 'de', 'tenant_missing'))->toBeNull();
});

it('separates cache scopes by tenant and canonical domain', function () {
    $resolver = app(TenantCanonicalUrlResolver::class);

    expect($resolver->cacheScope('tenant_a'))
        ->not->toBe($resolver->cacheScope('tenant_b'))
        ->and($resolver->cacheScope('tenant_b'))
        ->not->toBe($resolver->cacheScope('tenant_c'));
});
