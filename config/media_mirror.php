<?php

return [

    'enabled' => env('MEDIA_MIRROR_ENABLED', true),

    // Remote files should be downloaded by media:sync, not while serving a
    // browser request. This keeps a slow media host from blocking PHP workers.
    'download_on_request' => filter_var(env('MEDIA_MIRROR_DOWNLOAD_ON_REQUEST', false), FILTER_VALIDATE_BOOLEAN),

    'disk' => 'public',

    'cache_dir' => 'media-cache',

    'proxy_path' => 'media-proxy',

    'manifest_file' => 'media-mirror/manifest.json',

    'remote_hosts' => array_filter(array_map('trim', explode(',', env(
        'MEDIA_MIRROR_REMOTE_HOSTS',
        'omerdogan.de'
    )))),

    'remote_base' => rtrim(env('MEDIA_MIRROR_REMOTE_BASE', 'https://omerdogan.de'), '/'),

    'download_timeout' => (int) env('MEDIA_MIRROR_TIMEOUT', 30),

    'jpeg_quality' => (int) env('MEDIA_MIRROR_JPEG_QUALITY', 82),

    /**
     * Görsel varyantları (px genişlik).
     * card  → ServiceCard / LocationCard (400px görünüm, 2x retina)
     * default → slider, logo, OG, hero poster
     */
    'variants' => [
        'card' => (int) env('MEDIA_MIRROR_CARD_WIDTH', 480),
        'default' => (int) env('MEDIA_MIRROR_MAX_WIDTH', 1200),
    ],

    /** Bu alan adları API cevabında card varyantını kullanır */
    'card_fields' => ['image'],

];
