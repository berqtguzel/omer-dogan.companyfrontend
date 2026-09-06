<?php

namespace App\Data;

final class SiteShellData
{
    public static function from(array $settings, array $menus, string $locale, bool $prefixed, string $origin): array
    {
        $text = fn ($value) => NavigationData::text($value);
        $link = fn ($value) => NavigationData::link($value, $locale, $prefixed, $origin);
        $branding = $settings['branding'] ?? [];
        $general = $settings['general'] ?? [];
        $contact = $settings['contact']['contact_infos'][0] ?? $settings['contact'] ?? [];
        $phone = $text($contact['phone'] ?? $settings['phone'] ?? '');
        $email = $text($contact['email'] ?? $settings['email'] ?? '');
        $social = [];
        foreach (['facebook' => 'Facebook', 'instagram' => 'Instagram', 'twitter' => 'X', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'tiktok' => 'TikTok'] as $key => $label) {
            $raw = $settings['social']['social_'.$key] ?? $settings['social'][$key.'_url'] ?? '';
            if (is_string($raw) && preg_match('/^(?:www\.)?[a-z0-9-]+\.[a-z]{2,}(?:\/|$)/i', $raw)) {
                $raw = 'https://'.$raw;
            }
            $url = $link($raw);
            if ($url['external']) {
                $social[] = ['label' => $label, ...$url, 'newTab' => true];
            }
        }
        // SettingsController already resolves and mirrors images. Do not fetch media again.
        $image = function ($value): string {
            $raw = is_array($value) ? ($value['url'] ?? '') : $value;
            return is_string($raw) && preg_match('#^(?:https?://|/(?!/))#i', $raw) ? $raw : '';
        };
        return [
            'name' => $text($general['site_name'] ?? $branding['site_name'] ?? '') ?: 'Ömer Dogan Company GmbH',
            'description' => $text($settings['footer']['footer_description'] ?? $general['site_description'] ?? ''),
            'logo' => $image($branding['site_logo_url'] ?? $branding['logo_url'] ?? ''),
            'footerLogo' => $image($branding['site_dark_logo_url'] ?? $branding['dark_logo_url'] ?? ''),
            'home' => $link('/'),
            'contactLink' => $link('/kontakt'),
            'header' => NavigationData::items($menus['header'] ?? [], $locale, $prefixed, $origin),
            'footer' => NavigationData::items($menus['footer'] ?? [], $locale, $prefixed, $origin),
            'phone' => $phone,
            'phoneHref' => $phone !== '' ? 'tel:'.preg_replace('/[^+0-9]/', '', $phone) : '',
            'email' => $email,
            'emailHref' => filter_var($email, FILTER_VALIDATE_EMAIL) ? 'mailto:'.$email : '',
            'address' => $text($contact['address'] ?? implode(' ', array_filter([
                $contact['street'] ?? '', $contact['postal_code'] ?? '', $contact['city'] ?? '',
            ]))),
            'social' => $social,
        ];
    }
}
