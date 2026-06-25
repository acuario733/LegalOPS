<?php

declare(strict_types=1);

use App\Core\Config;

$isProduction = Config::env('APP_ENV', 'production') === 'production';

return [
    'session' => [
        'name' => Config::env('SESSION_NAME', 'legalops_session'),
        'secure' => $isProduction ? true : Config::env('SESSION_SECURE', true),
        'http_only' => true,
        'same_site' => Config::env('SESSION_SAME_SITE', 'Lax'),
        'lifetime_minutes' => (int) Config::env('SESSION_LIFETIME', 120),
    ],
    'csrf' => [
        'session_key' => '_csrf_token',
        'header' => 'X-CSRF-TOKEN',
        'input' => '_token',
    ],
];
