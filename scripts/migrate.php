<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Core\MigrationRunner;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (PHP_VERSION_ID < 80200) {
    fwrite(STDERR, "Se requiere PHP 8.2 o superior.\n");
    exit(1);
}

$basePath = dirname(__DIR__);
$autoload = $basePath . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Autoload no disponible. Ejecute composer dump-autoload.\n");
    exit(1);
}

require $autoload;

try {
    Config::load($basePath);
    $database = new Database((array) Config::get('database', []));
    $runner = new MigrationRunner($database->connection(), $basePath . '/database/migrations');
    $command = strtolower((string) ($argv[1] ?? 'status'));

    if ($command === 'status') {
        foreach ($runner->status() as $row) {
            printf("%-45s %-10s batch=%s applied_at=%s\n", $row['migration'], $row['status'], $row['batch'] ?? '-', $row['applied_at'] ?? '-');
        }
        exit(0);
    }

    if ($command === 'up') {
        $migrations = $runner->up();
        echo $migrations === []
            ? "No hay migraciones pendientes.\n"
            : "Migraciones aplicadas:\n- " . implode("\n- ", $migrations) . "\n";
        exit(0);
    }

    if ($command === 'down') {
        $migrations = $runner->down();
        echo $migrations === []
            ? "El último lote no tiene una reversión automática disponible.\n"
            : "Migraciones revertidas:\n- " . implode("\n- ", $migrations) . "\n";
        exit(0);
    }

    fwrite(STDERR, "Comando no válido. Use status, up o down.\n");
    exit(1);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Error de migración: ' . $exception->getMessage() . "\n");
    exit(1);
}
