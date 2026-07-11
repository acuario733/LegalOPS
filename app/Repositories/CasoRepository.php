<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class CasoRepository extends BaseRepository
{
    /** @param array<string, mixed> $filters @return array{items: list<array<string, mixed>>, total: int} */
    public function paginate(int $firmaId, array $filters, int $page = 1, int $perPage = 25): array
    {
        $where = ['c.firma_id=:firma_id', 'c.deleted_at IS NULL'];
        $params = ['firma_id' => $firmaId];
        if (($filters['estado'] ?? '') !== '') {
            $where[] = 'c.estado=:estado';
            $params['estado'] = $filters['estado'];
        }
        if (($filters['cliente_id'] ?? 0) > 0) {
            $where[] = 'c.cliente_id=:cliente_id';
            $params['cliente_id'] = $filters['cliente_id'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(c.titulo_normalizado LIKE :q OR c.radicado LIKE :radicado)';
            $params['q'] = '%' . $filters['q'] . '%';
            $params['radicado'] = '%' . $filters['q'] . '%';
        }

        $sqlWhere = ' WHERE ' . implode(' AND ', $where);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM casos c' . $sqlWhere);
        $count->execute($params);

        $offset = max(0, ($page - 1) * $perPage);
        $query = $this->pdo->prepare(
            'SELECT c.id,c.firma_id,c.numero,c.cliente_id,c.responsable_usuario_id,c.titulo,c.estado,c.prioridad,
                    c.etapa_actual_id,c.tipo_proceso,c.jurisdiccion,c.despacho,c.radicado,c.fecha_apertura,c.closed_at,c.created_at,
                    cl.nombre_razon_social AS cliente_nombre, u.nombre AS responsable_nombre
             FROM casos c
             INNER JOIN clientes cl ON cl.id=c.cliente_id AND cl.firma_id=c.firma_id
             LEFT JOIN usuarios u ON u.id=c.responsable_usuario_id AND u.firma_id=c.firma_id' . $sqlWhere . '
             ORDER BY CASE c.estado WHEN \'activo\' THEN 0 WHEN \'cerrado\' THEN 1 WHEN \'archivado\' THEN 2 ELSE 3 END,
                      c.updated_at DESC, c.id DESC
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
            'SELECT c.*, cl.nombre_razon_social AS cliente_nombre, u.nombre AS responsable_nombre
             FROM casos c
             INNER JOIN clientes cl ON cl.id=c.cliente_id AND cl.firma_id=c.firma_id
             LEFT JOIN usuarios u ON u.id=c.responsable_usuario_id AND u.firma_id=c.firma_id
             WHERE c.id=:id AND c.firma_id=:firma_id AND c.deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'firma_id' => $firmaId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function radicadoExists(int $firmaId, string $radicado, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM casos WHERE firma_id=:firma_id AND radicado=:radicado AND deleted_at IS NULL';
        $params = ['firma_id' => $firmaId, 'radicado' => $radicado];
        if ($excludeId !== null) {
            $sql .= ' AND id<>:id';
            $params['id'] = $excludeId;
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    /** @return list<array<string, mixed>> */
    public function searchForSelect(int $firmaId, string $query, ?int $clienteId = null, int $limit = 20): array
    {
        $where = ['c.firma_id=:firma_id', 'c.deleted_at IS NULL'];
        $params = ['firma_id' => $firmaId];
        if ($clienteId !== null) {
            $where[] = 'c.cliente_id=:cliente_id';
            $params['cliente_id'] = $clienteId;
        }
        $normalized = $this->normalizeText($query, 180);
        $raw = mb_substr(trim($query), 0, 180);
        if ($normalized !== '' || $raw !== '') {
            $where[] = '(c.titulo_normalizado LIKE :q OR c.radicado LIKE :raw OR cl.nombre_normalizado LIKE :q OR CAST(c.id AS CHAR) LIKE :raw)';
            $params['q'] = '%' . $normalized . '%';
            $params['raw'] = '%' . $raw . '%';
        }
        $statement = $this->pdo->prepare(
            'SELECT c.id,c.cliente_id,c.titulo,c.radicado,cl.nombre_razon_social AS cliente_nombre
             FROM casos c
             INNER JOIN clientes cl ON cl.id=c.cliente_id AND cl.firma_id=c.firma_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY c.updated_at DESC, c.id DESC
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
            'INSERT INTO casos
            (firma_id,numero,cliente_id,responsable_usuario_id,titulo,titulo_normalizado,descripcion,estado,prioridad,
             tipo_proceso,jurisdiccion,despacho,radicado,fecha_apertura,created_at,updated_at)
             VALUES
            (:firma_id,:numero,:cliente_id,:responsable_usuario_id,:titulo,:titulo_normalizado,:descripcion,:estado,:prioridad,
             :tipo_proceso,:jurisdiccion,:despacho,:radicado,:fecha_apertura,:created_at,:updated_at)'
        );
        $statement->execute($data + ['created_at' => $now, 'updated_at' => $now]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $firmaId, int $id, array $data): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');
        $statement = $this->pdo->prepare(
            'UPDATE casos
             SET cliente_id=:cliente_id,responsable_usuario_id=:responsable_usuario_id,titulo=:titulo,
                 titulo_normalizado=:titulo_normalizado,descripcion=:descripcion,estado=:estado,prioridad=:prioridad,
                 tipo_proceso=:tipo_proceso,jurisdiccion=:jurisdiccion,despacho=:despacho,radicado=:radicado,
                 fecha_apertura=:fecha_apertura,updated_at=:updated_at
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute($data + ['updated_at' => $now, 'id' => $id, 'firma_id' => $firmaId]);
    }

    public function close(int $firmaId, int $id, int $userId, string $reason): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');
        $statement = $this->pdo->prepare(
            'UPDATE casos
             SET estado=\'cerrado\', closed_at=:now1, closed_by_usuario_id=:usuario_id,
                 close_reason=:motivo, updated_at=:now2
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute(['now1' => $now, 'usuario_id' => $userId, 'motivo' => $reason, 'now2' => $now, 'id' => $id, 'firma_id' => $firmaId]);
    }

    public function archive(int $firmaId, int $id, int $userId, string $reason): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');
        $statement = $this->pdo->prepare(
            'UPDATE casos
             SET estado=\'archivado\', archived_at=:now1, archived_by_usuario_id=:usuario_id,
                 archive_reason=:motivo, updated_at=:now2
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute(['now1' => $now, 'usuario_id' => $userId, 'motivo' => $reason, 'now2' => $now, 'id' => $id, 'firma_id' => $firmaId]);
    }

    public function reopen(int $firmaId, int $id): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');
        $statement = $this->pdo->prepare(
            'UPDATE casos
             SET estado=\'activo\', updated_at=:updated_at
             WHERE id=:id AND firma_id=:firma_id AND estado IN (\'cerrado\',\'archivado\') AND deleted_at IS NULL'
        );
        $statement->execute(['updated_at' => $now, 'id' => $id, 'firma_id' => $firmaId]);
    }

    public function nextCaseNumber(int $firmaId, int $year): string
    {
        // TENANT FILTER: firma_id = ?
        $statement = $this->pdo->prepare(
            'INSERT INTO caso_secuencias (firma_id,anio,ultimo_numero,updated_at)
             VALUES (:firma_id,:anio,LAST_INSERT_ID(1),CURRENT_TIMESTAMP(6))
             ON DUPLICATE KEY UPDATE ultimo_numero=LAST_INSERT_ID(ultimo_numero + 1), updated_at=CURRENT_TIMESTAMP(6)'
        );
        $statement->execute(['firma_id' => $firmaId, 'anio' => $year]);
        $next = (int) $this->pdo->query('SELECT LAST_INSERT_ID()')->fetchColumn();

        return sprintf('%d-%04d', $year, $next);
    }

    public function setCurrentStage(int $firmaId, int $id, int $stageId): void
    {
        // TENANT FILTER: firma_id = ?
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');
        $statement = $this->pdo->prepare(
            'UPDATE casos
             SET etapa_actual_id=:etapa_actual_id, updated_at=:updated_at
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute(['etapa_actual_id' => $stageId, 'updated_at' => $now, 'id' => $id, 'firma_id' => $firmaId]);
    }

    private function normalizeText(string $value, int $max): string
    {
        $value = preg_replace('/\s+/', ' ', mb_strtolower(trim($value))) ?? '';

        return mb_substr($value, 0, $max);
    }
}
