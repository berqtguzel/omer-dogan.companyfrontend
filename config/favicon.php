<?php

return [
    'public_base_path' => env('FAVICON_PUBLIC_BASE_PATH', public_path('favicon')),
    'metadata_path' => env('FAVICON_METADATA_PATH', storage_path('app/favicon-sync.json')),
];
