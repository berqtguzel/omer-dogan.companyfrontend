<?php

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

it('does not expose the previous tenant content before corporate mappings are ready', function () {
    config(['corporate_home.content_ready' => false, 'corporate_home.preview_content' => false]);
    Http::preventStrayRequests();

    $home = $this->get('/')->assertOk();
    preg_match('/data-page="([^"]+)"/', $home->getContent(), $matches);
    $props = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true)['props'];

    expect($props['corporateReady'])->toBeFalse()
        ->and($props['siteShell']['name'])->toBe('Ömer Dogan Company GmbH')
        ->and($props['siteShell']['header'])->toBe([])
        ->and($props['home']['companies'])->toBe([])
        ->and($props['home']['projects'])->toBe([])
        ->and($props['home']['metrics'])->toBe([]);
    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /');
    $this->get('/sitemap.xml')->assertStatus(503);
    $this->get('/identity.json')->assertNotFound();
    Http::assertNothingSent();
});

it('keeps legacy cleaning entry points out of the unconfigured corporate frontend', function () {
    config(['corporate_home.content_ready' => false, 'corporate_home.preview_content' => false]);
    Http::preventStrayRequests();

    $this->get('/de/reinigungsleistungen?source=legacy')
        ->assertRedirect('/de/geschaeftsbereiche?source=legacy')
        ->assertStatus(301);
    $this->get('/de/gebaudereinigung')->assertNotFound();
    $this->get('/de/blog')->assertOk();

    Http::assertNothingSent();
});
