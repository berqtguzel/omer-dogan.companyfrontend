<?php

return [
    'enabled' => filter_var(env('OMR_AUTO_WARMUP', true), FILTER_VALIDATE_BOOLEAN),
    'version' => env('OMR_WARMUP_VERSION', '1'),
    'source_locale' => env('OMR_WARMUP_SOURCE_LOCALE', 'de'),
    'media' => filter_var(env('OMR_AUTO_WARM_MEDIA', true), FILTER_VALIDATE_BOOLEAN),
    'webhook_secret' => env('OMR_WARMUP_WEBHOOK_SECRET'),

    'catalog' => [
        'max_pages' => (int) env('OMR_WARMUP_MAX_PAGES', 120),
        'per_page' => (int) env('OMR_WARMUP_PER_PAGE', 100),
        'sleep_ms' => (int) env('OMR_WARMUP_SLEEP_MS', 2500),
        'limit_categories' => (int) env('OMR_WARMUP_LIMIT_CATEGORIES', 1),
    ],
];
