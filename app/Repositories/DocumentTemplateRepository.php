<?php

declare(strict_types=1);

namespace App\Repositories;

final class DocumentTemplateRepository extends BaseRepository
{
    /** @return list<array<string, mixed>> */
    public function findForFirma(int $firmaId, ?string $categoria = null): array
    {
        // TENANT FILTER
        $where = ['firma_id = :firma_id', 'deleted_at IS NULL'];
        $params = ['firma_id' => $firmaId];
        if ($categoria !== null && trim($categoria) !== '') {
            $where[] = 'categoria = :categoria';
            $params['categoria'] = $categoria;
        }

        $statement = $this->pdo->prepare(
            'SELECT *
             FROM document_templates
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY categoria ASC, nombre ASC, id ASC'
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id, int $firmaId): ?array
    {
        // TENANT FILTER
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM document_templates
             WHERE id = :id AND firma_id = :firma_id AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'firma_id' => $firmaId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO document_templates
                (firma_id, nombre, descripcion, categoria, contenido, variables_usadas,
                 activo, created_at, updated_at)
             VALUES
                (:firma_id, :nombre, :descripcion, :categoria, :contenido, :variables_usadas,
                 :activo, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        );
        $statement->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, int $firmaId, array $data): bool
    {
        // TENANT FILTER
        $statement = $this->pdo->prepare(
            'UPDATE document_templates
             SET nombre = :nombre,
                 descripcion = :descripcion,
                 categoria = :categoria,
                 contenido = :contenido,
                 variables_usadas = :variables_usadas,
                 activo = :activo,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND firma_id = :firma_id AND deleted_at IS NULL'
        );
        $statement->execute($data + ['id' => $id, 'firma_id' => $firmaId]);

        return $statement->rowCount() > 0;
    }

    public function softDelete(int $id, int $firmaId): bool
    {
        // TENANT FILTER
        $statement = $this->pdo->prepare(
            'UPDATE document_templates
             SET deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND firma_id = :firma_id AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'firma_id' => $firmaId]);

        return $statement->rowCount() > 0;
    }

    /** @return list<string> */
    public function getCategorias(int $firmaId): array
    {
        // TENANT FILTER
        $statement = $this->pdo->prepare(
            'SELECT DISTINCT categoria
             FROM document_templates
             WHERE firma_id = :firma_id AND deleted_at IS NULL AND categoria IS NOT NULL AND categoria <> \'\'
             ORDER BY categoria ASC'
        );
        $statement->execute(['firma_id' => $firmaId]);

        return array_map('strval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    }
}
