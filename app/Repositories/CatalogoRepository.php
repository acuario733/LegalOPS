<?php

declare(strict_types=1);

namespace App\Repositories;

final class CatalogoRepository extends BaseRepository
{
    /** @return list<array<string, mixed>> */
    public function visibleForFirma(int $firmaId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT c.id,c.firma_id,c.alcance,c.codigo,c.nombre,c.estado,c.created_at,COUNT(ci.id) AS items
             FROM catalogos c LEFT JOIN catalogo_items ci ON ci.catalogo_id=c.id AND ci.deleted_at IS NULL
             WHERE (c.firma_id=:firma_id OR c.firma_id IS NULL) AND c.deleted_at IS NULL
             GROUP BY c.id,c.firma_id,c.alcance,c.codigo,c.nombre,c.estado,c.created_at
             ORDER BY c.alcance,c.nombre'
        );
        $statement->execute(['firma_id' => $firmaId]);

        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function allGlobal(): array
    {
        return $this->pdo->query(
            'SELECT c.id,c.firma_id,c.alcance,c.codigo,c.nombre,c.estado,c.created_at,COUNT(ci.id) AS items
             FROM catalogos c LEFT JOIN catalogo_items ci ON ci.catalogo_id=c.id AND ci.deleted_at IS NULL
             WHERE c.firma_id IS NULL AND c.deleted_at IS NULL
             GROUP BY c.id,c.firma_id,c.alcance,c.codigo,c.nombre,c.estado,c.created_at
             ORDER BY c.alcance,c.nombre'
        )->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findVisible(int $firmaId, int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM catalogos WHERE id=:id AND (firma_id=:firma_id OR firma_id IS NULL) AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'firma_id' => $firmaId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO catalogos (firma_id,alcance,codigo,scope_key,nombre,estado,created_at,updated_at)
             VALUES (:firma_id,:alcance,:codigo,:scope_key,:nombre,\'activo\',CURRENT_TIMESTAMP(6),CURRENT_TIMESTAMP(6))'
        );
        $statement->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, ?int $firmaId, array $data): bool
    {
        $sql = 'UPDATE catalogos SET nombre=:nombre,estado=:estado,updated_at=CURRENT_TIMESTAMP(6) WHERE id=:id AND deleted_at IS NULL';
        $params = $data + ['id' => $id];
        if ($firmaId === null) {
            $sql .= ' AND firma_id IS NULL';
        } else {
            $sql .= ' AND firma_id=:firma_id';
            $params['firma_id'] = $firmaId;
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->rowCount() > 0;
    }

    /** @return list<array<string, mixed>> */
    public function items(int $catalogId, int $firmaId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT ci.id,ci.codigo,ci.etiqueta,ci.orden,ci.estado,ci.firma_id
             FROM catalogo_items ci INNER JOIN catalogos c ON c.id=ci.catalogo_id
             WHERE ci.catalogo_id=:catalogo_id AND ci.deleted_at IS NULL
               AND (c.firma_id=:firma_id OR c.firma_id IS NULL)
             ORDER BY ci.orden,ci.etiqueta'
        );
        $statement->execute(['catalogo_id' => $catalogId, 'firma_id' => $firmaId]);

        return $statement->fetchAll();
    }

    /** @param array<string, mixed> $data */
    public function createItem(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO catalogo_items (firma_id,catalogo_id,codigo,etiqueta,orden,estado,created_at,updated_at)
             VALUES (:firma_id,:catalogo_id,:codigo,:etiqueta,:orden,\'activo\',CURRENT_TIMESTAMP(6),CURRENT_TIMESTAMP(6))'
        );
        $statement->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function findItem(int $itemId, int $catalogId, ?int $firmaId): ?array
    {
        $sql = 'SELECT * FROM catalogo_items WHERE id=:id AND catalogo_id=:catalogo_id AND deleted_at IS NULL';
        $params = ['id' => $itemId, 'catalogo_id' => $catalogId];
        if ($firmaId === null) {
            $sql .= ' AND firma_id IS NULL';
        } else {
            $sql .= ' AND firma_id=:firma_id';
            $params['firma_id'] = $firmaId;
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param array{etiqueta: string, orden: int} $data */
    public function updateItem(int $itemId, int $catalogId, ?int $firmaId, array $data): bool
    {
        $sql = 'UPDATE catalogo_items SET etiqueta=:etiqueta,orden=:orden,updated_at=CURRENT_TIMESTAMP(6)
                WHERE id=:id AND catalogo_id=:catalogo_id AND deleted_at IS NULL';
        $params = $data + ['id' => $itemId, 'catalogo_id' => $catalogId];
        if ($firmaId === null) {
            $sql .= ' AND firma_id IS NULL';
        } else {
            $sql .= ' AND firma_id=:firma_id';
            $params['firma_id'] = $firmaId;
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->rowCount() > 0;
    }

    public function setItemStatus(int $itemId, int $catalogId, ?int $firmaId, string $status): bool
    {
        $sql = 'UPDATE catalogo_items SET estado=:estado,updated_at=CURRENT_TIMESTAMP(6)
                WHERE id=:id AND catalogo_id=:catalogo_id AND deleted_at IS NULL';
        $params = ['estado' => $status, 'id' => $itemId, 'catalogo_id' => $catalogId];
        if ($firmaId === null) {
            $sql .= ' AND firma_id IS NULL';
        } else {
            $sql .= ' AND firma_id=:firma_id';
            $params['firma_id'] = $firmaId;
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->rowCount() > 0;
    }
}
