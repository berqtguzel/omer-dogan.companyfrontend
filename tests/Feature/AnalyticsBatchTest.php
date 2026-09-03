<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('forwards an analytics batch with the site tenant and returns accepted ids', function () {
    config([
        'services.omr.analytics_enabled' => true,
        'services.omr.page_timing_enabled' => true,
        'services.omr.main_tenant' => 'oi_clean_belgiende_6a59d253200ec',
        'services.omr.tenant_id' => 'oi_cleande_690e161c3a1dd',
        'services.omr.base' => 'https://omerdogan.de/api',
        'services.omr.api_version' => 2,
        'services.omr.retry_count' => 0,
    ]);

    Http::fake([
        '*' => Http::response([
            'success' => true,
            'visitor_id' => 3952,
            'session_id' => 'session-test',
        ]),
    ]);

    $batchId = 'batch-'.uniqid();

    $response = $this->postJson('/api/analytics/track', [
        'batch_id' => $batchId,
        'events' => [
            [
                'id' => 'event-1',
                'payload' => [
                    'event_type' => 'page_view',
                    'page_url' => 'https://example.test/de',
                    'private_field' => 'must-not-leave-the-app',
                ],
            ],
        ],
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('accepted_ids.0', 'event-1')
        ->assertJsonPath('visitor_id', 3952)
        ->assertJsonPath('session_id', 'session-test');

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://omerdogan.de/api/v2/analytics/track?tenant=oi_cleande_690e161c3a1dd'
            && $request->hasHeader('X-Tenant-ID', 'oi_cleande_690e161c3a1dd')
            && $request['event_type'] === 'page_view'
            && $request['page_url'] === 'https://example.test/de'
            && ! isset($request['private_field']);
    });
});
