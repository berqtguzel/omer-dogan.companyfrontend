<?php

namespace App\Services;

use App\Support\OmrConfig;
use Illuminate\Support\Facades\Log;

class TenantCanonicalUrlResolver
{
    private static array $reportedMissingDomains = [];

    public function baseUrl(?string $tenantId = null): ?string
    {
        $tenantId = $tenantId ?: OmrConfig::tenantId();
        $tenantConfig = config("seo.tenant_domains.{$tenantId}");
        $configured = is_array($tenantConfig)
            ? ($tenantConfig['canonical_url'] ?? $tenantConfig['primary_url'] ?? null)
            : $tenantConfig;

        if (! is_string($configured) || trim($configured) === '') {
            if ($tenantId === OmrConfig::tenantId()) {
                $configured = config('seo.canonical_base_url');
            }
        }

        $baseUrl = $this->normalizeBaseUrl(is_string($configured) ? $configured : null);

        if ($baseUrl === null && ! isset(self::$reportedMissingDomains[$tenantId])) {
            self::$reportedMissingDomains[$tenantId] = true;
            Log::error('Tenant canonical domain is not configured', [
                'tenant_id' => $tenantId,
                'event' => 'seo.canonical_domain_missing',
            ]);
        }

        return $baseUrl;
    }

    public function url(string $path = '/', ?string $locale = null, ?string $tenantId = null): ?string
    {
        $baseUrl = $this->baseUrl($tenantId);

        if ($baseUrl === null) {
            return null;
        }

        return $baseUrl.$this->normalizePath($path, $locale);
    }

    public function normalizePath(string $value, ?string $locale = null): string
    {
        $path = parse_url(trim($value), PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        $path = preg_replace('#/+#', '/', '/'.ltrim($path, '/')) ?: '/';
        $path = preg_replace('#^/public(?=/|$)#i', '', $path) ?: '/';
        $path = '/'.ltrim($path, '/');

        $locale = strtolower(trim((string) $locale));
        $locale = preg_match('/^[a-z]{2}$/', $locale) ? $locale : null;

        if ($locale !== null && ! preg_match('#^/[a-z]{2}(?:/|$)#i', $path)) {
            $path = $path === '/' ? "/{$locale}/" : "/{$locale}{$path}";
        }

        if (preg_match('#^/([a-z]{2})/(?:home|homepage|startseite)/?$#i', $path, $match)) {
            $path = '/'.strtolower($match[1]).'/';
        }

        if (preg_match('#^/([a-z]{2})/categories/([^/]+)/?$#i', $path, $match)) {
            $path = '/'.strtolower($match[1]).'/'.$match[2];
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/')), fn ($part) => $part !== ''));
        $segments = array_map(function (string $segment): string {
            $decoded = rawurldecode($segment);

            return rawurlencode($decoded);
        }, $segments);

        $normalized = '/'.implode('/', $segments);

        if ($normalized === '/' || count($segments) === 1 && preg_match('/^[a-z]{2}$/', $segments[0])) {
            return $normalized === '/' ? '/' : $normalized.'/';
        }

        return rtrim($normalized, '/');
    }

    public function cacheScope(?string $tenantId = null): string
    {
        return substr(hash('sha256', ($tenantId ?: OmrConfig::tenantId()).'|'.($this->baseUrl($tenantId) ?? 'missing')), 0, 16);
    }

    private function normalizeBaseUrl(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $candidate = trim($value);
        if (! preg_match('#^https?://#i', $candidate)) {
            $candidate = 'https://'.$candidate;
        }

        $host = parse_url($candidate, PHP_URL_HOST);
        $port = parse_url($candidate, PHP_URL_PORT);

        if (! is_string($host) || $host === '' || filter_var($host, FILTER_VALIDATE_IP) === false && ! str_contains($host, '.')) {
            return null;
        }

        $scheme = app()->environment('production') ? 'https' : strtolower((string) parse_url($candidate, PHP_URL_SCHEME));
        $scheme = in_array($scheme, ['http', 'https'], true) ? $scheme : 'https';

        return $scheme.'://'.strtolower($host).($port ? ':'.$port : '');
    }
}
