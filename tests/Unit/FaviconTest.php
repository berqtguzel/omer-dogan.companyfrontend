<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

it('redirects favicon.ico to the cached API favicon through the local media proxy', function () {
    $tenant = 'favicon-'.uniqid();

    config([
        'cache.default' => 'array',
        'services.omr.base' => 'https://omerdogan.de/api',
        'services.omr.api_version' => 2,
        'services.omr.retry_count' => 0,
        'services.omr.tenant_id' => $tenant,
        'services.omr.tenant_id_fallback' => $tenant,
        'services.omr.main_tenant' => null,
        'services.omr.default_locale' => 'de',
        'seo.canonical_base_url' => 'https://tenant.example',
        "seo.tenant_domains.{$tenant}" => 'https://tenant.example',
        'media_mirror.enabled' => true,
        'media_mirror.remote_hosts' => ['omerdogan.de'],
    ]);
    Cache::clear();

    Http::fake(function ($request) use ($tenant) {
        $path = parse_url($request->url(), PHP_URL_PATH);

        return match ($path) {
            '/api/v2/settings' => Http::response(['data' => [
                ['key' => 'site_favicon', 'value' => "https://omerdogan.de/storage/{$tenant}/media/favicon.png"],
            ]]),
            '/api/v2/settings/contact' => Http::response(['data' => []]),
            default => Http::response([], 404),
        };
    });

    $first = $this->get('/favicon.ico')
        ->assertRedirect();

    expect($first->headers->get('Location'))->toContain('/media-proxy/')
        ->and($first->headers->get('Cache-Control'))->toContain('no-store');

    $apiRequests = Http::recorded()->count();

    $this->get('/favicon.ico')->assertRedirect($first->headers->get('Location'));

    expect(Http::recorded()->count())->toBe($apiRequests);
});

it('downloads the API favicon once while preserving its real format', function () {
    $tenant = 'favicon-sync-'.uniqid();
    $baseTarget = storage_path("framework/testing/{$tenant}");
    $target = $baseTarget.'.webp';
    $metadata = storage_path("framework/testing/{$tenant}.json");
    $webp = 'RIFF'.pack('V', 12).'WEBPtest-payload';

    config([
        'cache.default' => 'array',
        'services.omr.base' => 'https://omerdogan.de/api',
        'services.omr.api_version' => 2,
        'services.omr.retry_count' => 0,
        'services.omr.tenant_id' => $tenant,
        'services.omr.tenant_id_fallback' => $tenant,
        'services.omr.main_tenant' => null,
        'services.omr.default_locale' => 'de',
        'media_mirror.disk' => 'public',
        'media_mirror.cache_dir' => 'media-cache',
        'favicon.public_base_path' => $baseTarget,
        'favicon.metadata_path' => $metadata,
    ]);
    Cache::clear();
    Http::fake([
        '*/api/v2/settings*' => Http::response(['data' => [
            ['key' => 'site_favicon', 'value' => 'https://omerdogan.de/storage/favicon.webp'],
        ]]),
        '*/api/v2/settings/contact*' => Http::response(['data' => []]),
        'https://omerdogan.de/storage/favicon.webp' => Http::response($webp, 200, [
            'Content-Type' => 'image/webp',
        ]),
    ]);

    $mirror = $this->mock(\App\Services\MediaMirrorService::class);
    $mirror->shouldReceive('syncUrl')
        ->once()
        ->with('https://omerdogan.de/storage/favicon.webp', false, 'default')
        ->andReturn(null);

    try {
        $this->artisan('favicon:sync')
            ->expectsOutput("Tenant favicon written to public/{$tenant}.webp.")
            ->assertSuccessful();

        expect(file_get_contents($target))->toBe($webp);

        $this->artisan('favicon:sync')
            ->expectsOutput('Tenant favicon is already up to date.')
            ->assertSuccessful();
    } finally {
        if (is_file($target)) {
            unlink($target);
        }
        if (is_file($metadata)) {
            unlink($metadata);
        }
    }
});
