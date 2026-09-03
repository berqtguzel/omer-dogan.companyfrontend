<?php

use App\Http\Controllers\ContactFormController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

it('keeps the contact form visible and briefly caches fallback fields when the API fails', function () {
    $tenant = 'contact-fallback-'.uniqid();
    config([
        'cache.default' => 'array',
        'services.omr.main_tenant' => $tenant,
        'services.omr.tenant_id' => $tenant,
        'services.omr.api_version' => 2,
    ]);
    Cache::flush();
    Http::fake([ '*' => Http::response(['message' => 'unavailable'], 503) ]);

    $first = ContactFormController::getForms('de')->values()->all();
    $second = ContactFormController::getForms('de')->values()->all();

    expect($first)->toHaveCount(1)
        ->and($first[0]['id'])->toBe(1)
        ->and(collect($first[0]['fields'])->pluck('name')->all())
        ->toBe(['field_0', 'field_1', 'field_2', 'field_3'])
        ->and($second)->toBe($first)
        ->and(Http::recorded())->toHaveCount(1);
});
