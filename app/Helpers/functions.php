<?php

declare(strict_types=1);

use App\Core\Config;

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = rtrim((string) Config::get('app.url', ''), '/');
        $path = '/' . ltrim($path, '/');

        return $base . ($path === '/' ? '' : $path);
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return url('/assets/' . ltrim($path, '/'));
    }
}

