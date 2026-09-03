<?php

use App\Services\MediaMirrorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

it('replaces remote media URLs with a same-origin proxy URL', function () {
    Storage::fake('local');
    Storage::fake('public');

    config([
        'media_mirror.enabled' => true,
        'media_mirror.remote_base' => 'https://omerdogan.de',
        'media_mirror.remote_hosts' => ['omerdogan.de', 'oi-clean.test'],
        'media_mirror.proxy_path' => 'media-proxy',
        'media_mirror.download_on_request' => true,
    ]);

    app()->instance('request', Request::create('https://oi-clean.test/de', 'GET'));

    $mirror = new MediaMirrorService;
    $source = 'https://omerdogan.de/storage/tenant/media/general/logo.webp';
    $proxyUrl = $mirror->mirrorIfUrl($source);
    $token = basename((string) parse_url($proxyUrl, PHP_URL_PATH));

    expect($proxyUrl)
        ->toStartWith('https://oi-clean.test/media-proxy/')
        ->not->toContain('omerdogan.de')
        ->and($mirror->proxySourceUrl($token, [
            'format' => 'webp',
            'w' => '260',
            'q' => '82',
            'not_allowed' => 'ignored',
        ]))->toBe($source.'?format=webp&w=260&q=82')
        ->and($mirror->mirrorIfUrl('https://oi-clean.test/storage/logo.webp'))
        ->toBe('https://oi-clean.test/storage/logo.webp');
});

it('downloads and serves proxied media from the local domain', function () {
    Storage::fake('local');
    Storage::fake('public');

    config([
        'media_mirror.enabled' => true,
        'media_mirror.remote_base' => 'https://omerdogan.de',
        'media_mirror.remote_hosts' => ['omerdogan.de'],
        'media_mirror.proxy_path' => 'media-proxy',
        'media_mirror.download_on_request' => true,
        'media_mirror.variants' => [],
    ]);

    app()->instance('request', Request::create('https://oi-clean.test/de', 'GET'));

    $source = 'https://omerdogan.de/storage/tenant/media/general/proxy-test.webp';
    $proxyUrl = app(MediaMirrorService::class)->proxyUrl($source);

    Http::fake([
        $source => Http::response('fake-webp-content', 200, [
            'Content-Type' => 'image/webp',
            'Content-Length' => '17',
        ]),
    ]);

    $this->get((string) parse_url($proxyUrl, PHP_URL_PATH))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/webp')
        ->assertContent('fake-webp-content');
});

it('always allows the configured remote media base host for tenant logos', function () {
    config([
        'media_mirror.enabled' => true,
        'media_mirror.remote_base' => 'https://omerdogan.de',
        'media_mirror.remote_hosts' => ['oi-clean.test'],
    ]);

    $source = 'https://omerdogan.de/storage/tenant/media/general/dark-logo.webp';
    $token = rtrim(strtr(base64_encode($source), '+/', '-_'), '=');

    expect(app(MediaMirrorService::class)->proxySourceUrl($token))->toBe($source);
});

it('does not retry a failed proxy download during the failure cooldown', function () {
    Storage::fake('public');

    config([
        'cache.default' => 'array',
        'media_mirror.enabled' => true,
        'media_mirror.remote_base' => 'https://omerdogan.de',
        'media_mirror.remote_hosts' => ['omerdogan.de'],
        'media_mirror.proxy_path' => 'media-proxy',
        'media_mirror.download_on_request' => true,
    ]);

    Cache::clear();
    app()->instance('request', Request::create('https://oi-clean.test/de', 'GET'));

    $source = 'https://omerdogan.de/storage/tenant/media/general/unavailable.webp';
    Http::fake([$source => Http::response('', 503)]);

    $mirror = app(MediaMirrorService::class);

    expect($mirror->syncUrl($source))->toBeNull()
        ->and($mirror->syncUrl($source))->toBeNull();

    Http::assertSentCount(1);
});

it('does not download remote media while serving browser requests by default', function () {
    Storage::fake('public');

    config([
        'media_mirror.enabled' => true,
        'media_mirror.download_on_request' => false,
        'media_mirror.remote_base' => 'https://omerdogan.de',
        'media_mirror.remote_hosts' => ['omerdogan.de'],
        'media_mirror.proxy_path' => 'media-proxy',
    ]);

    app()->instance('request', Request::create('https://oi-clean.test/de', 'GET'));

    $source = 'https://omerdogan.de/storage/tenant/media/general/browser.webp';
    $token = rtrim(strtr(base64_encode($source), '+/', '-_'), '=');

    Http::fake();

    expect(app(MediaMirrorService::class)->mirrorIfUrl($source))->toBe($source);

    $this->get('/media-proxy/'.$token)->assertRedirect($source);

    Http::assertNotSent(fn ($request) => $request->url() === $source);
});

it('serves the newest cached image family when an upstream filename version changes', function () {
    Storage::fake('local');
    Storage::fake('public');

    config([
        'media_mirror.enabled' => true,
        'media_mirror.download_on_request' => false,
        'media_mirror.remote_base' => 'https://omerdogan.de',
        'media_mirror.remote_hosts' => ['omerdogan.de'],
        'media_mirror.manifest_file' => 'media-mirror/manifest.json',
    ]);

    $cachedPath = 'media-cache/aa/bb/about-us.webp';
    $oldSource = 'https://omerdogan.de/storage/old/media/general/about-us-1600x450-centered_11111111.webp';
    $newSource = 'https://omerdogan.de/storage/new/media/general/about-us-1600x450-centered_22222222.webp';

    Storage::disk('public')->put($cachedPath, 'cached-image');
    Storage::disk('local')->put('media-mirror/manifest.json', json_encode([
        $oldSource => [
            'path' => $cachedPath,
            'synced_at' => '2026-09-01T12:00:00+00:00',
        ],
    ]));

    expect((new MediaMirrorService)->mirrorIfUrl($newSource))
        ->toBe('/media-cache/aa/bb/about-us.webp');
});
