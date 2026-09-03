<?php

use Illuminate\Support\Facades\Http;

it('forwards a sanitized button click with the configured tenant', function () {
    config()->set([
        'services.omr.base' => 'https://omerdogan.de/api',
        'services.omr.api_version' => 2,
        'services.omr.tenant_id' => 'oi_cleande_690e161c3a1dd',
        'services.omr.retry_count' => 0,
    ]);

    Http::fake([
        '*' => Http::response([
            'success' => true,
            'data' => ['button_key' => 'contact_form_submit'],
        ]),
    ]);

    $response = $this->postJson('/api/button-tracking/track', [
        'button_key' => 'contact_form_submit',
        'session_id' => 's_client-tenant_random_timestamp',
        'metadata' => [
            'page' => '/kontakt',
            'url' => 'https://oi-clean.de/kontakt',
            'locale' => 'de',
            'referrer' => 'https://example.com/services',
            'user_agent' => 'Mozilla/5.0',
            'timestamp' => '2026-08-17T12:00:00.000Z',
            'tenant_id' => 'untrusted-client-value',
            'button_name' => 'Kontakt – Nachricht senden',
            'action' => 'submit_contact_form',
            'location' => 'contact_page',
            'form' => 'contact_form',
            'element_id' => 'contact-submit',
            'element_class' => 'submit-button bg-button',
            'element_text' => 'Nachricht senden',
            'element_type' => 'button',
            'click_x' => 120,
            'click_y' => 240,
        ],
        'email' => 'must-not-be-forwarded@example.com',
        'phone' => '+49 123 456',
        'message' => 'must not be forwarded',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('success', true);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://omerdogan.de/api/v2/button-tracking/track?tenant=oi_cleande_690e161c3a1dd'
            && $request->hasHeader('X-Tenant-ID', 'oi_cleande_690e161c3a1dd')
            && $request['button_key'] === 'contact_form_submit'
            && $request['session_id'] === 's_client-tenant_random_timestamp'
            && $request['metadata']['page'] === '/kontakt'
            && $request['metadata']['tenant_id'] === 'oi_cleande_690e161c3a1dd'
            && $request['metadata']['element_id'] === 'contact-submit'
            && $request['metadata']['click_x'] === 120
            && ! isset($request['email'], $request['phone'], $request['message']);
    });
});

it('rejects an invalid button click before calling the upstream API', function () {
    Http::fake();

    $this->postJson('/api/button-tracking/track', [
        'button_key' => 'invalid key',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors([
            'button_key',
            'session_id',
            'metadata',
            'metadata.button_name',
            'metadata.action',
            'metadata.location',
        ]);

    Http::assertNothingSent();
});
