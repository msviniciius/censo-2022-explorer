<?php

return [
    'name' => 'Census Explorer',
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost:8080'),
    'timezone' => 'UTC',
    'locale' => 'pt_BR',
    'fallback_locale' => 'en',
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY') ?: (is_file(storage_path('app/runtime.key'))
        ? trim(file_get_contents(storage_path('app/runtime.key')))
        : null),
    'previous_keys' => [],
    'maintenance' => ['driver' => 'file', 'store' => 'file'],
];
