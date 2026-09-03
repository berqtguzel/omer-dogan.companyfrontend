<?php

use App\Support\LocaleMapper;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class);

it('seeds every locale page cache from one translated source payload', function () {
    config([
        'cache.default' => 'array',
        'services.omr.tenant_id' => 'tenant-pages',
        'services.omr.main_tenant' => 'tenant-pages',
    ]);

    Cache::clear();

    $pages = [[
        'id' => 1,
        'slug' => 'about',
        'translations' => [
            ['language_code' => 'de', 'name' => 'Über uns'],
            ['language_code' => 'tr', 'name' => 'Hakkımızda'],
        ],
    ]];

    Cache::put('pages_list_v5_tenant-pages_de', $pages, now()->addMinute());

    $this->artisan('omr:warm-pages', [
        '--locale' => 'de',
        '--all-locales' => true,
    ])->assertSuccessful();

    foreach (LocaleMapper::WEB_LOCALES as $locale) {
        expect(Cache::get("pages_list_v5_tenant-pages_{$locale}"))->toBe($pages);
    }
});
