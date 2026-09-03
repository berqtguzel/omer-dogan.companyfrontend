<?php

namespace App\Support;

final class LegacyUrlNormalizer
{
    /**
     * Old service/location slugs that were published with a misspelled city or
     * a retired service name. Keep these exact so unrelated slugs are not
     * rewritten accidentally.
     */
    private const SERVICE_LOCATION_ALIASES = [
        'wohnungsreinigung-in-hagen' => 'apartmentreinigung-in-hagen',
        'winterdienst-in-neustadt-an-der-weinstrabe' => 'winterdienst-in-neustadt-an-der-weinstrasse',
        'winterdienst-in-biberach-an-der-rib' => 'winterdienst-in-biberach-an-der-riss',
        'treppenhausreinigung-in-gieben' => 'treppenhausreinigung-in-giessen',
        'teppichreinigung-in-gieben' => 'teppichreinigung-in-giessen',
        'schwimmbadreinigung-in-ludwighburg' => 'schwimmbadreinigung-in-ludwigsburg',
        'restaurantreinigung-in-ludwighburg' => 'restaurantreinigung-in-ludwigsburg',
        'praxisreinigung-in-ludwighburg' => 'praxisreinigung-in-ludwigsburg',
        'polsterreinigung-in-ludwighburg' => 'polsterreinigung-in-ludwigsburg',
        'restaurantreinigung-in-freifswald' => 'restaurantreinigung-in-greifswald',
        'restaurantreinigung-in-breiburg-im-breisgau' => 'restaurantreinigung-in-freiburg-im-breisgau',
    ];

    /**
     * Convert the old SEO pattern
     * service-in-city-service-firma-in-city to service-in-city.
     */
    public static function serviceSlug(?string $value): ?string
    {
        $slug = strtolower(trim(rawurldecode((string) $value), "/ \t\n\r\0\x0B"));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?: '';
        $slug = trim($slug, '-');

        if ($slug === '') {
            return null;
        }

        $pattern = '/^(?<service>[a-z0-9]+(?:-[a-z0-9]+)*)-in-(?<city>[a-z0-9]+(?:-[a-z0-9]+)*)-\k<service>-firma-in-\k<city>$/';

        if (! preg_match($pattern, $slug, $matches)) {
            return null;
        }

        $service = match ($matches['service']) {
            'sperrmull-entsorgung' => 'sperrmullentsorgung',
            default => $matches['service'],
        };

        return self::serviceLocationAlias($service.'-in-'.$matches['city'])
            ?? $service.'-in-'.$matches['city'];
    }

    /**
     * Return the canonical slug only when the supplied slug is a known alias.
     */
    public static function serviceLocationAlias(?string $value): ?string
    {
        $slug = strtolower(trim(rawurldecode((string) $value), "/ \t\n\r\0\x0B"));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?: '';
        $slug = trim($slug, '-');

        return self::SERVICE_LOCATION_ALIASES[$slug] ?? null;
    }
}
