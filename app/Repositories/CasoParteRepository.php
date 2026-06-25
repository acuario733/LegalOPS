<?php

declare(strict_types=1);

namespace App\Repositories;

final class CasoParteRepository extends BaseRepository
{
    /** @return list<array<string, mixed>> */
    public function allForCase(int $firmaId, int $casoId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM caso_partes
             WHERE firma_id=:firma_id AND caso_id=:caso_id AND deleted_at IS NULL
             ORDER BY tipo_parte, nombre'
        );
        $statement->execute(['firma_id' => $firmaId, 'caso_id' => $casoId]);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findForCase(int $firmaId, int $casoId, int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM caso_partes
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
            'INSERT INTO caso_partes
            (firma_id,caso_id,tipo_parte,nombre,nombre_normalizado,tipo_documento,numero_documento,
             documento_hash,email,telefono,direccion,estado,observaciones,created_at,updated_at)
             VALUES
            (:firma_id,:caso_id,:tipo_parte,:nombre,:nombre_normalizado,:tipo_documento,:numero_documento,
             :documento_hash,:email,:telefono,:direccion,:estado,:observaciones,CURRENT_TIMESTAMP(6),CURRENT_TIMESTAMP(6))'
        );
        $statement->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $firmaId, int $casoId, int $id, array $data): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE caso_partes
             SET tipo_parte=:tipo_parte,nombre=:nombre,nombre_normalizado=:nombre_normalizado,
                 tipo_documento=:tipo_documento,numero_documento=:numero_documento,documento_hash=:documento_hash,
                 email=:email,telefono=:telefono,direccion=:direccion,estado=:estado,observaciones=:observaciones,
                 updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id AND firma_id=:firma_id AND caso_id=:caso_id AND deleted_at IS NULL'
        );
        $statement->execute($data + ['id' => $id, 'firma_id' => $firmaId, 'caso_id' => $casoId]);
    }

    public function softDelete(int $firmaId, int $casoId, int $id): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE caso_partes SET deleted_at=CURRENT_TIMESTAMP(6), updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id AND firma_id=:firma_id AND caso_id=:caso_id AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'firma_id' => $firmaId, 'caso_id' => $casoId]);
    }
}
