<?php

use App\Http\Controllers\LanguagesController;
use Illuminate\Support\Facades\Http;

it('shows only languages enabled in general settings metadata', function () {
    $tenant = 'tenant-languages-'.uniqid();

    config([
        'services.omr.base' => 'https://omerdogan.de/api',
        'services.omr.api_version' => 2,
        'services.omr.retry_count' => 0,
        'services.omr.main_tenant' => $tenant,
        'services.omr.tenant_id' => 'site-tenant-must-not-be-used',
    ]);

    Http::fake([
        'https://omerdogan.de/api/global/settings/languages*' => Http::response([
            'data' => [
                'languages' => [
                    ['locale' => 'de', 'name' => 'Deutsch'],
                    ['locale' => 'en', 'name' => 'English'],
                    ['locale' => 'tr', 'name' => 'Türkçe'],
                ],
                'default' => ['locale' => 'en'],
            ],
        ]),
        'https://omerdogan.de/api/v2/settings/general*' => Http::response([
            'success' => true,
            'data' => [
                '_meta' => [
                    'default_language' => 'en',
                    'available_languages' => ['de'],
                ],
            ],
        ]),
    ]);

    $result = LanguagesController::getLanguages('site-tenant-must-not-be-used', 'de');

    expect($result['languages'])->toBe([
        ['code' => 'de', 'label' => 'Deutsch'],
    ])->and($result['defaultCode'])->toBe('de');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'tenant='.urlencode($tenant)));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'tenant=site-tenant-must-not-be-used'));
});
