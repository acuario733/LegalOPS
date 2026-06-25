<?php

declare(strict_types=1);

namespace App\Repositories;

final class OnboardingRepository extends BaseRepository
{
    /** @return list<array<string, mixed>> */
    public function progress(int $firmaId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM onboarding_progreso WHERE firma_id=:firma_id ORDER BY paso');
        $statement->execute(['firma_id' => $firmaId]);

        return $statement->fetchAll();
    }

    public function upsert(int $firmaId, string $step, string $status, ?int $userId, array $metadata = []): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO onboarding_progreso (firma_id,paso,estado,completed_at,completed_by_usuario_id,metadata_json,created_at,updated_at)
             VALUES (:firma_id,:paso,:estado,:completed_at,:usuario_id,:metadata,CURRENT_TIMESTAMP(6),CURRENT_TIMESTAMP(6))
             ON DUPLICATE KEY UPDATE
                estado=VALUES(estado),
                completed_at=CASE
                    WHEN VALUES(estado)=\'completado\' AND onboarding_progreso.estado<>\'completado\' THEN VALUES(completed_at)
                    WHEN VALUES(estado)<>\'completado\' THEN NULL
                    ELSE onboarding_progreso.completed_at
                END,
                completed_by_usuario_id=CASE
                    WHEN VALUES(estado)=\'completado\' AND onboarding_progreso.estado<>\'completado\' THEN VALUES(completed_by_usuario_id)
                    WHEN VALUES(estado)<>\'completado\' THEN NULL
                    ELSE onboarding_progreso.completed_by_usuario_id
                END,
                metadata_json=VALUES(metadata_json),
                updated_at=CURRENT_TIMESTAMP(6)'
        );
        $statement->execute([
            'firma_id' => $firmaId,
            'paso' => $step,
            'estado' => $status,
            'completed_at' => $status === 'completado' ? date('Y-m-d H:i:s') : null,
            'usuario_id' => $status === 'completado' ? $userId : null,
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function countWhere(string $table, int $firmaId, string $condition = 'deleted_at IS NULL'): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE firma_id=:firma_id AND ' . $condition);
        $statement->execute(['firma_id' => $firmaId]);

        return (int) $statement->fetchColumn();
    }

    public function hasPortalUser(int $firmaId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM portal_usuario_clientes puc
             INNER JOIN usuarios u ON u.id=puc.usuario_id AND u.firma_id=puc.firma_id
             INNER JOIN clientes c ON c.id=puc.cliente_id AND c.firma_id=puc.firma_id
             WHERE puc.firma_id=:firma_id AND puc.estado=\'activo\'
               AND u.tipo=\'cliente_externo\' AND u.estado=\'activo\' AND u.deleted_at IS NULL
               AND c.estado=\'activo\' AND c.deleted_at IS NULL'
        );
        $statement->execute(['firma_id' => $firmaId]);

        return (int) $statement->fetchColumn() > 0;
    }
}
