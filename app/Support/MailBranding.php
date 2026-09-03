<?php

namespace App\Support;

use App\Http\Controllers\SettingsController;

final class MailBranding
{
    public static function resolve(?array $settings = null, string $locale = 'de'): array
    {
        if ($settings === null) {
            try {
                $settings = SettingsController::getFrontendSettings(OmrConfig::tenantId(), $locale, false);
            } catch (\Throwable) {
                $settings = [];
            }
        }

        $general = $settings['general'] ?? [];
        $branding = $settings['branding'] ?? [];
        $colors = $settings['colors'] ?? [];
        $contact = $settings['contact']['contact_infos'][0] ?? $settings['contact'] ?? [];
        $primaryColor = self::firstColor($settings, [
            'colors.site_primary_color',
            'colors.primary_color',
            'colors.primary',
            'branding.colors.primary_color',
            'branding.primary_color',
            'colors.button_color',
            'colors.header_background_color',
        ], '#17324d');
        $accentColor = self::firstColor($settings, [
            'colors.site_accent_color',
            'colors.accent_color',
            'colors.accent',
            'branding.colors.accent_color',
            'branding.accent_color',
            'colors.button_color',
        ], $primaryColor);
        $secondaryColor = self::firstColor($settings, [
            'colors.site_secondary_color',
            'colors.secondary_color',
            'colors.secondary',
            'branding.colors.secondary_color',
            'branding.secondary_color',
            'colors.footer_background_color',
        ], $primaryColor);

        return [
            'siteName' => self::text(
                $general['site_name'] ?? $branding['site_name'] ?? config('app.name'),
                config('app.name')
            ),
            'logoUrl' => self::url(
                $branding['site_logo_url']
                    ?? $branding['logo_url']
                    ?? $branding['site_logo']
                    ?? $branding['logo']
                    ?? null
            ),
            'primaryColor' => $primaryColor,
            'accentColor' => $accentColor,
            'secondaryColor' => $secondaryColor,
            'buttonTextColor' => self::contrastTextColor($accentColor),
            'contactEmail' => self::text($contact['email'] ?? config('mail.from.address')),
            'websiteUrl' => rtrim((string) config('app.url'), '/'),
        ];
    }

    private static function text(mixed $value, string $fallback = ''): string
    {
        if (is_array($value)) {
            $value = $value['value'] ?? $value['text'] ?? $value['label'] ?? null;
        }

        return is_scalar($value) && trim((string) $value) !== ''
            ? trim((string) $value)
            : $fallback;
    }

    private static function url(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value['url'] ?? $value['value'] ?? null;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (str_starts_with($value, '/')) {
            return rtrim((string) config('app.url'), '/').$value;
        }

        return filter_var($value, FILTER_VALIDATE_URL) ? $value : null;
    }

    private static function color(mixed $value): string
    {
        $color = self::text($value);

        if (preg_match('/^#[0-9a-fA-F]{3}$/', $color)) {
            return '#'.$color[1].$color[1].$color[2].$color[2].$color[3].$color[3];
        }

        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '';
    }

    private static function firstColor(array $settings, array $paths, string $fallback): string
    {
        foreach ($paths as $path) {
            $color = self::color(data_get($settings, $path));

            if ($color !== '') {
                return $color;
            }
        }

        $fallbackColor = self::color($fallback);

        return $fallbackColor !== '' ? $fallbackColor : '#17324d';
    }

    private static function contrastTextColor(string $backgroundColor): string
    {
        $color = ltrim($backgroundColor, '#');

        if (strlen($color) !== 6) {
            return '#ffffff';
        }

        $red = hexdec(substr($color, 0, 2));
        $green = hexdec(substr($color, 2, 2));
        $blue = hexdec(substr($color, 4, 2));
        $brightness = (($red * 299) + ($green * 587) + ($blue * 114)) / 1000;

        return $brightness > 165 ? '#172033' : '#ffffff';
    }
}
