<?php

use App\Http\Controllers\LocationController;

function formatLocationCardTitle(array $service, string $city, string $locale): ?string
{
    $method = new ReflectionMethod(LocationController::class, 'formatServiceLocationTitle');
    $method->setAccessible(true);

    return $method->invoke(null, $service, $city, $locale);
}

test('uses the requested service translation for location card titles', function (string $locale, string $expected) {
    $service = [
        'name' => 'Abriss / Abrissarbeit / Abbrucharbeit Aalen',
        'translations' => [
            ['language_code' => 'de', 'name' => 'Abriss / Abrissarbeit / Abbrucharbeit Aalen'],
            ['language_code' => 'tr', 'name' => 'Yıkım / Yıkım İşi / Yıkım Çalışması Aalen'],
            ['language_code' => 'en', 'name' => 'Demolition / Demolition Work Aalen'],
        ],
    ];

    expect(formatLocationCardTitle($service, 'Aalen', $locale))->toBe($expected);
})->with([
    'German' => ['de', 'Abriss / Abrissarbeit / Abbrucharbeit Aalen'],
    'Turkish' => ['tr', 'Yıkım / Yıkım İşi / Yıkım Çalışması Aalen'],
    'English' => ['en', 'Demolition / Demolition Work Aalen'],
]);

test('supports Czech locale aliases in service translations', function () {
    $service = [
        'translations' => [
            ['language_code' => 'cs', 'name' => 'Demolice Aalen'],
        ],
    ];

    expect(formatLocationCardTitle($service, 'Aalen', 'cz'))->toBe('Demolice Aalen');
});

test('falls back to the city instead of German when a requested translation is missing', function () {
    $service = [
        'name' => 'Abriss / Abrissarbeit / Abbrucharbeit Aalen',
        'translations' => [
            ['language_code' => 'de', 'name' => 'Abriss / Abrissarbeit / Abbrucharbeit Aalen'],
        ],
    ];

    expect(formatLocationCardTitle($service, 'Aalen', 'fr'))->toBe('Aalen');
});
