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
        if (($filters['estado'] ?? '') !== '') {
            $where[] = 'ta.estado=:estado';
            $params['estado'] = $filters['estado'];
        }
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
             ORDER BY FIELD(ta.estado, \'vencida\',\'pendiente\',\'en_proceso\',\'completada\',\'cancelada\'), ta.fecha_vencimiento ASC, ta.id DESC
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

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO tareas
            (firma_id,caso_id,termino_id,responsable_usuario_id,titulo,titulo_normalizado,descripcion,prioridad,estado,fecha_vencimiento,created_at,updated_at)
             VALUES
            (:firma_id,:caso_id,:termino_id,:responsable_usuario_id,:titulo,:titulo_normalizado,:descripcion,:prioridad,:estado,:fecha_vencimiento,CURRENT_TIMESTAMP(6),CURRENT_TIMESTAMP(6))'
        );
        $statement->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $firmaId, int $id, array $data): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE tareas
             SET caso_id=:caso_id,termino_id=:termino_id,responsable_usuario_id=:responsable_usuario_id,
                 titulo=:titulo,titulo_normalizado=:titulo_normalizado,descripcion=:descripcion,prioridad=:prioridad,
                 estado=:estado,fecha_vencimiento=:fecha_vencimiento,updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute($data + ['id' => $id, 'firma_id' => $firmaId]);
    }

    public function setTermino(int $firmaId, int $id, ?int $termId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE tareas SET termino_id=:termino_id, updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute(['termino_id' => $termId, 'id' => $id, 'firma_id' => $firmaId]);
    }

    public function clearTerminoIfMatches(int $firmaId, int $id, int $termId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE tareas SET termino_id=NULL, updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id AND firma_id=:firma_id AND termino_id=:termino_id AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'firma_id' => $firmaId, 'termino_id' => $termId]);
    }

    public function reassign(int $firmaId, int $id, ?int $fromUserId, int $toUserId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE tareas
             SET responsable_usuario_id=:responsable_usuario_id,reassigned_at=CURRENT_TIMESTAMP(6),
                 reassigned_from_usuario_id=:from_usuario_id,reassigned_to_usuario_id=:reassigned_to_usuario_id,
                 updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute([
            'responsable_usuario_id' => $toUserId,
            'from_usuario_id' => $fromUserId,
            'reassigned_to_usuario_id' => $toUserId,
            'id' => $id,
            'firma_id' => $firmaId,
        ]);
    }

    public function changeStatus(int $firmaId, int $id, string $status, int $userId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE tareas
             SET estado=:estado,
                 completed_at=CASE WHEN :estado_check=\'completada\' THEN CURRENT_TIMESTAMP(6) ELSE NULL END,
                 completed_by_usuario_id=CASE WHEN :estado_user_check=\'completada\' THEN :usuario_id ELSE NULL END,
                 updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute([
            'estado' => $status,
            'estado_check' => $status,
            'estado_user_check' => $status,
            'usuario_id' => $userId,
            'id' => $id,
            'firma_id' => $firmaId,
        ]);
    }
}
