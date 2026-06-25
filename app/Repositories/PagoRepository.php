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
            'SELECT p.*, cl.nombre_razon_social AS cliente_nombre, c.titulo AS caso_titulo, h.concepto AS honorario_concepto
             FROM pagos p
             INNER JOIN clientes cl ON cl.id=p.cliente_id AND cl.firma_id=p.firma_id
             LEFT JOIN casos c ON c.id=p.caso_id AND c.firma_id=p.firma_id
             LEFT JOIN honorarios h ON h.id=p.honorario_id AND h.firma_id=p.firma_id' . $sqlWhere . '
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
            'SELECT p.*, cl.nombre_razon_social AS cliente_nombre, c.titulo AS caso_titulo, h.concepto AS honorario_concepto
             FROM pagos p
             INNER JOIN clientes cl ON cl.id=p.cliente_id AND cl.firma_id=p.firma_id
             LEFT JOIN casos c ON c.id=p.caso_id AND c.firma_id=p.firma_id
             LEFT JOIN honorarios h ON h.id=p.honorario_id AND h.firma_id=p.firma_id
             WHERE p.id=:id AND p.firma_id=:firma_id AND p.deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'firma_id' => $firmaId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
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
}
