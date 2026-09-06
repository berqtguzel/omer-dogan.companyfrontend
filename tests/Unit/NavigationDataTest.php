<?php

use App\Data\NavigationData;
use App\Data\SiteShellData;

it('preserves external protocols and rejects executable navigation URLs', function () {
    $link = fn ($url) => NavigationData::link($url, 'tr', true, 'https://example.com');
    expect($link('https://partner.example/company')['href'])->toBe('https://partner.example/company')
        ->and($link('//partner.example/company')['external'])->toBeTrue()
        ->and($link('mailto:office@example.com')['href'])->toBe('mailto:office@example.com')
        ->and($link('javascript:alert(1)')['href'])->toBe('')
        ->and($link('java&#10;script:alert(1)')['href'])->toBe('')
        ->and($link('data:text/html,test')['href'])->toBe('');
});

it('preserves the current URL prefix convention and query fragments', function () {
    expect(NavigationData::link('/kontakt', 'de', false, 'https://example.com')['href'])->toBe('/kontakt')
        ->and(NavigationData::link('/de/en/kontakt?from=nav#form', 'tr', true, 'https://example.com')['href'])
        ->toBe('/tr/kontakt?from=nav#form')
        ->and(NavigationData::link('https://example.com/de/kontakt', 'en', true, 'https://example.com')['href'])
        ->toBe('/en/kontakt')
        ->and(NavigationData::link('#vision', 'en', true, 'https://example.com')['href'])->toBe('#vision');
});

it('keeps panel menu hierarchy and new-tab intent without inventing links', function () {
    $items = NavigationData::items([
        ['label' => '<b>Unternehmen</b>', 'children' => [
            ['id' => 2, 'label' => 'Partner', 'url' => 'https://partner.example', 'target' => '_blank'],
        ]],
        ['label' => 'Invalid', 'url' => 'javascript:alert(1)'],
    ], 'de', true, 'https://example.com');
    expect($items)->toHaveCount(1)
        ->and($items[0]['label'])->toBe('Unternehmen')
        ->and($items[0]['href'])->toBe('')
        ->and($items[0]['children'][0]['newTab'])->toBeTrue();
});

it('normalizes shell contact and branding without requesting media or fabricating companies', function () {
    $shell = SiteShellData::from([
        'general' => ['site_name' => 'Example Group'],
        'branding' => ['site_logo_url' => '/media-cache/logo.webp'],
        'contact' => ['contact_infos' => [['phone' => '+49 (30) 123', 'email' => 'office@example.com']]],
        'social' => ['linkedin_url' => 'www.linkedin.com/company/example'],
    ], [], 'de', false, 'https://example.com');
    expect($shell['name'])->toBe('Example Group')
        ->and($shell['logo'])->toBe('/media-cache/logo.webp')
        ->and($shell['phoneHref'])->toBe('tel:+4930123')
        ->and($shell['emailHref'])->toBe('mailto:office@example.com')
        ->and($shell['header'])->toBe([])
        ->and($shell['footer'])->toBe([])
        ->and($shell['social'][0]['href'])->toBe('https://www.linkedin.com/company/example');
});
