<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class GastoRepository extends BaseRepository
{
    /** @param array<string, mixed> $filters @return array{items: list<array<string, mixed>>, total: int} */
    public function paginate(int $firmaId, array $filters, int $page = 1, int $perPage = 25): array
    {
        $where = ['g.firma_id=:firma_id', 'g.deleted_at IS NULL'];
        $params = ['firma_id' => $firmaId];
        foreach (['cliente_id', 'caso_id'] as $field) {
            if (($filters[$field] ?? 0) > 0) {
                $where[] = 'g.' . $field . '=:' . $field;
                $params[$field] = $filters[$field];
            }
        }
        if (($filters['estado'] ?? '') !== '') {
            $where[] = 'g.estado=:estado';
            $params['estado'] = $filters['estado'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = 'g.concepto_normalizado LIKE :q';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        $sqlWhere = ' WHERE ' . implode(' AND ', $where);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM gastos g' . $sqlWhere);
        $count->execute($params);

        $offset = max(0, ($page - 1) * $perPage);
        $query = $this->pdo->prepare(
            'SELECT g.*, cl.nombre_razon_social AS cliente_nombre, c.titulo AS caso_titulo,
                    GROUP_CONCAT(d.titulo ORDER BY d.titulo SEPARATOR \', \') AS soportes
             FROM gastos g
             INNER JOIN clientes cl ON cl.id=g.cliente_id AND cl.firma_id=g.firma_id
             LEFT JOIN casos c ON c.id=g.caso_id AND c.firma_id=g.firma_id
             LEFT JOIN gasto_soportes gs ON gs.gasto_id=g.id AND gs.firma_id=g.firma_id
             LEFT JOIN documentos d ON d.id=gs.documento_id AND d.firma_id=gs.firma_id AND d.deleted_at IS NULL' . $sqlWhere . '
             GROUP BY g.id,g.firma_id,g.cliente_id,g.caso_id,g.concepto,g.concepto_normalizado,g.categoria,g.monto,g.moneda,g.fecha_gasto,g.estado,g.observaciones,g.registrado_por_usuario_id,g.created_at,g.updated_at,g.deleted_at,cl.nombre_razon_social,c.titulo
             ORDER BY g.fecha_gasto DESC, g.id DESC
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
            'SELECT g.*, cl.nombre_razon_social AS cliente_nombre, c.titulo AS caso_titulo
             FROM gastos g
             INNER JOIN clientes cl ON cl.id=g.cliente_id AND cl.firma_id=g.firma_id
             LEFT JOIN casos c ON c.id=g.caso_id AND c.firma_id=g.firma_id
             WHERE g.id=:id AND g.firma_id=:firma_id AND g.deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'firma_id' => $firmaId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO gastos
            (firma_id,cliente_id,caso_id,concepto,concepto_normalizado,categoria,monto,moneda,fecha_gasto,estado,observaciones,registrado_por_usuario_id,created_at,updated_at)
             VALUES
            (:firma_id,:cliente_id,:caso_id,:concepto,:concepto_normalizado,:categoria,:monto,:moneda,:fecha_gasto,:estado,:observaciones,:registrado_por_usuario_id,CURRENT_TIMESTAMP(6),CURRENT_TIMESTAMP(6))'
        );
        $statement->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $firmaId, int $id, array $data): void
    {
        $payload = [
            'cliente_id' => $data['cliente_id'],
            'caso_id' => $data['caso_id'],
            'concepto' => $data['concepto'],
            'concepto_normalizado' => $data['concepto_normalizado'],
            'categoria' => $data['categoria'],
            'monto' => $data['monto'],
            'moneda' => $data['moneda'],
            'fecha_gasto' => $data['fecha_gasto'],
            'estado' => $data['estado'],
            'observaciones' => $data['observaciones'],
        ];
        $statement = $this->pdo->prepare(
            'UPDATE gastos
             SET cliente_id=:cliente_id,caso_id=:caso_id,concepto=:concepto,concepto_normalizado=:concepto_normalizado,
                 categoria=:categoria,monto=:monto,moneda=:moneda,fecha_gasto=:fecha_gasto,estado=:estado,observaciones=:observaciones,updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute($payload + ['id' => $id, 'firma_id' => $firmaId]);
    }

    public function attachSupport(int $firmaId, int $gastoId, int $documentoId): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO gasto_soportes (firma_id,gasto_id,documento_id,created_at)
             VALUES (:firma_id,:gasto_id,:documento_id,CURRENT_TIMESTAMP(6))
             ON DUPLICATE KEY UPDATE created_at=gasto_soportes.created_at'
        );
        $statement->execute(['firma_id' => $firmaId, 'gasto_id' => $gastoId, 'documento_id' => $documentoId]);
    }
}
