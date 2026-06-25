<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class AuditoriaRepository extends BaseRepository
{
    /** @param array<string, mixed> $event */
    public function insert(array $event): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO auditoria
            (firma_id, usuario_id, accion, modulo, entidad_tipo, entidad_id, severidad, correlation_id, ip_address, user_agent, metadata, created_at)
            VALUES
            (:firma_id, :usuario_id, :accion, :modulo, :entidad_tipo, :entidad_id, :severidad, :correlation_id, :ip_address, :user_agent, :metadata, CURRENT_TIMESTAMP(6))'
        );
        $statement->execute([
            'firma_id' => $event['firma_id'],
            'usuario_id' => $event['usuario_id'],
            'accion' => $event['accion'],
            'modulo' => $event['modulo'],
            'entidad_tipo' => $event['entidad_tipo'],
            'entidad_id' => $event['entidad_id'],
            'severidad' => $event['severidad'],
            'correlation_id' => $event['correlation_id'],
            'ip_address' => $event['ip_address'],
            'user_agent' => $event['user_agent'],
            'metadata' => json_encode($event['metadata'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $filters @return array{items: list<array<string, mixed>>, total: int} */
    public function paginate(?int $firmaId, array $filters, int $page, int $perPage, bool $global = false): array
    {
        $where = [];
        $params = [];
        if (!$global) {
            $where[] = 'a.firma_id = :firma_id';
            $params['firma_id'] = $firmaId;
        }
        foreach (['usuario_id' => 'a.usuario_id', 'modulo' => 'a.modulo', 'accion' => 'a.accion'] as $filter => $column) {
            if (($filters[$filter] ?? '') !== '') {
                $where[] = $column . ' = :' . $filter;
                $params[$filter] = $filters[$filter];
            }
        }
        if (($filters['desde'] ?? '') !== '') {
            $where[] = 'a.created_at >= :desde';
            $params['desde'] = $filters['desde'] . ' 00:00:00';
        }
        if (($filters['hasta'] ?? '') !== '') {
            $where[] = 'a.created_at <= :hasta';
            $params['hasta'] = $filters['hasta'] . ' 23:59:59';
        }

        $sqlWhere = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM auditoria a' . $sqlWhere);
        $count->execute($params);

        $offset = max(0, ($page - 1) * $perPage);
        $query = $this->pdo->prepare(
            'SELECT a.id, a.firma_id, a.usuario_id, a.accion, a.modulo, a.entidad_tipo, a.entidad_id,
                    a.severidad, a.correlation_id, a.ip_address, a.metadata, a.created_at,
                    u.nombre AS usuario_nombre, f.nombre AS firma_nombre
             FROM auditoria a
             LEFT JOIN usuarios u ON u.id = a.usuario_id
             LEFT JOIN firmas f ON f.id = a.firma_id' . $sqlWhere . '
             ORDER BY a.created_at DESC, a.id DESC
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
}

