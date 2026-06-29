<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Template de Repository para un módulo CRUD estándar.
 *
 * Patrón del proyecto: todos los métodos reciben $firmaId como primer argumento.
 * Esto garantiza aislamiento multi-tenant en TODOS los queries.
 *
 * Extender BaseRepository para acceder a $this->pdo y helpers comunes.
 */
final class ExampleRepository extends BaseRepository
{
    /**
     * Lista paginada filtrada por firma.
     *
     * @param array<string, mixed> $filters
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function paginate(int $firmaId, array $filters = [], int $page = 1, int $perPage = 25): array
    {
        // TENANT FILTER: firma_id = ? — siempre presente
        $where  = ['firma_id = :firma_id', 'deleted_at IS NULL'];
        $params = ['firma_id' => $firmaId];

        // Filtros opcionales
        if (!empty($filters['estado'])) {
            $where[]          = 'estado = :estado';
            $params['estado'] = $filters['estado'];
        }
        if (!empty($filters['q'])) {
            $where[]      = 'titulo LIKE :q';
            $params['q']  = '%' . $filters['q'] . '%';
        }

        $sqlWhere = ' WHERE ' . implode(' AND ', $where);

        // Contar total para paginación
        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM documentos' . $sqlWhere);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Obtener página
        $offset   = max(0, ($page - 1) * $perPage);
        $dataStmt = $this->pdo->prepare(
            'SELECT id, firma_id, titulo, descripcion, estado, created_at, updated_at
               FROM documentos' . $sqlWhere .
            ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset'
        );
        $dataStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            $dataStmt->bindValue(":$key", $value);
        }
        $dataStmt->execute();

        return [
            'items' => $dataStmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total' => $total,
        ];
    }

    /**
     * Buscar por ID dentro de la firma.
     *
     * Devuelve null si no existe O si pertenece a otra firma (seguridad crítica).
     *
     * @return array<string, mixed>|null
     */
    public function find(int $firmaId, int $id): ?array
    {
        // TENANT FILTER: firma_id = ? — garantiza que no hay acceso cross-tenant
        $stmt = $this->pdo->prepare(
            'SELECT * FROM documentos WHERE firma_id = ? AND id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$firmaId, $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * Crear un nuevo registro.
     *
     * @param array<string, mixed> $data
     */
    public function create(int $firmaId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO documentos (firma_id, titulo, descripcion, estado, created_at, updated_at)
             VALUES (:firma_id, :titulo, :descripcion, :estado, NOW(), NOW())'
        );
        $stmt->execute([
            // TENANT FILTER: firma_id siempre se asigna aquí, nunca viene del input del usuario
            'firma_id'    => $firmaId,
            'titulo'      => $data['titulo'] ?? '',
            'descripcion' => $data['descripcion'] ?? '',
            'estado'      => $data['estado'] ?? 'activo',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Actualizar un registro existente.
     *
     * Devuelve false si no existe o pertenece a otra firma.
     *
     * @param array<string, mixed> $data
     */
    public function update(int $firmaId, int $id, array $data): bool
    {
        $fields = [];
        $params = [
            // TENANT FILTER: asegura que solo actualizamos registros de la firma correcta
            'firma_id' => $firmaId,
            'id'       => $id,
        ];

        if (isset($data['titulo'])) {
            $fields[] = 'titulo = :titulo';
            $params['titulo'] = $data['titulo'];
        }
        if (isset($data['descripcion'])) {
            $fields[] = 'descripcion = :descripcion';
            $params['descripcion'] = $data['descripcion'];
        }
        if (isset($data['estado'])) {
            $fields[] = 'estado = :estado';
            $params['estado'] = $data['estado'];
        }

        if (empty($fields)) {
            return false; // Nada que actualizar
        }

        $fields[] = 'updated_at = NOW()';

        $stmt = $this->pdo->prepare(
            'UPDATE documentos SET ' . implode(', ', $fields) .
            ' WHERE firma_id = :firma_id AND id = :id AND deleted_at IS NULL'
        );
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    /**
     * Soft delete — marca deleted_at, no elimina físicamente.
     *
     * Devuelve false si no existe o pertenece a otra firma.
     */
    public function delete(int $firmaId, int $id): bool
    {
        // TENANT FILTER: firma_id garantiza que no podemos borrar de otra firma
        $stmt = $this->pdo->prepare(
            'UPDATE documentos SET deleted_at = NOW()
             WHERE firma_id = ? AND id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$firmaId, $id]);

        return $stmt->rowCount() > 0;
    }
}
