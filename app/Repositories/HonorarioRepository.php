<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class HonorarioRepository extends BaseRepository
{
    /** @param array<string, mixed> $filters @return array{items: list<array<string, mixed>>, total: int} */
    public function paginate(int $firmaId, array $filters, int $page = 1, int $perPage = 25): array
    {
        $where = ['h.firma_id=:firma_id', 'h.deleted_at IS NULL'];
        $params = ['firma_id' => $firmaId];
        if (($filters['cliente_id'] ?? 0) > 0) {
            $where[] = 'h.cliente_id=:cliente_id';
            $params['cliente_id'] = $filters['cliente_id'];
        }
        if (($filters['caso_id'] ?? 0) > 0) {
            $where[] = 'h.caso_id=:caso_id';
            $params['caso_id'] = $filters['caso_id'];
        }
        if (($filters['estado'] ?? '') !== '') {
            $where[] = 'h.estado=:estado';
            $params['estado'] = $filters['estado'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = 'h.concepto_normalizado LIKE :q';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        $sqlWhere = ' WHERE ' . implode(' AND ', $where);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM honorarios h' . $sqlWhere);
        $count->execute($params);

        $offset = max(0, ($page - 1) * $perPage);
        $query = $this->pdo->prepare(
            'SELECT h.*, cl.nombre_razon_social AS cliente_nombre, c.titulo AS caso_titulo
             FROM honorarios h
             INNER JOIN clientes cl ON cl.id=h.cliente_id AND cl.firma_id=h.firma_id
             LEFT JOIN casos c ON c.id=h.caso_id AND c.firma_id=h.firma_id' . $sqlWhere . '
             ORDER BY h.fecha_acuerdo DESC, h.id DESC
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
            'SELECT h.*, cl.nombre_razon_social AS cliente_nombre, c.titulo AS caso_titulo
             FROM honorarios h
             INNER JOIN clientes cl ON cl.id=h.cliente_id AND cl.firma_id=h.firma_id
             LEFT JOIN casos c ON c.id=h.caso_id AND c.firma_id=h.firma_id
             WHERE h.id=:id AND h.firma_id=:firma_id AND h.deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'firma_id' => $firmaId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO honorarios
            (firma_id,cliente_id,caso_id,concepto,concepto_normalizado,descripcion,monto,moneda,fecha_acuerdo,estado,created_by_usuario_id,created_at,updated_at)
             VALUES
            (:firma_id,:cliente_id,:caso_id,:concepto,:concepto_normalizado,:descripcion,:monto,:moneda,:fecha_acuerdo,:estado,:created_by_usuario_id,CURRENT_TIMESTAMP(6),CURRENT_TIMESTAMP(6))'
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
            'descripcion' => $data['descripcion'],
            'monto' => $data['monto'],
            'moneda' => $data['moneda'],
            'fecha_acuerdo' => $data['fecha_acuerdo'],
            'estado' => $data['estado'],
        ];
        $statement = $this->pdo->prepare(
            'UPDATE honorarios
             SET cliente_id=:cliente_id,caso_id=:caso_id,concepto=:concepto,concepto_normalizado=:concepto_normalizado,
                 descripcion=:descripcion,monto=:monto,moneda=:moneda,fecha_acuerdo=:fecha_acuerdo,estado=:estado,updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute($payload + ['id' => $id, 'firma_id' => $firmaId]);
    }
}
