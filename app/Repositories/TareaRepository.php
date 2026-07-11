<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class TareaRepository extends BaseRepository
{
    /** @param array<string, mixed> $filters @return array{items: list<array<string, mixed>>, total: int} */
    public function paginate(int $firmaId, array $filters, int $page = 1, int $perPage = 25): array
    {
        $where = ['ta.firma_id=:firma_id', 'ta.deleted_at IS NULL'];
        $params = ['firma_id' => $firmaId];
        $this->applyStateFilter($where, $params, (string) ($filters['estado'] ?? ''));
        if (($filters['caso_id'] ?? 0) > 0) {
            $where[] = 'ta.caso_id=:caso_id';
            $params['caso_id'] = $filters['caso_id'];
        }
        if (($filters['termino_id'] ?? 0) > 0) {
            $where[] = 'ta.termino_id=:termino_id';
            $params['termino_id'] = $filters['termino_id'];
        }
        if (($filters['responsable_usuario_id'] ?? 0) > 0) {
            $where[] = 'ta.responsable_usuario_id=:responsable_usuario_id';
            $params['responsable_usuario_id'] = $filters['responsable_usuario_id'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(ta.titulo_normalizado LIKE :q OR ta.descripcion LIKE :q_raw)';
            $params['q'] = '%' . $filters['q'] . '%';
            $params['q_raw'] = '%' . $filters['q_raw'] . '%';
        }

        $sqlWhere = ' WHERE ' . implode(' AND ', $where);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM tareas ta' . $sqlWhere);
        $count->execute($params);

        $offset = max(0, ($page - 1) * $perPage);
        $query = $this->pdo->prepare(
            'SELECT ta.*, c.titulo AS caso_titulo, t.titulo AS termino_titulo, u.nombre AS responsable_nombre
             FROM tareas ta
             LEFT JOIN casos c ON c.id=ta.caso_id AND c.firma_id=ta.firma_id
             LEFT JOIN terminos t ON t.id=ta.termino_id AND t.firma_id=ta.firma_id
             LEFT JOIN usuarios u ON u.id=ta.responsable_usuario_id AND u.firma_id=ta.firma_id' . $sqlWhere . '
             ORDER BY CASE ta.estado WHEN \'vencida\' THEN 0 WHEN \'pendiente\' THEN 1 WHEN \'en_proceso\' THEN 2
                           WHEN \'completada\' THEN 3 WHEN \'cancelada\' THEN 4 ELSE 5 END,
                      ta.fecha_vencimiento ASC, ta.id DESC
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

    /** @return array<string, mixed>|null */
    public function findForFirma(int $firmaId, int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT ta.*, c.titulo AS caso_titulo, t.titulo AS termino_titulo, u.nombre AS responsable_nombre
             FROM tareas ta
             LEFT JOIN casos c ON c.id=ta.caso_id AND c.firma_id=ta.firma_id
             LEFT JOIN terminos t ON t.id=ta.termino_id AND t.firma_id=ta.firma_id
             LEFT JOIN usuarios u ON u.id=ta.responsable_usuario_id AND u.firma_id=ta.firma_id
             WHERE ta.id=:id AND ta.firma_id=:firma_id AND ta.deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'firma_id' => $firmaId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function searchForSelect(int $firmaId, string $query, ?int $casoId = null, int $limit = 20): array
    {
        $where = ['ta.firma_id=:firma_id', 'ta.deleted_at IS NULL'];
        $params = ['firma_id' => $firmaId];
        if ($casoId !== null) {
            $where[] = 'ta.caso_id=:caso_id';
            $params['caso_id'] = $casoId;
        }
        $normalized = preg_replace('/\s+/', ' ', mb_strtolower(trim($query))) ?? '';
        $raw = mb_substr(trim($query), 0, 180);
        if ($normalized !== '' || $raw !== '') {
            $where[] = '(ta.titulo_normalizado LIKE :q OR ta.descripcion LIKE :raw OR c.titulo_normalizado LIKE :q)';
            $params['q'] = '%' . mb_substr($normalized, 0, 180) . '%';
            $params['raw'] = '%' . $raw . '%';
        }
        $statement = $this->pdo->prepare(
            'SELECT ta.id,ta.caso_id,ta.termino_id,ta.titulo,ta.fecha_vencimiento,ta.estado,c.titulo AS caso_titulo
             FROM tareas ta
             LEFT JOIN casos c ON c.id=ta.caso_id AND c.firma_id=ta.firma_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY ta.fecha_vencimiento IS NULL ASC, ta.fecha_vencimiento ASC, ta.id DESC
             LIMIT :limit'
        );
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', max(1, min(50, $limit)), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        // Nota (Sesion 7, 2026-07-11): timestamp calculado en PHP en vez de
        // CURRENT_TIMESTAMP(6) para que sea compatible con SQLite en pruebas
        // unitarias, siguiendo la convencion ya documentada en la sesion GDPR
        // (docs/IMPLEMENTACION_FASES.md, seccion C2). Mismo valor efectivo en MySQL.
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');
        $statement = $this->pdo->prepare(
            'INSERT INTO tareas
            (firma_id,caso_id,termino_id,responsable_usuario_id,titulo,titulo_normalizado,descripcion,prioridad,estado,fecha_vencimiento,created_at,updated_at)
             VALUES
            (:firma_id,:caso_id,:termino_id,:responsable_usuario_id,:titulo,:titulo_normalizado,:descripcion,:prioridad,:estado,:fecha_vencimiento,:created_at,:updated_at)'
        );
        $statement->execute($data + ['created_at' => $now, 'updated_at' => $now]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $firmaId, int $id, array $data): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');
        $statement = $this->pdo->prepare(
            'UPDATE tareas
             SET caso_id=:caso_id,termino_id=:termino_id,responsable_usuario_id=:responsable_usuario_id,
                 titulo=:titulo,titulo_normalizado=:titulo_normalizado,descripcion=:descripcion,prioridad=:prioridad,
                 estado=:estado,fecha_vencimiento=:fecha_vencimiento,updated_at=:updated_at
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute($data + ['updated_at' => $now, 'id' => $id, 'firma_id' => $firmaId]);
    }

    public function setTermino(int $firmaId, int $id, ?int $termId): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');
        $statement = $this->pdo->prepare(
            'UPDATE tareas SET termino_id=:termino_id, updated_at=:updated_at
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute(['termino_id' => $termId, 'updated_at' => $now, 'id' => $id, 'firma_id' => $firmaId]);
    }

    public function clearTerminoIfMatches(int $firmaId, int $id, int $termId): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');
        $statement = $this->pdo->prepare(
            'UPDATE tareas SET termino_id=NULL, updated_at=:updated_at
             WHERE id=:id AND firma_id=:firma_id AND termino_id=:termino_id AND deleted_at IS NULL'
        );
        $statement->execute(['updated_at' => $now, 'id' => $id, 'firma_id' => $firmaId, 'termino_id' => $termId]);
    }

    public function reassign(int $firmaId, int $id, ?int $fromUserId, int $toUserId): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');
        $statement = $this->pdo->prepare(
            'UPDATE tareas
             SET responsable_usuario_id=:responsable_usuario_id,reassigned_at=:now1,
                 reassigned_from_usuario_id=:from_usuario_id,reassigned_to_usuario_id=:reassigned_to_usuario_id,
                 updated_at=:now2
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute([
            'responsable_usuario_id' => $toUserId,
            'now1' => $now,
            'from_usuario_id' => $fromUserId,
            'reassigned_to_usuario_id' => $toUserId,
            'now2' => $now,
            'id' => $id,
            'firma_id' => $firmaId,
        ]);
    }

    public function changeStatus(int $firmaId, int $id, string $status, int $userId): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');
        $statement = $this->pdo->prepare(
            'UPDATE tareas
             SET estado=:estado,
                 completed_at=CASE WHEN :estado_check=\'completada\' THEN :now1 ELSE NULL END,
                 completed_by_usuario_id=CASE WHEN :estado_user_check=\'completada\' THEN :usuario_id ELSE NULL END,
                 updated_at=:now2
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute([
            'estado' => $status,
            'estado_check' => $status,
            'now1' => $now,
            'estado_user_check' => $status,
            'usuario_id' => $userId,
            'now2' => $now,
            'id' => $id,
            'firma_id' => $firmaId,
        ]);
    }

    /** @param list<string> $where @param array<string, mixed> $params */
    private function applyStateFilter(array &$where, array &$params, string $state): void
    {
        if ($state === 'vencida') {
            $where[] = 'ta.estado NOT IN (\'completada\',\'cancelada\') AND ta.fecha_vencimiento IS NOT NULL AND ta.fecha_vencimiento < CURRENT_DATE()';

            return;
        }
        if (in_array($state, ['pendiente', 'en_proceso', 'completada', 'cancelada'], true)) {
            $where[] = 'ta.estado=:estado';
            $params['estado'] = $state;
        }
    }
}
