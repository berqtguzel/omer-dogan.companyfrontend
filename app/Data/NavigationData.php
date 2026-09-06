<?php

namespace App\Data;

use App\Support\LocaleMapper;

final class NavigationData
{
    public static function items(array $items, string $locale, bool $prefixed, string $origin): array
    {
        $result = [];
        foreach ($items['items'] ?? $items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }
            $label = self::text($item['label'] ?? $item['name'] ?? '');
            $children = self::items((array) ($item['children'] ?? []), $locale, $prefixed, $origin);
            $link = self::link($item['url'] ?? '', $locale, $prefixed, $origin);
            if ($label === '' || ($link['href'] === '' && $children === [])) {
                continue;
            }
            $result[] = [
                'id' => (string) ($item['id'] ?? $index),
                'label' => $label,
                ...$link,
                'newTab' => $link['external'] || ($item['target'] ?? '') === '_blank',
                'children' => $children,
            ];
        }

        return $result;
    }

    public static function link(mixed $value, string $locale, bool $prefixed, string $origin): array
    {
        $empty = ['href' => '', 'external' => false];
        if (! is_string($value)) {
            return $empty;
        }
        $url = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '' || preg_match('/[\x00-\x20\\\\]/', $url)) {
            return $empty;
        }
        if (preg_match('/^(?:mailto:|tel:)/i', $url) || str_starts_with($url, '#')) {
            return ['href' => $url, 'external' => false];
        }
        if (str_starts_with($url, '//')) {
            $url = 'https:'.$url;
        }
        if (preg_match('#^https?://#i', $url)) {
            if (! filter_var($url, FILTER_VALIDATE_URL)) {
                return $empty;
            }
            $target = parse_url($url);
            $base = parse_url($origin);
            if (strtolower($target['host'] ?? '') !== strtolower($base['host'] ?? '')
                || ($target['port'] ?? null) !== ($base['port'] ?? null)) {
                return ['href' => $url, 'external' => true];
            }
            $url = ($target['path'] ?? '/').(isset($target['query']) ? '?'.$target['query'] : '')
                .(isset($target['fragment']) ? '#'.$target['fragment'] : '');
        } elseif (preg_match('/^[a-z][a-z0-9+.-]*:/i', $url)) {
            return $empty;
        }
        $parts = preg_split('/(?=[?#])/', $url, 2);
        $segments = array_values(array_filter(explode('/', $parts[0]), fn ($part) => $part !== ''));
        while ($segments && LocaleMapper::isSupportedWeb($segments[0])) {
            array_shift($segments);
        }
        $prefix = $prefixed ? '/'.$locale : '';
        $path = $prefix.'/'.implode('/', $segments);

        return ['href' => $path.($parts[1] ?? ''), 'external' => false];
    }

    public static function text(mixed $value): string
    {
        return is_scalar($value)
            ? trim(strip_tags(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8')))
            : '';
    }
}
