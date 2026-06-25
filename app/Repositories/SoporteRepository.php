<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class SoporteRepository extends BaseRepository
{
    /** @return array{items: list<array<string, mixed>>, total: int} */
    public function paginate(int $firmaId, ?int $usuarioId, bool $firmaScope, int $page = 1, int $perPage = 25): array
    {
        $where = ['t.firma_id=:firma_id', 't.deleted_at IS NULL'];
        $params = ['firma_id' => $firmaId];
        if (!$firmaScope && $usuarioId !== null) {
            $where[] = 't.creado_por_usuario_id=:usuario_id';
            $params['usuario_id'] = $usuarioId;
        }
        $sqlWhere = ' WHERE ' . implode(' AND ', $where);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM tickets_soporte t' . $sqlWhere);
        $count->execute($params);
        $offset = max(0, ($page - 1) * $perPage);
        $query = $this->pdo->prepare(
            'SELECT t.*, u.nombre AS creado_por_nombre
             FROM tickets_soporte t
             LEFT JOIN usuarios u ON u.id=t.creado_por_usuario_id AND u.firma_id=t.firma_id' . $sqlWhere . '
             ORDER BY FIELD(t.estado,\'abierto\',\'en_proceso\',\'esperando_cliente\',\'cerrado\'), t.updated_at DESC
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
    public function globalQueue(): array
    {
        return $this->pdo->query(
            'SELECT t.*, f.nombre AS firma_nombre, u.nombre AS creado_por_nombre
             FROM tickets_soporte t
             INNER JOIN firmas f ON f.id=t.firma_id
             LEFT JOIN usuarios u ON u.id=t.creado_por_usuario_id AND u.firma_id=t.firma_id
             WHERE t.deleted_at IS NULL
             ORDER BY FIELD(t.estado,\'abierto\',\'en_proceso\',\'esperando_cliente\',\'cerrado\'), t.updated_at DESC
             LIMIT 100'
        )->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findForFirma(int $firmaId, int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM tickets_soporte WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL');
        $statement->execute(['id' => $id, 'firma_id' => $firmaId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO tickets_soporte (firma_id,creado_por_usuario_id,asunto,categoria,prioridad,estado,contexto_json,created_at,updated_at)
             VALUES (:firma_id,:creado_por_usuario_id,:asunto,:categoria,:prioridad,\'abierto\',:contexto_json,CURRENT_TIMESTAMP(6),CURRENT_TIMESTAMP(6))'
        );
        $statement->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    public function addMessage(int $firmaId, int $ticketId, ?int $userId, string $visibility, string $message): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO ticket_mensajes (firma_id,ticket_id,autor_usuario_id,visibilidad,mensaje,created_at)
             VALUES (:firma_id,:ticket_id,:autor_usuario_id,:visibilidad,:mensaje,CURRENT_TIMESTAMP(6))'
        );
        $statement->execute(['firma_id' => $firmaId, 'ticket_id' => $ticketId, 'autor_usuario_id' => $userId, 'visibilidad' => $visibility, 'mensaje' => $message]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function messages(int $firmaId, int $ticketId, bool $includeSuperadmin = false): array
    {
        $visibility = $includeSuperadmin ? '' : ' AND tm.visibilidad=\'firma\'';
        $statement = $this->pdo->prepare(
            'SELECT tm.*, u.nombre AS autor_nombre
             FROM ticket_mensajes tm
             LEFT JOIN usuarios u ON u.id=tm.autor_usuario_id AND u.firma_id=tm.firma_id
             WHERE tm.firma_id=:firma_id AND tm.ticket_id=:ticket_id
             ' . $visibility . '
             ORDER BY tm.created_at'
        );
        $statement->execute(['firma_id' => $firmaId, 'ticket_id' => $ticketId]);

        return $statement->fetchAll();
    }

    public function setStatus(int $firmaId, int $id, string $status): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE tickets_soporte
             SET estado=:estado, closed_at=CASE WHEN :status_check=\'cerrado\' THEN CURRENT_TIMESTAMP(6) ELSE closed_at END, updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute(['estado' => $status, 'status_check' => $status, 'id' => $id, 'firma_id' => $firmaId]);
    }
}
