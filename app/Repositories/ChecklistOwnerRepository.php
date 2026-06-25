<?php

declare(strict_types=1);

namespace App\Repositories;

final class ChecklistOwnerRepository extends BaseRepository
{
    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->pdo->query(
            'SELECT co.*, f.nombre AS firma_nombre, u.nombre AS decidido_por_nombre
             FROM checklist_owner co
             LEFT JOIN firmas f ON f.id=co.firma_id
             LEFT JOIN usuarios u ON u.id=co.decidido_por_usuario_id
             ORDER BY co.created_at DESC'
        )->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM checklist_owner WHERE id=:id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function items(int $checklistId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM checklist_owner_items WHERE checklist_id=:id ORDER BY id');
        $statement->execute(['id' => $checklistId]);

        return $statement->fetchAll();
    }

    public function create(?int $firmaId, string $title, ?int $userId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO checklist_owner (firma_id,titulo,estado,created_by_usuario_id,created_at,updated_at)
             VALUES (:firma_id,:titulo,\'pendiente\',:usuario_id,CURRENT_TIMESTAMP(6),CURRENT_TIMESTAMP(6))'
        );
        $statement->execute(['firma_id' => $firmaId, 'titulo' => $title, 'usuario_id' => $userId]);

        return (int) $this->pdo->lastInsertId();
    }

    public function addItem(int $checklistId, string $code, string $title): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO checklist_owner_items (checklist_id,codigo,titulo,estado,created_at,updated_at)
             VALUES (:checklist_id,:codigo,:titulo,\'pendiente\',CURRENT_TIMESTAMP(6),CURRENT_TIMESTAMP(6))
             ON DUPLICATE KEY UPDATE titulo=VALUES(titulo)'
        );
        $statement->execute(['checklist_id' => $checklistId, 'codigo' => $code, 'titulo' => $title]);
    }

    public function evaluateItem(int $checklistId, int $itemId, string $status, ?string $observation, ?string $evidence, ?int $userId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE checklist_owner_items
             SET estado=:estado,observacion=:observacion,evidencia=:evidencia,evaluado_por_usuario_id=:usuario_id,evaluado_at=CURRENT_TIMESTAMP(6),updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id AND checklist_id=:checklist_id'
        );
        $statement->execute(['estado' => $status, 'observacion' => $observation, 'evidencia' => $evidence, 'usuario_id' => $userId, 'id' => $itemId, 'checklist_id' => $checklistId]);
    }

    public function decide(int $id, string $decision, string $observation, ?int $userId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE checklist_owner
             SET estado=\'decidido\',decision=:decision,decision_observacion=:observacion,decidido_por_usuario_id=:usuario_id,decidido_at=CURRENT_TIMESTAMP(6),updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id'
        );
        $statement->execute(['decision' => $decision, 'observacion' => $observation, 'usuario_id' => $userId, 'id' => $id]);
    }
}
