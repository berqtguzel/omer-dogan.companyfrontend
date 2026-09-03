<?php

return [

    'enabled' => env('UPDATE_ENABLED', false),

    'secret' => env('UPDATE_SECRET'),

    'github_owner' => env('GITHUB_REPO_OWNER', 'Oi2024'),
    'github_repo' => env('GITHUB_REPO_NAME', 'oicleanfront'),
    'github_token' => env('GITHUB_TOKEN'),
    'github_branch' => env('GITHUB_BRANCH', 'main'),
    'github_webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),

    /** FTP ile yüklenen sitelerde .git yoksa GitHub zipball kullan */
    'archive_fallback' => env('UPDATE_ARCHIVE_FALLBACK', true),

    'php_cli' => env('UPDATE_PHP_CLI', '/usr/bin/php82'),
    'composer_bin' => env('UPDATE_COMPOSER_BIN', '/usr/bin/composer'),

    'timeout' => (int) env('UPDATE_TIMEOUT', 900),

    'run_composer' => env('UPDATE_RUN_COMPOSER', true),

    /** Sunucuda Node/npm yoksa false — build GitHub Actions veya local'de alınır */
    'run_npm' => env('UPDATE_RUN_NPM', false),
    'run_build' => env('UPDATE_RUN_BUILD', false),
    'run_media_sync' => env('UPDATE_RUN_MEDIA_SYNC', false),

    'allowed_ips' => array_filter(array_map('trim', explode(',', (string) env('UPDATE_ALLOWED_IPS', '')))),

];
