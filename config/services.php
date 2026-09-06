<?php

return [

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'omr' => [
        'base' => env('OMR_API_BASE', 'https://omerdogan.de/api'),
        /** Tenant JSON API: 1 veya 2 (routes/tenant_api.php vs tenant_api_v2.php). */
        'api_version' => (int) env('OMR_API_VERSION', 2),
        'connect_timeout' => (int) env('OMR_API_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('OMR_API_TIMEOUT', 20),
        'retry_count' => (int) env('OMR_API_RETRY_COUNT', 1),
        'retry_sleep' => (int) env('OMR_API_RETRY_SLEEP', 500),
        'analytics_enabled' => filter_var(env('OMR_ANALYTICS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'page_timing_enabled' => filter_var(env('OMR_PAGE_TIMING_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'analytics_throttle_seconds' => (int) env('OMR_ANALYTICS_THROTTLE_SECONDS', 3600),
        /** Boşsa fallback kullanılır (config() ile okunmalı). */
        'tenant_id' => env('TENANT_ID', ''),
        'default_locale' => env('OMR_DEFAULT_LOCALE', 'de'),
        /** Brand / shared content tenant (optional). */
        /** Harita / bölge (.env’de VITE_* — build + sunucu için aynı değerler). */
        'tenant_district' => env('VITE_TENANT_DISTRICT', ''),
        'tenant_city' => env('VITE_TENANT_CITY', ''),
        'tenant_state_code' => env('VITE_TENANT_STATE_CODE', ''),
        'center_lng' => (float) env('VITE_TENANT_CENTER_LNG', 9.5),
        'center_lat' => (float) env('VITE_TENANT_CENTER_LAT', 51.5),
        'scale_desktop' => (int) env('VITE_TENANT_SCALE_DESKTOP', 2400),
        'scale_mobile' => (int) env('VITE_TENANT_SCALE_MOBILE', 2400),
        'location_category_slug' => env('OMR_LOCATION_CATEGORY_SLUG', 'gebaudereinigung'),
        'location_parent_id' => env('OMR_LOCATION_PARENT_ID', ''),
    ],

    'api' => [
        'base_url'  => env('DASHBOARD_API_URL', 'https://omerdogan.de/api'),
        'api_key'   => env('DASHBOARD_API_KEY', ''),
        'site_id'   => env('TENANT_ID', ''),
        'cache_ttl' => env('DASHBOARD_CACHE_TTL', 3600),
    ],


];
