<?php

namespace App\Support;

final class LocaleMapper
{
    public const WEB_LOCALES = [
        'de', 'en', 'tr', 'ru', 'fr', 'es', 'it', 'pt', 'ro', 'pl', 'cs', 'sk', 'bg', 'hr',
    ];

    public static function toWeb(?string $locale): string
    {
        $locale = str_replace('_', '-', strtolower(trim((string) $locale)));
        $locale = explode('-', $locale)[0] ?? '';

        return $locale === 'cz' ? 'cs' : $locale;
    }

    public static function toApi(?string $locale): string
    {
        $locale = self::toWeb($locale);

        return $locale === 'cs' ? 'cz' : $locale;
    }

    public static function isSupportedWeb(?string $locale): bool
    {
        return in_array(self::toWeb($locale), self::WEB_LOCALES, true);
    }

    public static function normalizeWebUrl(string $url): string
    {
        return preg_replace('#/cz(?=/|$)#', '/cs', $url, 1) ?: $url;
    }
}
