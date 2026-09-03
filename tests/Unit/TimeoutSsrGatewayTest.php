<?php

use App\Inertia\TimeoutSsrGateway;
use Illuminate\Support\Facades\Http;
use Inertia\Ssr\Gateway;
use Tests\TestCase;

uses(TestCase::class);

it('uses the application SSR gateway and returns rendered markup', function () {
    config([
        'inertia.ssr.enabled' => true,
        'inertia.ssr.url' => 'http://127.0.0.1:13714',
        'inertia.ssr.bundle' => base_path('bootstrap/ssr/ssr.js'),
    ]);

    Http::fake([
        'http://127.0.0.1:13714/render' => Http::response([
            'head' => ['<title>Home</title>'],
            'body' => '<div id="app">Rendered</div>',
        ]),
    ]);

    $gateway = app(Gateway::class);
    $response = $gateway->dispatch(['component' => 'Home']);

    expect($gateway)->toBeInstanceOf(TimeoutSsrGateway::class)
        ->and($response?->head)->toBe('<title>Home</title>')
        ->and($response?->body)->toBe('<div id="app">Rendered</div>');

    Http::assertSentCount(1);
});

it('falls back to client rendering when the SSR server is unavailable', function () {
    config([
        'inertia.ssr.enabled' => true,
        'inertia.ssr.url' => 'http://127.0.0.1:13714',
        'inertia.ssr.bundle' => base_path('bootstrap/ssr/ssr.js'),
    ]);

    Http::fake(fn () => throw new RuntimeException('SSR unavailable'));

    expect(app(Gateway::class)->dispatch(['component' => 'Home']))->toBeNull();
});
