<?php

declare(strict_types=1);

namespace App\Repositories;

final class ExportacionRepository extends BaseRepository
{
    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO exportaciones (firma_id,usuario_id,tipo,filtros_json,estado,archivo_path,filas,expires_at,created_at)
             VALUES (:firma_id,:usuario_id,:tipo,:filtros_json,:estado,:archivo_path,:filas,:expires_at,CURRENT_TIMESTAMP(6))'
        );
        $statement->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    public function cleanupExpired(string $storageRoot): int
    {
        $statement = $this->pdo->query('SELECT id, archivo_path FROM exportaciones WHERE expires_at IS NOT NULL AND expires_at < CURRENT_TIMESTAMP(6) AND archivo_path IS NOT NULL');
        $rows = $statement->fetchAll();
        $deleted = 0;
        foreach ($rows as $row) {
            $relative = (string) $row['archivo_path'];
            if (str_contains($relative, '..') || str_starts_with($relative, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $relative) === 1) {
                continue;
            }
            $path = $storageRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $root = realpath($storageRoot);
            $real = is_file($path) ? realpath($path) : false;
            if ($root === false || ($real !== false && !str_starts_with($real, $root))) {
                continue;
            }
            if (is_file($path) && @unlink($path)) {
                $deleted++;
            }
        }
        $this->pdo->exec('UPDATE exportaciones SET estado=\'expirada\', archivo_path=NULL WHERE expires_at IS NOT NULL AND expires_at < CURRENT_TIMESTAMP(6)');

        return $deleted;
    }
}
