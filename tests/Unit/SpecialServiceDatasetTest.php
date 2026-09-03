<?php

use App\Http\Controllers\LocationShowController;
use App\Http\Controllers\ServiceShowController;
use App\Console\Commands\WarmOmrCatalog;
use Tests\TestCase;

uses(TestCase::class);

function invokePrivate(object $target, string $method, mixed ...$arguments): mixed
{
    $reflection = new ReflectionMethod($target, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke($target, ...$arguments);
}

it('uses only the two explicitly configured legacy category datasets', function () {
    config(['services.omr.location_parent_id' => null]);

    $serviceController = app(ServiceShowController::class);
    $locationController = app(LocationShowController::class);
    $catalogWarmer = app(WarmOmrCatalog::class);

    expect(invokePrivate($serviceController, 'relatedServicesQuery', 'housekeeping-service'))
        ->toBe(['category_slug' => 'reinigungsservice', '_max_pages' => 10])
        ->and(invokePrivate($serviceController, 'relatedServicesQuery', 'praxisreinigung'))
        ->toBe(['parent_id' => 11, '_max_pages' => 10])
        ->and(invokePrivate($locationController, 'serviceCollectionQuery', 'housekeeping-service'))
        ->toBe(['category_slug' => 'reinigungsservice', '_max_pages' => 10])
        ->and(invokePrivate($locationController, 'serviceCollectionQuery', 'praxisreinigung'))
        ->toBe(['parent_id' => 11, '_max_pages' => 10])
        ->and(invokePrivate($locationController, 'serviceCollectionQuery', 'hotelreinigung'))
        ->toBe(['category_slug' => 'hotelreinigung', '_max_pages' => 1])
        ->and(invokePrivate($catalogWarmer, 'categoryQuery', 'housekeeping-service', 10, 100, 0))
        ->toBe([
            'category_slug' => 'reinigungsservice',
            '_max_pages' => 10,
            '_per_page' => 100,
            '_sleep_ms' => 0,
        ])
        ->and(invokePrivate($catalogWarmer, 'categoryQuery', 'praxisreinigung', 10, 100, 0))
        ->toBe([
            'parent_id' => 11,
            '_max_pages' => 10,
            '_per_page' => 100,
            '_sleep_ms' => 0,
        ]);
});

it('renders a special city URL only when both service and city really match', function () {
    $controller = app(LocationShowController::class);
    $services = [
        ['id' => 256, 'slug' => 'housekeeping-service-bayreuth', 'parent_id' => 2, 'city' => 'Bayreuth'],
        ['id' => 257, 'slug' => 'housekeeping-service-berlin', 'parent_id' => 2, 'city' => 'Berlin'],
        ['id' => 1792, 'slug' => 'praxisreinigung-straubing', 'parent_id' => 11, 'city' => 'Straubing'],
    ];

    expect(invokePrivate($controller, 'findServiceByCityAndServiceSlug', $services, 'bayreuth', 'housekeeping-service'))
        ->toMatchArray(['id' => 256])
        ->and(invokePrivate($controller, 'findServiceByCityAndServiceSlug', $services, 'straubing', 'praxisreinigung'))
        ->toMatchArray(['id' => 1792])
        ->and(invokePrivate($controller, 'findServiceByCityAndServiceSlug', $services, 'munchen', 'housekeeping-service'))
        ->toBeNull()
        ->and(invokePrivate($controller, 'findServiceByCityAndServiceSlug', $services, 'bayreuth', 'praxisreinigung'))
        ->toBeNull();
});
