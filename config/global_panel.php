<?php

return [
    'url' => rtrim((string) env('GLOBAL_PANEL_URL', ''), '/'),
    'tenant_id' => (string) env('TENANT_ID', ''),
    'register_token' => (string) env('FRONT_REGISTER_TOKEN', ''),
    'deploy_path' => (string) env('DEPLOY_PATH', ''),
    'timeout_seconds' => (int) env('GLOBAL_PANEL_TIMEOUT', 30),
];