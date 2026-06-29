<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class TerminoRepository extends BaseRepository
{
    /** @param array<string, mixed> $filters @return array{items: list<array<string, mixed>>, total: int} */
    public function paginate(int $firmaId, array $filters, int $page = 1, int $perPage = 25): array
    {
        $where = ['t.firma_id=:firma_id', 't.deleted_at IS NULL'];
        $params = ['firma_id' => $firmaId];
        $this->applyStateFilter($where, $params, (string) ($filters['estado'] ?? ''));
        if (($filters['caso_id'] ?? 0) > 0) {
            $where[] = 't.caso_id=:caso_id';
            $params['caso_id'] = $filters['caso_id'];
        }
        if (($filters['tarea_id'] ?? 0) > 0) {
            $where[] = 't.tarea_id=:tarea_id';
            $params['tarea_id'] = $filters['tarea_id'];
        }
        if (($filters['responsable_usuario_id'] ?? 0) > 0) {
            $where[] = 't.responsable_usuario_id=:responsable_usuario_id';
            $params['responsable_usuario_id'] = $filters['responsable_usuario_id'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(t.titulo_normalizado LIKE :q OR t.descripcion LIKE :q_raw)';
            $params['q'] = '%' . $filters['q'] . '%';
            $params['q_raw'] = '%' . $filters['q_raw'] . '%';
        }

        $sqlWhere = ' WHERE ' . implode(' AND ', $where);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM terminos t' . $sqlWhere);
        $count->execute($params);

        $offset = max(0, ($page - 1) * $perPage);
        $query = $this->pdo->prepare(
            'SELECT t.*, c.titulo AS caso_titulo, u.nombre AS responsable_nombre
             FROM terminos t
             LEFT JOIN casos c ON c.id=t.caso_id AND c.firma_id=t.firma_id
             LEFT JOIN usuarios u ON u.id=t.responsable_usuario_id AND u.firma_id=t.firma_id' . $sqlWhere . '
             ORDER BY FIELD(t.estado, \'vencido\',\'critico\',\'proximo\',\'vigente\',\'cumplido\'), t.fecha_vencimiento ASC, t.id DESC
             LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $query->bindValue(':' . $key, $value);
        }
        $query->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();

        return ['items' => $query->fetchAll(), 'total' => (int) $count->fetchColumn()];
    }

    /** @return list<array<string, mixed>> */
    public function allForSelect(int $firmaId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, caso_id, tarea_id, titulo, fecha_vencimiento, estado
             FROM terminos
             WHERE firma_id=:firma_id AND deleted_at IS NULL
             ORDER BY fecha_vencimiento ASC, titulo ASC
             LIMIT 200'
        );
        $statement->execute(['firma_id' => $firmaId]);

        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function searchForSelect(int $firmaId, string $query, ?int $casoId = null, int $limit = 20): array
    {
        $where = ['t.firma_id=:firma_id', 't.deleted_at IS NULL'];
        $params = ['firma_id' => $firmaId];
        if ($casoId !== null) {
            $where[] = 't.caso_id=:caso_id';
            $params['caso_id'] = $casoId;
        }
        $normalized = preg_replace('/\s+/', ' ', mb_strtolower(trim($query))) ?? '';
        $raw = mb_substr(trim($query), 0, 180);
        if ($normalized !== '' || $raw !== '') {
            $where[] = '(t.titulo_normalizado LIKE :q OR t.descripcion LIKE :raw OR c.titulo_normalizado LIKE :q)';
            $params['q'] = '%' . mb_substr($normalized, 0, 180) . '%';
            $params['raw'] = '%' . $raw . '%';
        }
        $statement = $this->pdo->prepare(
            'SELECT t.id,t.caso_id,t.titulo,t.fecha_vencimiento,t.estado,c.titulo AS caso_titulo
             FROM terminos t
             LEFT JOIN casos c ON c.id=t.caso_id AND c.firma_id=t.firma_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY t.fecha_vencimiento ASC, t.id DESC
             LIMIT :limit'
        );
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', max(1, min(50, $limit)), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findForFirma(int $firmaId, int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT t.*, c.titulo AS caso_titulo, ta.titulo AS tarea_titulo, u.nombre AS responsable_nombre
             FROM terminos t
             LEFT JOIN casos c ON c.id=t.caso_id AND c.firma_id=t.firma_id
             LEFT JOIN tareas ta ON ta.id=t.tarea_id AND ta.firma_id=t.firma_id
             LEFT JOIN usuarios u ON u.id=t.responsable_usuario_id AND u.firma_id=t.firma_id
             WHERE t.id=:id AND t.firma_id=:firma_id AND t.deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'firma_id' => $firmaId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO terminos
            (firma_id,caso_id,tarea_id,responsable_usuario_id,titulo,titulo_normalizado,descripcion,fecha_inicio,
             fecha_vencimiento,timezone,prioridad,estado,alerta_dias,created_at,updated_at)
             VALUES
            (:firma_id,:caso_id,:tarea_id,:responsable_usuario_id,:titulo,:titulo_normalizado,:descripcion,:fecha_inicio,
             :fecha_vencimiento,:timezone,:prioridad,:estado,:alerta_dias,CURRENT_TIMESTAMP(6),CURRENT_TIMESTAMP(6))'
        );
        $statement->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $firmaId, int $id, array $data): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE terminos
             SET caso_id=:caso_id,tarea_id=:tarea_id,responsable_usuario_id=:responsable_usuario_id,
                 titulo=:titulo,titulo_normalizado=:titulo_normalizado,descripcion=:descripcion,
                 fecha_inicio=:fecha_inicio,fecha_vencimiento=:fecha_vencimiento,timezone=:timezone,
                 prioridad=:prioridad,estado=:estado,alerta_dias=:alerta_dias,updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute($data + ['id' => $id, 'firma_id' => $firmaId]);
    }

    public function setTask(int $firmaId, int $id, ?int $taskId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE terminos SET tarea_id=:tarea_id, updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute(['tarea_id' => $taskId, 'id' => $id, 'firma_id' => $firmaId]);
    }

    public function clearTaskIfMatches(int $firmaId, int $id, int $taskId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE terminos SET tarea_id=NULL, updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id AND firma_id=:firma_id AND tarea_id=:tarea_id AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'firma_id' => $firmaId, 'tarea_id' => $taskId]);
    }

    public function markFulfilled(int $firmaId, int $id, int $userId, ?string $observation): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE terminos
             SET estado=\'cumplido\', cumplido_at=CURRENT_TIMESTAMP(6), cumplido_por_usuario_id=:usuario_id,
                 observacion_cumplimiento=:observacion, updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute(['usuario_id' => $userId, 'observacion' => $observation, 'id' => $id, 'firma_id' => $firmaId]);
    }

    public function softDelete(int $firmaId, int $id): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE terminos SET deleted_at=CURRENT_TIMESTAMP(6), updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'firma_id' => $firmaId]);
    }

    /** @param list<string> $where @param array<string, mixed> $params */
    private function applyStateFilter(array &$where, array &$params, string $state): void
    {
        match ($state) {
            'cumplido' => $where[] = 't.estado=\'cumplido\'',
            'vencido' => $where[] = 't.estado<>\'cumplido\' AND t.fecha_vencimiento < CURRENT_DATE()',
            'critico' => $where[] = 't.estado<>\'cumplido\' AND t.fecha_vencimiento >= CURRENT_DATE() AND t.fecha_vencimiento <= DATE_ADD(CURRENT_DATE(), INTERVAL t.alerta_dias DAY)',
            'proximo' => $where[] = 't.estado<>\'cumplido\' AND t.fecha_vencimiento > DATE_ADD(CURRENT_DATE(), INTERVAL t.alerta_dias DAY) AND t.fecha_vencimiento <= DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY)',
            'vigente' => $where[] = 't.estado=\'vigente\' AND t.fecha_vencimiento > DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY)',
            default => null,
        };
    }
}
