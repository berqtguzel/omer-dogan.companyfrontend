<?php

use App\Support\Omr\OmrCatalog;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class);

test('uses the default catalog for every locale when localized catalog data is missing', function (string $locale) {
    $tenant = 'catalog-locale-fallback-'.uniqid();
    $query = ['parent_id' => 'null'];
    $queryHash = md5(json_encode($query, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $defaultCacheKey = "omr_catalog_v16_services_{$tenant}_de_{$queryHash}";
    $categories = [
        [
            'id' => 1,
            'slug' => 'apartment-hotelreinigung',
            'parent_id' => null,
            'translations' => [
                ['language_code' => 'de', 'name' => 'Apartment Hotelreinigung'],
                ['language_code' => $locale, 'name' => "Translated {$locale}"],
            ],
        ],
    ];

    Cache::put($defaultCacheKey, $categories, now()->addHour());

    expect(OmrCatalog::services($tenant, $locale, $query))->toBe($categories)
        ->and(OmrCatalog::rootCategories($tenant, $locale))->toHaveCount(1)
        ->and(OmrCatalog::rootCategories($tenant, $locale)[0]['slug'])
        ->toBe('apartment-hotelreinigung');
})->with([
    'English' => 'en',
    'Turkish' => 'tr',
    'French' => 'fr',
    'Spanish' => 'es',
    'Italian' => 'it',
    'Portuguese' => 'pt',
    'Romanian' => 'ro',
    'Polish' => 'pl',
    'Czech' => 'cz',
    'Slovak' => 'sk',
    'Bulgarian' => 'bg',
    'Croatian' => 'hr',
    'Russian' => 'ru',
]);

test('uses the default catalog when an empty localized result is cached', function () {
    $tenant = 'catalog-empty-locale-fallback-'.uniqid();
    $query = ['parent_id' => 'null'];
    $queryHash = md5(json_encode($query, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $categories = [
        ['id' => 6, 'slug' => 'apartment-hotelreinigung', 'parent_id' => null],
        ['id' => 23, 'slug' => 'stewarding-service-spulkuchenreinigung', 'parent_id' => null],
        ['id' => 27, 'slug' => 'abriss-abrissarbeit-abbrucharbeit', 'parent_id' => null],
    ];

    Cache::put("omr_catalog_v16_services_{$tenant}_de_{$queryHash}", $categories, now()->addHour());
    Cache::put("omr_catalog_v16_services_{$tenant}_tr_{$queryHash}", [], now()->addHour());

    expect(OmrCatalog::services($tenant, 'tr', $query))->toBe($categories)
        ->and(OmrCatalog::rootCategories($tenant, 'tr'))->toHaveCount(3);
});
