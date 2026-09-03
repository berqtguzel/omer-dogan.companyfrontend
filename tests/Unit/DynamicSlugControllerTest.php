<?php

use App\Http\Controllers\DynamicSlugController;
use App\Http\Controllers\StaticPageController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

function invokeDynamicSlugMethod(string $method, mixed ...$arguments): mixed
{
    $controller = new DynamicSlugController();
    $reflection = new ReflectionMethod($controller, $method);

    return $reflection->invoke($controller, ...$arguments);
}

test('recognizes stewarding spulkuchenreinigung as a root service', function () {
    $prefixes = invokeDynamicSlugMethod('staticServicePrefixes');

    expect($prefixes)
        ->toHaveKey('stewarding-service-spulkuchenreinigung')
        ->toHaveKey('abriss-abrissarbeit-abbrucharbeit');
});

test('splits stewarding spulkuchenreinigung city urls at the longest service prefix', function () {
    $prefixes = invokeDynamicSlugMethod('staticServicePrefixes');
    $result = invokeDynamicSlugMethod(
        'splitServiceCityByPrefixes',
        'stewarding-service-spulkuchenreinigung-frankfurt-am-main',
        $prefixes
    );

    expect($result)->toBe([
        'serviceSlug' => 'stewarding-service-spulkuchenreinigung',
        'citySlug' => 'frankfurt-am-main',
    ]);
});

test('routes apartment hotel city URLs without depending on the dynamic route cache', function () {
    $prefixes = invokeDynamicSlugMethod('staticServicePrefixes');

    foreach (['suhl', 'trier', 'ulm', 'weiden-der-oberpfalz'] as $city) {
        expect(invokeDynamicSlugMethod(
            'splitServiceCityByPrefixes',
            "apartment-hotelreinigung-{$city}",
            $prefixes
        ))->toBe([
            'serviceSlug' => 'apartment-hotelreinigung',
            'citySlug' => $city,
        ]);
    }
});

test('renders a localized static page when the dynamic route index cached only services', function () {
    config([
        'cache.default' => 'array',
        'services.omr.tenant_id' => 'tenant-it-pages',
        'services.omr.tenant_id_fallback' => 'tenant-it-pages',
        'services.omr.main_tenant' => 'tenant-it-pages',
    ]);
    Cache::clear();
    Http::fake();

    Cache::put('dynamic_route_index_v11_tenant-it-pages_it', [
        'pages' => [],
        'services' => ['hotelreinigung' => ['slug' => 'hotelreinigung']],
        'serviceCities' => [],
        'servicePrefixes' => ['hotelreinigung' => true],
    ], now()->addMinutes(2));

    Cache::put('pages_list_v5_tenant-it-pages_it', [[
        'id' => 17,
        'slug' => 'qualitatsmanagement',
        'translations' => [[
            'language_code' => 'it',
            'name' => 'Gestione della qualità',
            'content' => '<p>Contenuto italiano reale.</p>',
        ]],
    ]], now()->addDays(7));

    expect(StaticPageController::getPage('it', 'qualitatsmanagement'))
        ->not->toBeNull();

    $this->get('/it/qualitatsmanagement')
        ->assertOk()
        ->assertSee('qualitatsmanagement', false)
        ->assertSee('Gestione della qualit', false)
        ->assertSee('Contenuto italiano reale.', false);
});
