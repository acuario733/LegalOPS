<?php

/**
 * Configuración de Phinx para LegalOPS Cloud V2.
 *
 * NOTA: Este proyecto ya tiene un MigrationRunner propio (app/Core/MigrationRunner.php)
 * que ejecuta las migraciones SQL en database/migrations/.
 * Phinx se agrega como alternativa para entornos CI/CD y herramientas externas.
 *
 * Instalar con:
 *   composer require --dev robmorgan/phinx
 *
 * Uso:
 *   vendor/bin/phinx migrate -e development
 *   vendor/bin/phinx rollback -e development
 *   vendor/bin/phinx status -e development
 *   vendor/bin/phinx seed:run -e development
 *   vendor/bin/phinx create NombreMigracion
 */

// Cargar variables de entorno del .env si no están disponibles
if (!getenv('DB_HOST')) {
    $envFile = __DIR__ . '/.env';
    if (is_file($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $sep = strpos($line, '=');
            if ($sep === false) {
                continue;
            }
            $key = trim(substr($line, 0, $sep));
            $val = trim(substr($line, $sep + 1), " \t\"'");
            if ($key !== '' && getenv($key) === false) {
                putenv("$key=$val");
            }
        }
    }
}

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/database/phinx',
        'seeds'      => '%%PHINX_CONFIG_DIR%%/database/seeds',
    ],

    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment'     => 'development',

        'development' => [
            'adapter' => 'mysql',
            'host'    => getenv('DB_HOST') ?: '127.0.0.1',
            'name'    => getenv('DB_DATABASE') ?: 'legalops',
            'user'    => getenv('DB_USERNAME') ?: 'root',
            'pass'    => getenv('DB_PASSWORD') ?: '',
            'port'    => getenv('DB_PORT')     ?: '3306',
            'charset' => 'utf8mb4',
        ],

        'testing' => [
            'adapter' => 'mysql',
            'host'    => getenv('DB_HOST') ?: '127.0.0.1',
            'name'    => (getenv('DB_DATABASE') ?: 'legalops') . '_test',
            'user'    => getenv('DB_USERNAME') ?: 'root',
            'pass'    => getenv('DB_PASSWORD') ?: '',
            'port'    => getenv('DB_PORT')     ?: '3306',
            'charset' => 'utf8mb4',
        ],

        'production' => [
            'adapter' => 'mysql',
            'host'    => getenv('DB_HOST') ?: '127.0.0.1',
            'name'    => getenv('DB_DATABASE') ?: 'legalops',
            'user'    => getenv('DB_USERNAME') ?: 'root',
            'pass'    => getenv('DB_PASSWORD') ?: '',
            'port'    => getenv('DB_PORT')     ?: '3306',
            'charset' => 'utf8mb4',
        ],
    ],

    'version_order' => 'creation',
];
