<?php

use App\Http\Controllers\LocationShowController;

function locationItemMatchesCity(array $item, string $citySlug): bool
{
    $controller = new LocationShowController;
    $method = new ReflectionMethod($controller, 'itemCityMatches');

    return $method->invoke($controller, $item, $citySlug);
}

it('does not treat a composite city ending in Berlin as the city of Berlin', function () {
    $item = [
        'slug' => 'abriss-abrissarbeit-abbrucharbeit-neuenhagen-bei-berlin',
        'city' => 'Neuenhagen bei Berlin',
        'district' => 'Brandenburg',
    ];

    expect(locationItemMatchesCity($item, 'berlin'))->toBeFalse()
        ->and(locationItemMatchesCity($item, 'neuenhagen-bei-berlin'))->toBeTrue();
});

it('still matches an explicit Berlin city and slug-only legacy records', function () {
    expect(locationItemMatchesCity([
        'slug' => 'gebaudereinigung-berlin',
        'city' => 'Berlin',
    ], 'berlin'))->toBeTrue()
        ->and(locationItemMatchesCity([
            'slug' => 'gebaudereinigung-berlin',
        ], 'berlin'))->toBeTrue();
});
