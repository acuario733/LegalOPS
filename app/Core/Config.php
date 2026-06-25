<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Config
{
    /** @var array<string, mixed> */
    private static array $items = [];

    private static bool $loaded = false;

    public static function load(string $basePath): void
    {
        if (self::$loaded) {
            return;
        }

        self::loadEnvironmentFile($basePath . DIRECTORY_SEPARATOR . '.env');

        $configPath = $basePath . DIRECTORY_SEPARATOR . 'config';
        if (!is_dir($configPath)) {
            throw new RuntimeException('No se encontró el directorio de configuración.');
        }

        $files = glob($configPath . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files);

        foreach ($files as $file) {
            $key = pathinfo($file, PATHINFO_FILENAME);
            $values = require $file;

            if (!is_array($values)) {
                throw new RuntimeException(sprintf('La configuración "%s" no devolvió un arreglo.', $key));
            }

            self::$items[$key] = $values;
        }

        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = self::$items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public static function require(string $key): mixed
    {
        $value = self::get($key);
        if ($value === null || $value === '') {
            throw new RuntimeException(sprintf('Falta la configuración obligatoria "%s".', $key));
        }

        return $value;
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        return self::$items;
    }

    public static function env(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }

        return self::normalizeEnvironmentValue($value);
    }

    private static function loadEnvironmentFile(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException('No fue posible leer el archivo de entorno.');
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = substr($line, 7);
            }

            $separator = strpos($line, '=');
            if ($separator === false) {
                continue;
            }

            $key = trim(substr($line, 0, $separator));
            $value = trim(substr($line, $separator + 1));

            if ($key === '' || getenv($key) !== false) {
                continue;
            }

            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }

    private static function normalizeEnvironmentValue(string $value): mixed
    {
        return match (strtolower(trim($value))) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }
}

