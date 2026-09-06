<?php

use App\Data\HomePageData;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

function corporatePageProps($response): array
{
    $response->assertOk();
    // Media/pretty-HTML middleware returns a rebuilt response rather than a
    // TestResponse view. Inspect the same serialized page the browser reads.
    preg_match('/data-page="([^"]+)"/', $response->getContent(), $matches);
    expect($matches)->toHaveCount(2);

    return json_decode(html_entity_decode($matches[1], ENT_QUOTES), true, flags: JSON_THROW_ON_ERROR)['props'];
}

it('keeps real empty data empty and does not promote a cleaning company to group statistics', function () {
    $normalizer = new HomePageData([
        ['slug' => 'uber-uns', 'name' => 'Cleaning company', 'entity' => ['stats' => [['label' => 'Employees', 'value' => '2000']]]],
    ], 'de', false, 'https://example.com');
    $data = $normalizer->build([], config('corporate_home'));
    expect($data['metrics'])->toBe([])->and($data['companies'])->toBe([])->and($data['projects'])->toBe([])
        ->and($data['countries'])->toBe([])->and($data['hero']['primary']['href'])->toBe('/kontakt')
        ->and($data['businessAreas'])->toBe([]);
});

it('preserves panel hero data and only uses explicitly mapped published content', function () {
    $pages = [
        ['slug' => 'unternehmensgruppe', 'name' => 'Our group', 'entity' => ['stats' => [
            ['label' => 'Companies', 'value' => '0'], ['label' => 'People', 'value' => '350+'],
        ]]],
        ['slug' => 'company-a', 'name' => 'Company A', 'content' => '<p>First.</p><p>Second.</p>', 'entity' => ['industry' => 'Hospitality']],
        ['slug' => 'draft', 'name' => 'Draft', 'status' => 'draft'],
    ];
    $data = (new HomePageData($pages, 'de', true, 'https://example.com'))->build([
        'sliders' => [['title' => '<b>Panel title</b>', 'buttonLabel' => 'Partner', 'buttonUrl' => 'https://partner.example', 'image' => '/media-cache/hero.webp']],
    ], [...config('corporate_home'), 'company_pages' => ['company-a', 'draft']]);
    expect($data['hero']['title'])->toBe('Panel title')->and($data['hero']['primary']['external'])->toBeTrue()
        ->and($data['hero']['image'])->toBe('/media-cache/hero.webp')->and($data['metrics'])->toBe([])
        ->and($data['companies'])->toHaveCount(1)->and($data['companies'][0]['description'])->toBe('First. Second.')
        ->and($data['companies'][0]['link']['href'])->toBe('/de/unternehmen/company-a');
});

it('does not publish untranslated page or entity content in another language', function () {
    $pages = [
        ['slug' => 'missing', 'name' => 'Deutsch'],
        ['slug' => 'translated', 'name' => 'Deutsch', 'entity' => ['industry' => 'Gebäudereinigung'],
            'translations' => [['language_code' => 'en', 'name' => 'English', 'content' => 'English content']]],
    ];
    $data = (new HomePageData($pages, 'en', true, 'https://example.com'))->build([], [
        ...config('corporate_home'), 'company_pages' => ['missing', 'translated'],
    ]);
    expect($data['companies'])->toHaveCount(1)->and($data['companies'][0]['name'])->toBe('English')
        ->and($data['companies'][0]['sector'])->toBe('');
});
