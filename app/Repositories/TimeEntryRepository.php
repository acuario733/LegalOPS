<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class TimeEntryRepository extends BaseRepository
{
    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function findForFirma(int $firmaId, array $filters = []): array
    {
        // TENANT FILTER
        $where = ['te.firma_id = :firma_id', 'te.deleted_at IS NULL'];
        $params = ['firma_id' => $firmaId];

        foreach (['caso_id', 'tarea_id', 'usuario_id'] as $field) {
            $value = filter_var($filters[$field] ?? null, FILTER_VALIDATE_INT);
            if ($value !== false && $value > 0) {
                $where[] = 'te.' . $field . ' = :' . $field;
                $params[$field] = $value;
            }
        }

        foreach (['es_facturable', 'facturado'] as $field) {
            if (array_key_exists($field, $filters) && in_array($filters[$field], [0, 1, '0', '1', false, true], true)) {
                $where[] = 'te.' . $field . ' = :' . $field;
                $params[$field] = (int) $filters[$field];
            }
        }

        foreach (['fecha_desde' => '>=', 'fecha_hasta' => '<='] as $field => $operator) {
            $value = trim((string) ($filters[$field] ?? ''));
            if ($value !== '') {
                $where[] = 'te.fecha ' . $operator . ' :' . $field;
                $params[$field] = $value;
            }
        }

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where[] = 'te.descripcion LIKE :q';
            $params['q'] = '%' . mb_substr($query, 0, 100) . '%';
        }

        $statement = $this->pdo->prepare(
            'SELECT te.*, c.titulo AS caso_titulo, ta.titulo AS tarea_titulo, u.nombre AS usuario_nombre
             FROM time_entries te
             INNER JOIN casos c ON c.id = te.caso_id AND c.firma_id = te.firma_id
             LEFT JOIN tareas ta ON ta.id = te.tarea_id AND ta.firma_id = te.firma_id
             INNER JOIN usuarios u ON u.id = te.usuario_id AND u.firma_id = te.firma_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY te.fecha DESC, te.id DESC'
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function findByCaso(int $casoId, int $firmaId): array
    {
        // TENANT FILTER
        $statement = $this->pdo->prepare(
            'SELECT te.*, ta.titulo AS tarea_titulo, u.nombre AS usuario_nombre
             FROM time_entries te
             LEFT JOIN tareas ta ON ta.id = te.tarea_id AND ta.firma_id = te.firma_id
             INNER JOIN usuarios u ON u.id = te.usuario_id AND u.firma_id = te.firma_id
             WHERE te.caso_id = :caso_id
               AND te.firma_id = :firma_id
               AND te.deleted_at IS NULL
             ORDER BY te.fecha DESC, te.id DESC'
        );
        $statement->execute(['caso_id' => $casoId, 'firma_id' => $firmaId]);

        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function findUnbilledByCaso(int $casoId, int $firmaId): array
    {
        // TENANT FILTER
        $statement = $this->pdo->prepare(
            'SELECT te.*, ta.titulo AS tarea_titulo, u.nombre AS usuario_nombre
             FROM time_entries te
             LEFT JOIN tareas ta ON ta.id = te.tarea_id AND ta.firma_id = te.firma_id
             INNER JOIN usuarios u ON u.id = te.usuario_id AND u.firma_id = te.firma_id
             WHERE te.caso_id = :caso_id
               AND te.firma_id = :firma_id
               AND te.es_facturable = 1
               AND te.facturado = 0
               AND te.deleted_at IS NULL
             ORDER BY te.fecha, te.id'
        );
        $statement->execute(['caso_id' => $casoId, 'firma_id' => $firmaId]);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id, int $firmaId): ?array
    {
        // TENANT FILTER
        $statement = $this->pdo->prepare(
            'SELECT te.*, c.titulo AS caso_titulo, ta.titulo AS tarea_titulo, u.nombre AS usuario_nombre
             FROM time_entries te
             INNER JOIN casos c ON c.id = te.caso_id AND c.firma_id = te.firma_id
             LEFT JOIN tareas ta ON ta.id = te.tarea_id AND ta.firma_id = te.firma_id
             INNER JOIN usuarios u ON u.id = te.usuario_id AND u.firma_id = te.firma_id
             WHERE te.id = :id
               AND te.firma_id = :firma_id
               AND te.deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'firma_id' => $firmaId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        // TENANT FILTER: firma_id is mandatory in the inserted row.
        $statement = $this->pdo->prepare(
            'INSERT INTO time_entries
                (firma_id, caso_id, tarea_id, usuario_id, descripcion, fecha, duracion_minutos,
                 tarifa_hora, es_facturable, facturado, honorario_id, created_at, updated_at)
             VALUES
                (:firma_id, :caso_id, :tarea_id, :usuario_id, :descripcion, :fecha, :duracion_minutos,
                 :tarifa_hora, :es_facturable, 0, NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        );
        $statement->execute([
            'firma_id' => $data['firma_id'],
            'caso_id' => $data['caso_id'],
            'tarea_id' => $data['tarea_id'],
            'usuario_id' => $data['usuario_id'],
            'descripcion' => $data['descripcion'],
            'fecha' => $data['fecha'],
            'duracion_minutos' => $data['duracion_minutos'],
            'tarifa_hora' => $data['tarifa_hora'],
            'es_facturable' => $data['es_facturable'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, int $firmaId, array $data): bool
    {
        // TENANT FILTER
        $statement = $this->pdo->prepare(
            'UPDATE time_entries
             SET caso_id = :caso_id,
                 tarea_id = :tarea_id,
                 descripcion = :descripcion,
                 fecha = :fecha,
                 duracion_minutos = :duracion_minutos,
                 tarifa_hora = :tarifa_hora,
                 es_facturable = :es_facturable,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
               AND firma_id = :firma_id
               AND deleted_at IS NULL'
        );
        $statement->execute([
            'caso_id' => $data['caso_id'],
            'tarea_id' => $data['tarea_id'],
            'descripcion' => $data['descripcion'],
            'fecha' => $data['fecha'],
            'duracion_minutos' => $data['duracion_minutos'],
            'tarifa_hora' => $data['tarifa_hora'],
            'es_facturable' => $data['es_facturable'],
            'id' => $id,
            'firma_id' => $firmaId,
        ]);

        return $statement->rowCount() > 0;
    }

    public function softDelete(int $id, int $firmaId): bool
    {
        // TENANT FILTER
        $statement = $this->pdo->prepare(
            'UPDATE time_entries
             SET deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
               AND firma_id = :firma_id
               AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'firma_id' => $firmaId]);

        return $statement->rowCount() > 0;
    }

    public function sumMinutesByCaso(int $casoId, int $firmaId): int
    {
        // TENANT FILTER
        $statement = $this->pdo->prepare(
            'SELECT COALESCE(SUM(duracion_minutos), 0)
             FROM time_entries
             WHERE caso_id = :caso_id
               AND firma_id = :firma_id
               AND deleted_at IS NULL'
        );
        $statement->execute(['caso_id' => $casoId, 'firma_id' => $firmaId]);

        return (int) $statement->fetchColumn();
    }

    /** @param list<int> $ids */
    public function markAsBilled(array $ids, int $honorarioId, int $firmaId): int
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $ids),
            static fn (int $id): bool => $id > 0
        )));
        if ($ids === []) {
            return 0;
        }

        $placeholders = [];
        $params = ['honorario_id' => $honorarioId, 'firma_id' => $firmaId];
        foreach ($ids as $index => $id) {
            $key = 'id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }

        // TENANT FILTER
        $statement = $this->pdo->prepare(
            'UPDATE time_entries
             SET facturado = 1, honorario_id = :honorario_id, updated_at = CURRENT_TIMESTAMP
             WHERE id IN (' . implode(', ', $placeholders) . ')
               AND firma_id = :firma_id
               AND es_facturable = 1
               AND facturado = 0
               AND deleted_at IS NULL'
        );
        $statement->execute($params);

        return $statement->rowCount();
    }
}
