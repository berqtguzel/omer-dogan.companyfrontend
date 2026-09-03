<?php

$tenantDomains = json_decode((string) env('TENANT_CANONICAL_DOMAINS', '{}'), true);

return [
    'hidden_links' => [],

    /*
     * This is deployment-owned configuration, never an HTTP Host header.
     * TENANT_CANONICAL_URL is preferred; the existing sitemap/app settings
     * keep current single-tenant deployments backwards compatible.
     */
    'canonical_base_url' => env(
        'TENANT_CANONICAL_URL',
        env('SITEMAP_BASE_URL', env('APP_URL'))
    ),

    /* Optional JSON map for installations resolving more than one tenant. */
    'tenant_domains' => is_array($tenantDomains) ? $tenantDomains : [],
];
