<?php

use App\Data\CorporatePageData;

uses(Tests\TestCase::class);

it('does not render missing translations or unpublished corporate pages', function () {
    $page = ['name' => 'German page', 'content' => '<p>German body</p>'];
    expect(CorporatePageData::from($page, 'tr')['available'])->toBeFalse();
    expect(CorporatePageData::from([...$page, 'status' => 'draft'], 'de')['available'])->toBeFalse();
    expect(CorporatePageData::from(null, 'de')['content'])->toBe('');
});

it('uses the requested page translation and rejects executable image URLs', function () {
    $data = CorporatePageData::from(['name' => 'German', 'translations' => [
        ['language_code' => 'tr', 'name' => 'Turkish', 'content' => '<p>Translated</p>', 'image' => 'javascript:alert(1)'],
    ]], 'tr');
    expect($data['title'])->toBe('Turkish')->and($data['content'])->toBe('<p>Translated</p>')->and($data['image'])->toBe('');
});
