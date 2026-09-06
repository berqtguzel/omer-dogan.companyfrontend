<?php

use App\Support\Omr\OmrCachedClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config([
        'cache.default' => 'array',
        'services.omr.base' => 'https://api.audit.test',
        'services.omr.tenant_id' => 'audit-default',
        'services.omr.main_tenant' => 'audit-default',
        'services.omr.retry_count' => 1,
    ]);
    Cache::flush();
});

function auditCacheKey(string $namespace, string $path, string $tenant, array $query = []): string
{
    $query['tenant'] = $query['tenant'] ?? $tenant;
    ksort($query);
    $method = new ReflectionMethod(OmrCachedClient::class, 'cacheKey');

    return $method->invoke(null, $namespace, $path, $tenant, $query);
}

function auditSnapshotPath(string $cacheKey): string
{
    $method = new ReflectionMethod(OmrCachedClient::class, 'snapshotPath');

    return $method->invoke(null, $cacheKey);
}

it('caches successful and successful empty responses without duplicate requests', function () {
    Http::fake([
        'api.audit.test/*/full*' => Http::response(['data' => [['id' => 1]]]),
        'api.audit.test/*/empty*' => Http::response(['data' => []]),
    ]);

    $tenant = 'tenant-a-'.uniqid();
    expect(OmrCachedClient::get('audit', 'full', [], $tenant)['ok'])->toBeTrue();
    expect(OmrCachedClient::get('audit', 'full', [], $tenant)['json']['data'])->toHaveCount(1);
    expect(OmrCachedClient::get('audit', 'empty', [], $tenant)['json']['data'])->toBe([]);
    expect(OmrCachedClient::get('audit', 'empty', [], $tenant)['ok'])->toBeTrue();
    Http::assertSentCount(2);
});

it('refreshes from the API after fresh cache expires instead of promoting the snapshot', function () {
    Http::fakeSequence()->push(['data' => [['id' => 1]]])->push(['data' => [['id' => 2]]]);
    $tenant = 'tenant-refresh-'.uniqid();
    $key = auditCacheKey('refresh', 'pages', $tenant);

    OmrCachedClient::get('refresh', 'pages', [], $tenant);
    Cache::forget($key);
    Cache::forget($key.'_stale');
    $refreshed = OmrCachedClient::get('refresh', 'pages', [], $tenant);

    expect($refreshed['json']['data'][0]['id'])->toBe(2);
    Http::assertSentCount(2);
});

it('uses stale data on a server error and returns a safe failure without stale data', function () {
    Http::fakeSequence()->push(['data' => [['id' => 8]]])->push('down', 503);
    $tenant = 'tenant-stale-'.uniqid();
    $key = auditCacheKey('stale', 'pages', $tenant);
    OmrCachedClient::get('stale', 'pages', [], $tenant);
    Cache::forget($key);
    File::delete(auditSnapshotPath($key));

    $stale = OmrCachedClient::get('stale', 'pages', [], $tenant);
    expect($stale['ok'])->toBeTrue()->and($stale['json']['data'][0]['id'])->toBe(8);

    Cache::flush();
    Http::fake(['*' => Http::response('down', 503)]);
    $failed = OmrCachedClient::get('missing', 'pages', [], 'tenant-missing-'.uniqid());
    expect($failed['ok'])->toBeFalse()
        ->and($failed['json'])->toBeNull()
        ->and($failed['body'])->toBe('');
});

it('caches 404 failures and applies a tenant cooldown after 429', function () {
    Http::fakeSequence()->push([], 404)->push([], 429);
    $tenant404 = 'tenant-404-'.uniqid();
    $first404 = OmrCachedClient::get('not-found', 'pages', [], $tenant404);
    $second404 = OmrCachedClient::get('not-found', 'pages', [], $tenant404);
    expect($first404['status'])->toBe(404)->and($second404['status'])->toBe(404);
    Http::assertSentCount(1);

    $tenant429 = 'tenant-429-'.uniqid();
    $limited = OmrCachedClient::get('limited', 'pages', [], $tenant429);
    $duringCooldown = OmrCachedClient::get('limited-next', 'menus', [], $tenant429);
    expect($limited['status'])->toBe(429)->and($duringCooldown['ok'])->toBeFalse();
    Http::assertSentCount(2);
});

it('ignores a corrupt snapshot and isolates cache entries by tenant and locale', function () {
    $corruptTenant = 'tenant-corrupt-'.uniqid();
    $corruptKey = auditCacheKey('corrupt', 'pages', $corruptTenant, ['lang' => 'de']);
    $corruptPath = auditSnapshotPath($corruptKey);
    File::ensureDirectoryExists(dirname($corruptPath));
    File::put($corruptPath, '{invalid-json');

    Http::fake(['*' => Http::response(['data' => []])]);
    expect(OmrCachedClient::get('corrupt', 'pages', ['lang' => 'de'], $corruptTenant)['ok'])->toBeTrue();
    $isolation = uniqid();
    OmrCachedClient::get('isolated', 'pages', ['lang' => 'de'], 'tenant-one-'.$isolation);
    OmrCachedClient::get('isolated', 'pages', ['lang' => 'en'], 'tenant-one-'.$isolation);
    OmrCachedClient::get('isolated', 'pages', ['lang' => 'de'], 'tenant-two-'.$isolation);

    Http::assertSentCount(4);
    File::delete($corruptPath);
});
