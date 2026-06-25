<?php

declare(strict_types=1);

namespace App\Repositories;

final class CasoTimelineRepository extends BaseRepository
{
    /** @return list<array<string, mixed>> */
    public function allForCase(int $firmaId, int $casoId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT t.*, u.nombre AS autor_nombre
             FROM caso_timeline t
             LEFT JOIN usuarios u ON u.id=t.autor_usuario_id
             WHERE t.firma_id=:firma_id AND t.caso_id=:caso_id AND t.deleted_at IS NULL
             ORDER BY t.fecha_evento DESC, t.id DESC'
        );
        $statement->execute(['firma_id' => $firmaId, 'caso_id' => $casoId]);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findForCase(int $firmaId, int $casoId, int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM caso_timeline
             WHERE id=:id AND firma_id=:firma_id AND caso_id=:caso_id AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'firma_id' => $firmaId, 'caso_id' => $casoId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO caso_timeline
            (firma_id,caso_id,autor_usuario_id,fecha_evento,tipo_evento,titulo,contenido_publico,
             contenido_interno,visibilidad,critico,documento_id,estado,created_at,updated_at)
             VALUES
            (:firma_id,:caso_id,:autor_usuario_id,:fecha_evento,:tipo_evento,:titulo,:contenido_publico,
             :contenido_interno,:visibilidad,:critico,:documento_id,:estado,CURRENT_TIMESTAMP(6),CURRENT_TIMESTAMP(6))'
        );
        $statement->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $firmaId, int $casoId, int $id, array $data): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE caso_timeline
             SET fecha_evento=:fecha_evento,tipo_evento=:tipo_evento,titulo=:titulo,
                 contenido_publico=:contenido_publico,contenido_interno=:contenido_interno,
                 visibilidad=:visibilidad,critico=:critico,documento_id=:documento_id,estado=:estado,
                 updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id AND firma_id=:firma_id AND caso_id=:caso_id AND deleted_at IS NULL'
        );
        $statement->execute($data + ['id' => $id, 'firma_id' => $firmaId, 'caso_id' => $casoId]);
    }

    public function setVisibility(int $firmaId, int $casoId, int $id, string $visibility): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE caso_timeline SET visibilidad=:visibilidad,updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id AND firma_id=:firma_id AND caso_id=:caso_id AND deleted_at IS NULL'
        );
        $statement->execute(['visibilidad' => $visibility, 'id' => $id, 'firma_id' => $firmaId, 'caso_id' => $casoId]);
    }

    public function softDelete(int $firmaId, int $casoId, int $id): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE caso_timeline SET deleted_at=CURRENT_TIMESTAMP(6),updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id AND firma_id=:firma_id AND caso_id=:caso_id AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'firma_id' => $firmaId, 'caso_id' => $casoId]);
    }
}
