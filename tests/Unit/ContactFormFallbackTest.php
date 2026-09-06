<?php

use App\Http\Controllers\ContactFormController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

it('does not invent a form id when no confirmed form exists', function () {
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

    expect($first)->toBe([])
        ->and($second)->toBe($first)
        ->and(Http::recorded())->toHaveCount(1);
});

it('keeps confirmed legacy stale form records during upstream failure', function () {
    config(['cache.default' => 'array', 'services.omr.main_tenant' => 'form-stale', 'services.omr.tenant_id' => 'form-stale']);
    Cache::flush();
    $forms = [['id' => 42, 'name' => 'Contact', 'fields' => [['name' => 'field_0', 'type' => 'text']]]];
    Cache::put('contact_forms_v2_stale_form-stale_de', $forms, 300);
    Http::fake(['*' => Http::response([], 503)]);
    expect(ContactFormController::getForms('de')->all())->toBe($forms);
    expect(ContactFormController::getForms('de')->all())->toBe($forms);
    Http::assertSentCount(1);
});
