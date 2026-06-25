<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class NotificacionRepository extends BaseRepository
{
    /** @param array<string, mixed> $filters @return array{items: list<array<string, mixed>>, total: int} */
    public function paginate(int $firmaId, int $usuarioId, array $filters, int $page = 1, int $perPage = 25): array
    {
        $where = ['n.firma_id=:firma_id', 'n.usuario_id=:usuario_id'];
        $params = ['firma_id' => $firmaId, 'usuario_id' => $usuarioId];
        if (($filters['estado'] ?? '') !== '') {
            $where[] = 'n.estado=:estado';
            $params['estado'] = $filters['estado'];
        }
        if (($filters['severidad'] ?? '') !== '') {
            $where[] = 'n.severidad=:severidad';
            $params['severidad'] = $filters['severidad'];
        }

        $sqlWhere = ' WHERE ' . implode(' AND ', $where);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM notificaciones n' . $sqlWhere);
        $count->execute($params);

        $offset = max(0, ($page - 1) * $perPage);
        $query = $this->pdo->prepare(
            'SELECT n.*
             FROM notificaciones n' . $sqlWhere . '
             ORDER BY FIELD(n.estado, \'pendiente\',\'leida\'), FIELD(n.severidad, \'critica\',\'warning\',\'info\'), n.created_at DESC
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
    public function findForUser(int $firmaId, int $usuarioId, int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM notificaciones WHERE id=:id AND firma_id=:firma_id AND usuario_id=:usuario_id');
        $statement->execute(['id' => $id, 'firma_id' => $firmaId, 'usuario_id' => $usuarioId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function createIfMissing(array $data): bool
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO notificaciones
            (firma_id,usuario_id,titulo,mensaje,severidad,estado,origen_tipo,origen_id,origen_url,dedupe_key,generated_at,created_at,updated_at)
             VALUES
            (:firma_id,:usuario_id,:titulo,:mensaje,:severidad,\'pendiente\',:origen_tipo,:origen_id,:origen_url,:dedupe_key,CURRENT_TIMESTAMP(6),CURRENT_TIMESTAMP(6),CURRENT_TIMESTAMP(6))
             ON DUPLICATE KEY UPDATE id = id'
        );
        $statement->execute($data);

        return $statement->rowCount() > 0;
    }

    public function markRead(int $firmaId, int $usuarioId, int $id): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE notificaciones
             SET estado=\'leida\', read_at=COALESCE(read_at,CURRENT_TIMESTAMP(6)), updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id AND firma_id=:firma_id AND usuario_id=:usuario_id AND estado=\'pendiente\''
        );
        $statement->execute(['id' => $id, 'firma_id' => $firmaId, 'usuario_id' => $usuarioId]);

        return $statement->rowCount() > 0;
    }

    /** @return list<int> */
    public function fallbackUsers(int $firmaId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT DISTINCT u.id
             FROM usuarios u
             INNER JOIN usuario_roles ur ON ur.usuario_id=u.id AND ur.firma_id=u.firma_id
             INNER JOIN roles r ON r.id=ur.rol_id AND r.firma_id=ur.firma_id
             WHERE u.firma_id=:firma_id AND u.tipo=\'interno\' AND u.estado=\'activo\' AND u.deleted_at IS NULL
               AND r.codigo=\'administrador\' AND r.estado=\'activo\' AND r.deleted_at IS NULL
             ORDER BY u.id
             LIMIT 25'
        );
        $statement->execute(['firma_id' => $firmaId]);
        $adminIds = array_map('intval', array_column($statement->fetchAll(), 'id'));
        if ($adminIds !== []) {
            return $adminIds;
        }

        $statement = $this->pdo->prepare(
            'SELECT u.id
             FROM usuarios u
             WHERE u.firma_id=:firma_id AND u.tipo=\'interno\' AND u.estado=\'activo\' AND u.deleted_at IS NULL
             ORDER BY u.id
             LIMIT 25'
        );
        $statement->execute(['firma_id' => $firmaId]);

        return array_map('intval', array_column($statement->fetchAll(), 'id'));
    }

    /** @return array<string, mixed>|null */
    public function activeInternalUser(int $firmaId, int $userId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id
             FROM usuarios
             WHERE id=:id AND firma_id=:firma_id AND tipo=\'interno\' AND estado=\'activo\' AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $userId, 'firma_id' => $firmaId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function dueTerms(): array
    {
        return $this->pdo->query(
            'SELECT t.firma_id, t.id, t.titulo, t.fecha_vencimiento, t.alerta_dias, t.responsable_usuario_id
             FROM terminos t
             WHERE t.deleted_at IS NULL AND t.estado NOT IN (\'cumplido\',\'cancelado\')
               AND t.fecha_vencimiento <= DATE_ADD(CURRENT_DATE(), INTERVAL t.alerta_dias DAY)'
        )->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function upcomingAudiences(): array
    {
        return $this->pdo->query(
            'SELECT a.firma_id, a.id, a.titulo, a.fecha, a.hora, a.responsable_usuario_id
             FROM audiencias a
             WHERE a.deleted_at IS NULL AND a.estado=\'programada\'
               AND a.fecha BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY)'
        )->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function dueTasks(): array
    {
        return $this->pdo->query(
            'SELECT ta.firma_id, ta.id, ta.titulo, ta.fecha_vencimiento, ta.responsable_usuario_id
             FROM tareas ta
             WHERE ta.deleted_at IS NULL AND ta.estado NOT IN (\'completada\',\'cancelada\') AND ta.fecha_vencimiento IS NOT NULL
               AND ta.fecha_vencimiento <= DATE_ADD(CURRENT_DATE(), INTERVAL 3 DAY)'
        )->fetchAll();
    }
}
