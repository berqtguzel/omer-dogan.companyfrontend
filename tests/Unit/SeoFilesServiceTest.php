<?php

use App\Services\SeoFilesService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

test('new SEO files fall back to trusted manifest URLs and sync well-known AI', function () {
    config([
        'cache.default' => 'array',
        'services.omr.base' => 'https://omerdogan.de/api',
        'services.omr.api_version' => 2,
        'services.omr.retry_count' => 0,
    ]);
    Cache::clear();

    $tenant = 'seo-files-'.uniqid();
    config([
        'services.omr.tenant_id' => $tenant,
        'services.omr.tenant_id_fallback' => $tenant,
        'services.omr.main_tenant' => null,
    ]);
    $originalStorage = app()->storagePath();
    $storage = storage_path('framework/testing/'.$tenant);
    app()->useStoragePath($storage);

    $manifest = [
        'data' => [
            'enabled' => true,
            'files' => [
                'identity' => [
                    'url' => "https://omerdogan.de/{$tenant}/identity.json",
                    'mime_type' => 'application/json; charset=UTF-8',
                ],
                'ai' => [
                    'url' => "https://omerdogan.de/{$tenant}/ai.json",
                    'mime_type' => 'application/json; charset=UTF-8',
                ],
                'ai_brand_md' => [
                    'url' => null,
                    'mime_type' => 'text/markdown; charset=UTF-8',
                ],
                'ai_knowledge_md' => [
                    'url' => null,
                    'mime_type' => 'text/markdown; charset=UTF-8',
                ],
                'ai_about_md' => [
                    'url' => null,
                    'mime_type' => 'text/markdown; charset=UTF-8',
                ],
            ],
        ],
    ];

    Http::fake(function ($request) use ($manifest, $tenant) {
        $url = $request->url();
        $path = parse_url($url, PHP_URL_PATH);

        if ($path === '/api/v2/seo-files') {
            return Http::response($manifest);
        }

        if (str_starts_with((string) $path, '/api/v2/seo-files/')) {
            return Http::response(['success' => false, 'message' => 'Invalid SEO file type'], 400);
        }

        if ($url === "https://omerdogan.de/{$tenant}/identity.json") {
            return Http::response('{"name":"Tenant"}', 200, ['Content-Type' => 'application/json']);
        }

        if ($url === "https://omerdogan.de/{$tenant}/ai.json") {
            return Http::response('{"ai":true}', 200, ['Content-Type' => 'application/json']);
        }

        if ($url === "https://omerdogan.de/{$tenant}/ai/brand.md") {
            return Http::response('# Tenant Brand', 200, ['Content-Type' => 'text/markdown']);
        }

        if ($url === "https://omerdogan.de/{$tenant}/ai/knowledge.md") {
            return Http::response('# Tenant Knowledge', 200, ['Content-Type' => 'text/markdown']);
        }

        if ($url === "https://omerdogan.de/{$tenant}/ai/about.md") {
            return Http::response('# About Tenant', 200, ['Content-Type' => 'text/markdown']);
        }

        return Http::response('', 404);
    });

    try {
        $service = app(SeoFilesService::class);
        $identity = $service->sync('identity', $tenant);
        $ai = $service->sync('ai', $tenant);
        $aiBrand = $service->sync('ai_brand_md', $tenant);
        $aiKnowledge = $service->sync('ai_knowledge_md', $tenant);
        $aiAbout = $service->sync('ai_about_md', $tenant);

        expect($identity)->not->toBeNull()
            ->and($identity['content'])->toContain('"name":"Tenant"')
            ->and($ai)->not->toBeNull()
            ->and($ai['content'])->toContain('"ai":true')
            ->and($aiBrand['content'])->toContain('# Tenant Brand')
            ->and($aiKnowledge['content'])->toContain('# Tenant Knowledge')
            ->and($aiAbout['content'])->toContain('# About Tenant')
            ->and(File::exists($storage.'/app/seo-files/public/ai.json'))->toBeTrue()
            ->and(File::exists($storage.'/app/seo-files/public/.well-known/ai.json'))->toBeTrue()
            ->and(File::exists($storage.'/app/seo-files/public/ai/brand.md'))->toBeTrue()
            ->and(File::exists($storage.'/app/seo-files/public/ai/knowledge.md'))->toBeTrue()
            ->and(File::exists($storage.'/app/seo-files/public/ai/about.md'))->toBeTrue();

        $this->get('/identity.json')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json; charset=UTF-8')
            ->assertSee('"name":"Tenant"', false);

        $this->get('/.well-known/ai.json')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json; charset=UTF-8')
            ->assertSee('"ai":true', false);

        $this->get('/ai/brand.md')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
            ->assertSee('# Tenant Brand', false);

        $this->get('/ai/knowledge.md')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
            ->assertSee('# Tenant Knowledge', false);

        $this->get('/ai/about.md')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
            ->assertSee('# About Tenant', false);

        Http::assertSent(fn ($request) => $request->url() === "https://omerdogan.de/{$tenant}/identity.json");
        Http::assertSent(fn ($request) => $request->url() === "https://omerdogan.de/{$tenant}/ai.json");
        Http::assertSent(fn ($request) => $request->url() === "https://omerdogan.de/{$tenant}/ai/brand.md");
        Http::assertSent(fn ($request) => $request->url() === "https://omerdogan.de/{$tenant}/ai/knowledge.md");
        Http::assertSent(fn ($request) => $request->url() === "https://omerdogan.de/{$tenant}/ai/about.md");
    } finally {
        app()->useStoragePath($originalStorage);
        File::deleteDirectory($storage);
    }
});

test('all JSON SEO files discard duplicated custom appendix content', function () {
    config([
        'cache.default' => 'array',
        'services.omr.base' => 'https://omerdogan.de/api',
        'services.omr.api_version' => 2,
        'services.omr.retry_count' => 0,
    ]);
    Cache::clear();

    $tenant = 'seo-schema-'.uniqid();
    config([
        'services.omr.tenant_id' => $tenant,
        'services.omr.tenant_id_fallback' => $tenant,
        'services.omr.main_tenant' => null,
    ]);
    $originalStorage = app()->storagePath();
    $storage = storage_path('framework/testing/'.$tenant);
    app()->useStoragePath($storage);

    $documents = [
        'manifest' => '{"name":"Tenant App"}',
        'schema' => '[{"@context":"https://schema.org","@type":"Organization"}]',
        'identity' => '{"name":"Tenant"}',
        'knowledge' => '{"topics":[]}',
        'ai' => '{"version":"1.0","brand":"https://oi-clean.de/brand.txt","knowledge":"https://oi-clean.de/knowledge.json","markdown":{"brand":"https://oi-clean.de/ai/brand.md","knowledge":"https://oi-clean.de/ai/knowledge.md","about":"https://oi-clean.de/ai/about.md"}}',
    ];

    Http::fake(function ($request) use ($documents) {
        $path = parse_url($request->url(), PHP_URL_PATH);

        if ($path === '/api/v2/seo-files') {
            return Http::response(['data' => [
                'enabled' => true,
                'files' => array_fill_keys(array_keys($documents), []),
            ]]);
        }

        $type = basename((string) $path);
        if (str_starts_with((string) $path, '/api/v2/seo-files/') && isset($documents[$type])) {
            $duplicated = $documents[$type]."\n\n<!-- custom appendix -->\n\n".$documents[$type];

            return Http::response($duplicated, 200, ['Content-Type' => 'application/json; charset=UTF-8']);
        }

        return Http::response('', 404);
    });

    try {
        $service = app(SeoFilesService::class);
        $results = [];

        foreach ($documents as $type => $expectedDocument) {
            $results[$type] = $service->sync($type, $tenant);
            $decoded = json_decode($results[$type]['content'], true, 512, JSON_THROW_ON_ERROR);
            $expected = json_decode($expectedDocument, true, 512, JSON_THROW_ON_ERROR);

            expect($results[$type])->not->toBeNull()
                ->and($decoded)->toBe($expected)
                ->and($results[$type]['content'])->not->toContain('custom appendix');
        }

        $schema = $results['schema'];
        $decodedSchema = json_decode($schema['content'], true, 512, JSON_THROW_ON_ERROR);

        expect($decodedSchema)->toHaveCount(1)
            ->and($decodedSchema[0]['@type'])->toBe('Organization')
            ->and(substr_count($schema['content'], 'schema.org'))->toBe(1);

        $this->get('/schema.json')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/ld+json; charset=UTF-8')
            ->assertDontSee('custom appendix', false);

        $this->get('/.well-known/ai.json')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json; charset=UTF-8')
            ->assertDontSee('custom appendix', false)
            ->assertSee('/ai/brand.md', false)
            ->assertSee('/ai/knowledge.md', false)
            ->assertSee('/ai/about.md', false);
    } finally {
        app()->useStoragePath($originalStorage);
        File::deleteDirectory($storage);
    }
});
