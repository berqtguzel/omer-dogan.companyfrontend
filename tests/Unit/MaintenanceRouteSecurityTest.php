<?php

use App\Services\SeoFilesService;
use Tests\TestCase;

uses(TestCase::class);

it('keeps the SEO sync endpoint closed without a configured secret', function () {
    config(['omr_warmup.webhook_secret' => null]);
    $this->post('/_seo-files/sync')->assertNotFound();
});

it('requires the configured secret before starting an SEO sync', function () {
    config(['omr_warmup.webhook_secret' => 'test-secret']);
    $service = Mockery::mock(SeoFilesService::class);
    $service->shouldReceive('syncAll')->once()->andReturn(['updated' => 0]);
    app()->instance(SeoFilesService::class, $service);

    $this->post('/_seo-files/sync', [], ['X-OMR-Warmup-Secret' => 'wrong'])->assertForbidden();
    $this->post('/_seo-files/sync', [], ['X-OMR-Warmup-Secret' => 'test-secret'])
        ->assertOk()->assertJson(['updated' => 0]);
});

it('does not expose the mail preview in a production environment', function () {
    $this->app->detectEnvironment(fn () => 'production');
    $this->get('http://localhost/_mail-preview/contact')->assertNotFound();
});
