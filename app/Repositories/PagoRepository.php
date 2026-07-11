<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PagoRepository extends BaseRepository
{
    /** @param array<string, mixed> $filters @return array{items: list<array<string, mixed>>, total: int} */
    public function paginate(int $firmaId, array $filters, int $page = 1, int $perPage = 25): array
    {
        $where = ['p.firma_id=:firma_id', 'p.deleted_at IS NULL'];
        $params = ['firma_id' => $firmaId];
        foreach (['cliente_id', 'caso_id', 'honorario_id'] as $field) {
            if (($filters[$field] ?? 0) > 0) {
                $where[] = 'p.' . $field . '=:' . $field;
                $params[$field] = $filters[$field];
            }
        }
        if (($filters['estado'] ?? '') !== '') {
            $where[] = 'p.estado=:estado';
            $params['estado'] = $filters['estado'];
        }

        $sqlWhere = ' WHERE ' . implode(' AND ', $where);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM pagos p' . $sqlWhere);
        $count->execute($params);

        $offset = max(0, ($page - 1) * $perPage);
        $query = $this->pdo->prepare(
            'SELECT p.*, cl.nombre_razon_social AS cliente_nombre, c.titulo AS caso_titulo, h.concepto AS honorario_concepto,
                    u.nombre AS registrado_por_nombre
             FROM pagos p
             INNER JOIN clientes cl ON cl.id=p.cliente_id AND cl.firma_id=p.firma_id
             LEFT JOIN casos c ON c.id=p.caso_id AND c.firma_id=p.firma_id
             LEFT JOIN honorarios h ON h.id=p.honorario_id AND h.firma_id=p.firma_id
             LEFT JOIN usuarios u ON u.id=p.registrado_por_usuario_id' . $sqlWhere . '
             ORDER BY p.fecha_pago DESC, p.id DESC
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
            'SELECT p.*, cl.nombre_razon_social AS cliente_nombre, c.titulo AS caso_titulo, h.concepto AS honorario_concepto,
                    u.nombre AS registrado_por_nombre
             FROM pagos p
             INNER JOIN clientes cl ON cl.id=p.cliente_id AND cl.firma_id=p.firma_id
             LEFT JOIN casos c ON c.id=p.caso_id AND c.firma_id=p.firma_id
             LEFT JOIN honorarios h ON h.id=p.honorario_id AND h.firma_id=p.firma_id
             LEFT JOIN usuarios u ON u.id=p.registrado_por_usuario_id
             WHERE p.id=:id AND p.firma_id=:firma_id AND p.deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'firma_id' => $firmaId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function duplicateExists(array $data): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM pagos
             WHERE firma_id=:firma_id
               AND cliente_id=:cliente_id
               AND (caso_id <=> :caso_id)
               AND (honorario_id <=> :honorario_id)
               AND fecha_pago=:fecha_pago
               AND monto=:monto
               AND moneda=:moneda
               AND metodo_pago=:metodo_pago
               AND (referencia_hash <=> :referencia_hash)
               AND estado=\'registrado\'
               AND deleted_at IS NULL'
        );
        $statement->execute([
            'firma_id' => $data['firma_id'],
            'cliente_id' => $data['cliente_id'],
            'caso_id' => $data['caso_id'],
            'honorario_id' => $data['honorario_id'],
            'fecha_pago' => $data['fecha_pago'],
            'monto' => $data['monto'],
            'moneda' => $data['moneda'],
            'metodo_pago' => $data['metodo_pago'],
            'referencia_hash' => $data['referencia_hash'],
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    /** @return list<array<string, mixed>> */
    public function searchForSelect(int $firmaId, string $query, ?int $clienteId = null, int $limit = 20): array
    {
        $where = ['p.firma_id=:firma_id', 'p.deleted_at IS NULL'];
        $params = ['firma_id' => $firmaId];
        if ($clienteId !== null) {
            $where[] = 'p.cliente_id=:cliente_id';
            $params['cliente_id'] = $clienteId;
        }
        $raw = mb_substr(trim($query), 0, 180);
        if ($raw !== '') {
            $where[] = '(p.metodo_pago LIKE :q OR p.referencia LIKE :q OR cl.nombre_razon_social LIKE :q)';
            $params['q'] = '%' . $raw . '%';
        }
        $statement = $this->pdo->prepare(
            'SELECT p.id,p.cliente_id,p.caso_id,p.honorario_id,p.fecha_pago,p.monto,p.moneda,p.metodo_pago,
                    cl.nombre_razon_social AS cliente_nombre
             FROM pagos p
             INNER JOIN clientes cl ON cl.id=p.cliente_id AND cl.firma_id=p.firma_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY p.fecha_pago DESC, p.id DESC
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
        $statement = $this->pdo->prepare(
            'INSERT INTO pagos
            (firma_id,cliente_id,caso_id,honorario_id,fecha_pago,monto,moneda,metodo_pago,referencia,referencia_hash,estado,observaciones,registrado_por_usuario_id,created_at,updated_at)
             VALUES
            (:firma_id,:cliente_id,:caso_id,:honorario_id,:fecha_pago,:monto,:moneda,:metodo_pago,:referencia,:referencia_hash,:estado,:observaciones,:registrado_por_usuario_id,CURRENT_TIMESTAMP(6),CURRENT_TIMESTAMP(6))'
        );
        $statement->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateStatus(int $firmaId, int $id, string $status): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE pagos SET estado=:estado,updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute(['estado' => $status, 'id' => $id, 'firma_id' => $firmaId]);
    }
}
