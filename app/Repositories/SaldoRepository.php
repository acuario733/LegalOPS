<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class SaldoRepository extends BaseRepository
{
    private const HONORARIO_ESTADOS_VIGENTES = ['pendiente', 'parcial', 'pagado'];
    private const PAGO_ESTADOS_VALIDOS = ['registrado'];
    private const GASTO_ESTADOS_COBRABLES = ['registrado'];

    /** @return array{honorarios: list<string>, pagos: list<string>, gastos: list<string>, expresion: string} */
    public function formula(): array
    {
        return [
            'honorarios' => self::HONORARIO_ESTADOS_VIGENTES,
            'pagos' => self::PAGO_ESTADOS_VALIDOS,
            'gastos' => self::GASTO_ESTADOS_COBRABLES,
            'expresion' => 'saldo = honorarios_vigentes + gastos_cobrables - pagos_validos',
        ];
    }

    /** @return array{items: list<array<string, mixed>>, total: int} */
    public function paginateClientes(int $firmaId, int $page = 1, int $perPage = 25): array
    {
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM clientes WHERE firma_id=:firma_id AND deleted_at IS NULL');
        $count->execute(['firma_id' => $firmaId]);
        $offset = max(0, ($page - 1) * $perPage);
        $query = $this->pdo->prepare(
            'SELECT cl.id AS cliente_id, cl.nombre_razon_social AS cliente_nombre,
                    COALESCE(h.total_honorarios,0) AS total_honorarios,
                    COALESCE(p.total_pagos,0) AS total_pagos,
                    COALESCE(g.total_gastos,0) AS total_gastos,
                    (COALESCE(h.total_honorarios,0) + COALESCE(g.total_gastos,0) - COALESCE(p.total_pagos,0)) AS saldo
             FROM clientes cl
             LEFT JOIN (SELECT cliente_id, SUM(monto) total_honorarios FROM honorarios WHERE firma_id=:firma_h AND estado IN (\'pendiente\',\'parcial\',\'pagado\') AND deleted_at IS NULL GROUP BY cliente_id) h ON h.cliente_id=cl.id
             LEFT JOIN (SELECT cliente_id, SUM(monto) total_pagos FROM pagos WHERE firma_id=:firma_p AND estado IN (\'registrado\') AND deleted_at IS NULL GROUP BY cliente_id) p ON p.cliente_id=cl.id
             LEFT JOIN (SELECT cliente_id, SUM(monto) total_gastos FROM gastos WHERE firma_id=:firma_g AND estado IN (\'registrado\') AND deleted_at IS NULL GROUP BY cliente_id) g ON g.cliente_id=cl.id
             WHERE cl.firma_id=:firma_id AND cl.deleted_at IS NULL
             ORDER BY saldo DESC, cl.nombre_razon_social
             LIMIT :limit OFFSET :offset'
        );
        $query->bindValue(':firma_id', $firmaId, PDO::PARAM_INT);
        $query->bindValue(':firma_h', $firmaId, PDO::PARAM_INT);
        $query->bindValue(':firma_p', $firmaId, PDO::PARAM_INT);
        $query->bindValue(':firma_g', $firmaId, PDO::PARAM_INT);
        $query->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();

        return ['items' => $query->fetchAll(), 'total' => (int) $count->fetchColumn()];
    }

    /** @return array<string, mixed> */
    public function resumen(int $firmaId, ?int $clienteId = null, ?int $casoId = null): array
    {
        $conditions = ['firma_id=:firma_id', 'deleted_at IS NULL'];
        $params = ['firma_id' => $firmaId];
        if ($clienteId !== null) {
            $conditions[] = 'cliente_id=:cliente_id';
            $params['cliente_id'] = $clienteId;
        }
        if ($casoId !== null) {
            $conditions[] = 'caso_id=:caso_id';
            $params['caso_id'] = $casoId;
        }
        $where = ' WHERE ' . implode(' AND ', $conditions);

        return $this->totals(
            $this->sum('honorarios', $where . ' AND estado IN (\'pendiente\',\'parcial\',\'pagado\')', $params),
            $this->sum('pagos', $where . ' AND estado IN (\'registrado\')', $params),
            $this->sum('gastos', $where . ' AND estado IN (\'registrado\')', $params)
        );
    }

    /** @return array<string, mixed> */
    public function resumenCliente(int $firmaId, int $clienteId): array
    {
        return $this->resumen($firmaId, $clienteId, null);
    }

    /** @return array<string, mixed> */
    public function resumenCaso(int $firmaId, int $casoId): array
    {
        return $this->resumen($firmaId, null, $casoId);
    }

    /** @param array<string, mixed> $params */
    private function sum(string $table, string $where, array $params): float
    {
        $statement = $this->pdo->prepare('SELECT COALESCE(SUM(monto),0) FROM ' . $table . $where);
        $statement->execute($params);

        return (float) $statement->fetchColumn();
    }

    /** @return array<string, mixed> */
    private function totals(float $honorarios, float $pagos, float $gastos): array
    {
        return [
            'total_honorarios' => $honorarios,
            'total_pagos' => $pagos,
            'total_gastos' => $gastos,
            'saldo' => $honorarios + $gastos - $pagos,
            'formula' => $this->formula(),
        ];
    }
}
