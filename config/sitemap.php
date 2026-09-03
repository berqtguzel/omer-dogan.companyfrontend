<?php

use App\Support\LocaleMapper;

return [
    // Local mode builds the sitemap from known application routes and never
    // contacts the remote content or sitemap APIs.
    'source' => env('SITEMAP_SOURCE', 'local'),

    'base_url' => rtrim(env('TENANT_CANONICAL_URL', env('SITEMAP_BASE_URL', env('APP_URL', 'http://localhost'))), '/'),

    'locales' => LocaleMapper::WEB_LOCALES,

    'modules' => ['pages'],

    'max_urls_per_file' => 50000,
    'fresh_hours' => 24,
    'building_ttl_hours' => 6,
    'http_cache_seconds' => 3600,
    'live_cache_seconds' => 21600,
    'empty_cache_seconds' => 3600,
    'snapshot_path' => storage_path('app/sitemaps'),

    'connect_timeout' => (int) env('SITEMAP_CONNECT_TIMEOUT', 5),
    'request_timeout' => (int) env('SITEMAP_REQUEST_TIMEOUT', 20),
    'retry_count' => (int) env('SITEMAP_RETRY_COUNT', 2),
    'retry_sleep_ms' => (int) env('SITEMAP_RETRY_SLEEP_MS', 750),
    'request_sleep_ms' => (int) env('SITEMAP_REQUEST_SLEEP_MS', 0),
    'max_api_pages' => 1000,
    // This must remain enabled in production: foreign URLs without real API
    // translations redirect to German and must never enter a sitemap.
    'require_raw_translations' => true,
];
