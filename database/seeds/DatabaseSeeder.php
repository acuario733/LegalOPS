<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * Seeder maestro — ejecuta todos los seeders en orden correcto de dependencias.
 *
 * Uso:
 *   vendor/bin/phinx seed:run -e development
 */
final class DatabaseSeeder extends AbstractSeed
{
    public function getDependencies(): array
    {
        return [
            'FirmaSeeder',
            'UserSeeder',
            'ClienteSeeder',
            'ProspectoSeeder',
            'CasoSeeder',
            'TareaSeeder',
        ];
    }

    public function run(): void
    {
        // El maestro solo declara dependencias; Phinx corre los seeders hijos.
    }
}
