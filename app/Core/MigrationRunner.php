<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;
use Throwable;

final class MigrationRunner
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $migrationPath
    ) {
    }

    /** @return list<array{migration: string, status: string, batch: int|null, applied_at: string|null}> */
    public function status(): array
    {
        $this->ensureMigrationTable();
        $applied = $this->appliedMigrations();
        $status = [];

        foreach ($this->migrationFiles() as $file) {
            $migration = basename($file);
            $row = $applied[$migration] ?? null;
            $status[] = [
                'migration' => $migration,
                'status' => $row === null ? 'pending' : 'applied',
                'batch' => $row === null ? null : (int) $row['batch'],
                'applied_at' => $row['applied_at'] ?? null,
            ];
        }

        return $status;
    }

    /** @return list<string> */
    public function up(): array
    {
        $this->ensureMigrationTable();
        $applied = $this->appliedMigrations();
        $batch = (int) $this->pdo->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations')->fetchColumn();
        $completed = [];

        foreach ($this->migrationFiles() as $file) {
            $migration = basename($file);
            if (isset($applied[$migration])) {
                continue;
            }

            $sql = $this->migrationSection($file, 'up');
            if ($sql === '') {
                throw new RuntimeException(sprintf('La migración "%s" no contiene una sección @up.', $migration));
            }

            $started = microtime(true);
            try {
                $this->executeStatements($sql);
                $duration = (int) round((microtime(true) - $started) * 1000);
                $statement = $this->pdo->prepare(
                    'INSERT INTO migrations (migration, batch, duration_ms, applied_at) VALUES (:migration, :batch, :duration_ms, CURRENT_TIMESTAMP)'
                );
                $statement->execute([
                    'migration' => $migration,
                    'batch' => $batch,
                    'duration_ms' => $duration,
                ]);
            } catch (Throwable $exception) {
                throw new RuntimeException(sprintf('Falló la migración "%s" y no fue registrada como aplicada.', $migration), 0, $exception);
            }

            $completed[] = $migration;
        }

        return $completed;
    }

    /** @return list<string> */
    public function down(): array
    {
        $this->ensureMigrationTable();
        $batch = (int) $this->pdo->query('SELECT COALESCE(MAX(batch), 0) FROM migrations')->fetchColumn();
        if ($batch === 0) {
            return [];
        }

        $statement = $this->pdo->prepare('SELECT migration FROM migrations WHERE batch = :batch ORDER BY id DESC');
        $statement->execute(['batch' => $batch]);
        $migrations = $statement->fetchAll(PDO::FETCH_COLUMN);
        $reverted = [];

        foreach ($migrations as $migration) {
            $migration = (string) $migration;
            $file = $this->migrationPath . DIRECTORY_SEPARATOR . $migration;
            if (!is_file($file)) {
                throw new RuntimeException(sprintf('No existe el archivo de migración "%s".', $migration));
            }

            $sql = $this->migrationSection($file, 'down');
            if ($sql === '') {
                continue;
            }

            try {
                $this->executeStatements($sql);
                $delete = $this->pdo->prepare('DELETE FROM migrations WHERE migration = :migration');
                $delete->execute(['migration' => $migration]);
            } catch (Throwable $exception) {
                throw new RuntimeException(sprintf('No fue posible revertir la migración "%s".', $migration), 0, $exception);
            }

            $reverted[] = $migration;
        }

        return $reverted;
    }

    private function ensureMigrationTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                migration VARCHAR(191) NOT NULL,
                batch INT UNSIGNED NOT NULL,
                duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
                applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_migrations_migration (migration),
                KEY idx_migrations_batch (batch)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /** @return array<string, array{batch: int|string, applied_at: string}> */
    private function appliedMigrations(): array
    {
        $rows = $this->pdo->query('SELECT migration, batch, applied_at FROM migrations ORDER BY id')->fetchAll();
        $applied = [];
        foreach ($rows as $row) {
            $applied[(string) $row['migration']] = [
                'batch' => $row['batch'],
                'applied_at' => (string) $row['applied_at'],
            ];
        }

        return $applied;
    }

    /** @return list<string> */
    private function migrationFiles(): array
    {
        $files = glob($this->migrationPath . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        sort($files, SORT_STRING);

        return array_values($files);
    }

    private function migrationSection(string $file, string $section): string
    {
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException(sprintf('No fue posible leer la migración "%s".', basename($file)));
        }

        if (!preg_match('/^\s*--\s*@up\s*$/mi', $contents, $upMatch, PREG_OFFSET_CAPTURE)) {
            return $section === 'up' ? trim($contents) : '';
        }

        $upStart = $upMatch[0][1] + strlen($upMatch[0][0]);
        $downStart = null;
        if (preg_match('/^\s*--\s*@down\s*$/mi', $contents, $downMatch, PREG_OFFSET_CAPTURE)) {
            $downStart = $downMatch[0][1];
            $downContentStart = $downStart + strlen($downMatch[0][0]);
        }

        if ($section === 'up') {
            return trim(substr($contents, $upStart, $downStart === null ? null : $downStart - $upStart));
        }

        return $downStart === null ? '' : trim(substr($contents, $downContentStart));
    }

    private function executeStatements(string $sql): void
    {
        foreach ($this->splitStatements($sql) as $statement) {
            $this->pdo->exec($statement);
        }
    }

    /** @return list<string> */
    private function splitStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $quote = null;
        $lineComment = false;
        $blockComment = false;
        $length = strlen($sql);

        for ($index = 0; $index < $length; $index++) {
            $character = $sql[$index];
            $next = $index + 1 < $length ? $sql[$index + 1] : '';

            if ($lineComment) {
                if ($character === "\n") {
                    $lineComment = false;
                    $buffer .= $character;
                }
                continue;
            }

            if ($blockComment) {
                if ($character === '*' && $next === '/') {
                    $blockComment = false;
                    $index++;
                }
                continue;
            }

            if ($quote !== null) {
                $buffer .= $character;
                if ($character === '\\' && $next !== '') {
                    $buffer .= $next;
                    $index++;
                    continue;
                }
                if ($character === $quote) {
                    if ($next === $quote) {
                        $buffer .= $next;
                        $index++;
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }

            if (($character === '-' && $next === '-' && ($index + 2 >= $length || ctype_space($sql[$index + 2]))) || $character === '#') {
                $lineComment = true;
                $index += $character === '-' ? 1 : 0;
                continue;
            }
            if ($character === '/' && $next === '*') {
                $blockComment = true;
                $index++;
                continue;
            }
            if (in_array($character, ["'", '"', '`'], true)) {
                $quote = $character;
                $buffer .= $character;
                continue;
            }
            if ($character === ';') {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $character;
        }

        $statement = trim($buffer);
        if ($statement !== '') {
            $statements[] = $statement;
        }

        return $statements;
    }
}

