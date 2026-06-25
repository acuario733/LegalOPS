<?php

declare(strict_types=1);

use App\Core\Config;

return [
    'driver' => Config::env('DB_DRIVER', 'mysql'),
    'host' => Config::env('DB_HOST', '127.0.0.1'),
    'port' => (int) Config::env('DB_PORT', 3306),
    'database' => Config::env('DB_DATABASE', 'legalops'),
    'username' => Config::env('DB_USERNAME', ''),
    'password' => Config::env('DB_PASSWORD', ''),
    'charset' => Config::env('DB_CHARSET', 'utf8mb4'),
];

