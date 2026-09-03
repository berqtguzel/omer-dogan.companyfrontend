<?php

use App\Support\MediaUrl;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

it('temporarily caches failed media lookups', function () {
    config([
        'cache.default' => 'array',
        'services.omr.base' => 'https://omerdogan.de/api',
        'services.omr.api_version' => 2,
        'services.omr.tenant_id' => 'media-cache-test',
        'services.omr.tenant_id_fallback' => 'media-cache-test',
        'services.omr.main_tenant' => null,
    ]);

    Cache::clear();
    Http::fake([
        'https://omerdogan.de/api/v2/media/999*' => Http::response([], 503),
    ]);

    expect(MediaUrl::resolve(999))->toBeNull()
        ->and(MediaUrl::resolve(999))->toBeNull();

    Http::assertSentCount(1);
});
