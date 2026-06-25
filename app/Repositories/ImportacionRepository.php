<?php

declare(strict_types=1);

namespace App\Repositories;

final class ImportacionRepository extends BaseRepository
{
    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO importaciones
            (firma_id,usuario_id,tipo,estado,archivo_path,nombre_original,filas_total,filas_validas,filas_error,errores_json,resultado_json,expires_at,created_at,updated_at)
             VALUES
            (:firma_id,:usuario_id,:tipo,:estado,:archivo_path,:nombre_original,:filas_total,:filas_validas,:filas_error,:errores_json,:resultado_json,:expires_at,CURRENT_TIMESTAMP(6),CURRENT_TIMESTAMP(6))'
        );
        $statement->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function findForFirma(int $firmaId, int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM importaciones WHERE id=:id AND firma_id=:firma_id');
        $statement->execute(['id' => $id, 'firma_id' => $firmaId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $firmaId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM importaciones WHERE firma_id=:firma_id ORDER BY created_at DESC LIMIT 50');
        $statement->execute(['firma_id' => $firmaId]);

        return $statement->fetchAll();
    }

    /** @param array<string, mixed> $result */
    public function markConfirmed(int $firmaId, int $id, array $result): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE importaciones SET estado=\'confirmada\', resultado_json=:resultado_json, confirmed_at=CURRENT_TIMESTAMP(6), updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id AND firma_id=:firma_id AND estado=\'previsualizada\''
        );
        $statement->execute(['resultado_json' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'id' => $id, 'firma_id' => $firmaId]);
    }

    public function cleanupExpired(string $storageRoot): int
    {
        $statement = $this->pdo->query('SELECT id, archivo_path FROM importaciones WHERE expires_at IS NOT NULL AND expires_at < CURRENT_TIMESTAMP(6) AND archivo_path IS NOT NULL');
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
        $this->pdo->exec('UPDATE importaciones SET estado=\'expirada\', archivo_path=NULL, updated_at=CURRENT_TIMESTAMP(6) WHERE estado=\'previsualizada\' AND expires_at IS NOT NULL AND expires_at < CURRENT_TIMESTAMP(6)');

        return $deleted;
    }
}
