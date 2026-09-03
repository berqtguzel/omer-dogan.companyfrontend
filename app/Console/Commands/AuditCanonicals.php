<?php

namespace App\Console\Commands;

use App\Services\TenantCanonicalUrlResolver;
use App\Support\OmrConfig;
use Illuminate\Console\Command;

class AuditCanonicals extends Command
{
    protected $signature = 'seo:audit-canonicals
        {--locale=de : Example locale}
        {--path=berlin : Example page path or slug}';

    protected $description = 'Read-only audit of tenant canonical, sitemap and hreflang domains';

    public function handle(TenantCanonicalUrlResolver $resolver): int
    {
        $locale = strtolower((string) $this->option('locale'));
        $path = (string) $this->option('path');
        $configured = config('seo.tenant_domains', []);
        $tenants = is_array($configured) ? $configured : [];

        if (! array_key_exists(OmrConfig::tenantId(), $tenants)) {
            $tenants[OmrConfig::tenantId()] = [
                'canonical_url' => config('seo.canonical_base_url'),
                'aliases' => [],
            ];
        }

        $rows = [];
        $primaryHosts = [];
        $hasErrors = false;

        foreach ($tenants as $tenantId => $domainConfig) {
            $domainConfig = is_array($domainConfig) ? $domainConfig : ['canonical_url' => $domainConfig];
            $baseUrl = $resolver->baseUrl((string) $tenantId);
            $canonical = $resolver->url($path, $locale, (string) $tenantId);
            $host = $baseUrl ? (string) parse_url($baseUrl, PHP_URL_HOST) : '';
            $aliases = collect($domainConfig['aliases'] ?? [])->filter()->implode(', ');
            $issues = [];

            if (! $baseUrl) {
                $issues[] = 'primary domain missing';
            }
            if ($canonical && (str_contains($canonical, '/public/') || parse_url($canonical, PHP_URL_QUERY))) {
                $issues[] = 'canonical is not normalized';
            }
            if ($canonical && (string) parse_url($canonical, PHP_URL_HOST) !== $host) {
                $issues[] = 'canonical/sitemap host mismatch';
            }
            if ($host !== '') {
                $primaryHosts[$host][] = (string) $tenantId;
            }

            $hasErrors = $hasErrors || $issues !== [];
            $rows[] = [
                (string) $tenantId,
                $aliases ?: '-',
                $host ?: 'MISSING',
                $canonical ?: 'OMITTED',
                $host ?: 'MISSING',
                $host ?: 'MISSING',
                $canonical ? 1 : 0,
                $issues ? implode('; ', $issues) : 'OK',
            ];
        }

        foreach ($primaryHosts as $host => $tenantIds) {
            if (count(array_unique($tenantIds)) > 1) {
                $hasErrors = true;
                $this->error("Primary domain [{$host}] is assigned to multiple tenants: ".implode(', ', $tenantIds));
            }
        }

        $this->table([
            'Tenant', 'Request aliases', 'Primary domain', 'Example canonical',
            'Sitemap domain', 'Hreflang domains', 'Canonical count', 'Result',
        ], $rows);

        $this->line('Read-only audit: no cache, settings, or content was changed.');

        return $hasErrors ? self::FAILURE : self::SUCCESS;
    }
}
