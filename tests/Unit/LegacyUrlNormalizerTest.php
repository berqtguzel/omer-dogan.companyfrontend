<?php

use App\Support\LegacyUrlNormalizer;

it('normalizes duplicated legacy service and city slugs', function () {
    expect(LegacyUrlNormalizer::serviceSlug(
        'polsterreinigung-in-leverkusen-polsterreinigung-firma-in-leverkusen'
    ))->toBe('polsterreinigung-in-leverkusen')
        ->and(LegacyUrlNormalizer::serviceSlug(
            'restaurantreinigung-in-frankfurt-am-main-restaurantreinigung-firma-in-frankfurt-am-main'
        ))->toBe('restaurantreinigung-in-frankfurt-am-main')
        ->and(LegacyUrlNormalizer::serviceSlug('polsterreinigung-in-leverkusen'))
        ->toBeNull();
});

it('maps known legacy service aliases to the current route slug', function () {
    expect(LegacyUrlNormalizer::serviceSlug(
        'sperrmull-entsorgung-in-celle-sperrmull-entsorgung-firma-in-celle'
    ))->toBe('sperrmullentsorgung-in-celle');
});

it('maps misspelled legacy service location slugs to their canonical routes', function (string $legacy, string $canonical) {
    expect(LegacyUrlNormalizer::serviceLocationAlias($legacy))->toBe($canonical)
        ->and(LegacyUrlNormalizer::serviceSlug("{$legacy}-".strstr($legacy, '-in-', true).'-firma-in-'.substr(strstr($legacy, '-in-'), 4)))
        ->toBe($canonical);
})->with([
    ['wohnungsreinigung-in-hagen', 'apartmentreinigung-in-hagen'],
    ['winterdienst-in-neustadt-an-der-weinstrabe', 'winterdienst-in-neustadt-an-der-weinstrasse'],
    ['winterdienst-in-biberach-an-der-rib', 'winterdienst-in-biberach-an-der-riss'],
    ['treppenhausreinigung-in-gieben', 'treppenhausreinigung-in-giessen'],
    ['teppichreinigung-in-gieben', 'teppichreinigung-in-giessen'],
    ['schwimmbadreinigung-in-ludwighburg', 'schwimmbadreinigung-in-ludwigsburg'],
    ['restaurantreinigung-in-ludwighburg', 'restaurantreinigung-in-ludwigsburg'],
    ['praxisreinigung-in-ludwighburg', 'praxisreinigung-in-ludwigsburg'],
    ['polsterreinigung-in-ludwighburg', 'polsterreinigung-in-ludwigsburg'],
    ['restaurantreinigung-in-freifswald', 'restaurantreinigung-in-greifswald'],
    ['restaurantreinigung-in-breiburg-im-breisgau', 'restaurantreinigung-in-freiburg-im-breisgau'],
]);
