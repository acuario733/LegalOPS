<?php

declare(strict_types=1);

namespace App\Repositories;

final class ReporteRepository extends BaseRepository
{
    /** @return array{headers: list<string>, rows: list<array<string, mixed>>} */
    public function data(int $firmaId, string $type, int $limit = 2000, array $filters = []): array
    {
        return match ($type) {
            'clientes' => $this->queryFiltered(['id','nombre_razon_social','tipo_persona','estado','origen','created_at'], 'SELECT id,nombre_razon_social,tipo_persona,estado,origen,created_at FROM clientes', ['firma_id=:firma_id', 'deleted_at IS NULL'], 'id DESC', $firmaId, $limit, $filters, 'created_at', 'estado'),
            'casos' => $this->queryFiltered(['id','cliente','titulo','tipo_proceso','jurisdiccion','estado','prioridad','radicado'], 'SELECT c.id,cl.nombre_razon_social cliente,c.titulo,c.tipo_proceso,c.jurisdiccion,c.estado,c.prioridad,c.radicado FROM casos c INNER JOIN clientes cl ON cl.id=c.cliente_id AND cl.firma_id=c.firma_id', ['c.firma_id=:firma_id', 'c.deleted_at IS NULL'], 'c.id DESC', $firmaId, $limit, $filters, 'c.created_at', 'c.estado', 'c.cliente_id', [], ['caso_id' => 'c.id', 'tipo_caso' => 'c.tipo_proceso']),
            'terminos' => $this->queryFiltered(['id','titulo','caso','estado','fecha_vencimiento','prioridad'], 'SELECT t.id,t.titulo,c.titulo caso,t.estado,t.fecha_vencimiento,t.prioridad FROM terminos t LEFT JOIN casos c ON c.id=t.caso_id AND c.firma_id=t.firma_id', ['t.firma_id=:firma_id', 't.deleted_at IS NULL'], 't.fecha_vencimiento ASC', $firmaId, $limit, $filters, 't.fecha_vencimiento', 't.estado', 'c.cliente_id', [], ['caso_id' => 't.caso_id', 'tipo_caso' => 'c.tipo_proceso']),
            'audiencias' => $this->queryFiltered(['id','titulo','caso','fecha','hora','estado','modalidad'], 'SELECT a.id,a.titulo,c.titulo caso,a.fecha,a.hora,a.estado,a.modalidad FROM audiencias a LEFT JOIN casos c ON c.id=a.caso_id AND c.firma_id=a.firma_id', ['a.firma_id=:firma_id', 'a.deleted_at IS NULL'], 'a.fecha ASC,a.hora ASC', $firmaId, $limit, $filters, 'a.fecha', 'a.estado', 'c.cliente_id', [], ['caso_id' => 'a.caso_id', 'tipo_caso' => 'c.tipo_proceso']),
            'tareas' => $this->queryFiltered(['id','titulo','caso','estado','prioridad','fecha_vencimiento'], 'SELECT t.id,t.titulo,c.titulo caso,t.estado,t.prioridad,t.fecha_vencimiento FROM tareas t LEFT JOIN casos c ON c.id=t.caso_id AND c.firma_id=t.firma_id', ['t.firma_id=:firma_id', 't.deleted_at IS NULL'], 't.id DESC', $firmaId, $limit, $filters, 't.fecha_vencimiento', 't.estado', 'c.cliente_id', [], ['caso_id' => 't.caso_id', 'tipo_caso' => 'c.tipo_proceso']),
            'documentos' => $this->queryFiltered(['id','titulo','tipo_documental','cliente','caso','version','updated_at'], 'SELECT d.id,d.titulo,d.tipo_documental,cl.nombre_razon_social cliente,c.titulo caso,v.version_numero version,d.updated_at FROM documentos d LEFT JOIN clientes cl ON cl.id=d.cliente_id AND cl.firma_id=d.firma_id LEFT JOIN casos c ON c.id=d.caso_id AND c.firma_id=d.firma_id LEFT JOIN documento_versiones v ON v.id=d.current_version_id', ['d.firma_id=:firma_id', 'd.deleted_at IS NULL'], 'd.updated_at DESC', $firmaId, $limit, $filters, 'd.updated_at', 'd.estado', 'd.cliente_id', [], ['caso_id' => 'd.caso_id', 'tipo_caso' => 'c.tipo_proceso']),
            'finanzas' => $this->finance($firmaId, $limit, $filters),
            'auditoria' => $this->queryFiltered(['id','accion','modulo','entidad_tipo','entidad_id','severidad','created_at'], 'SELECT id,accion,modulo,entidad_tipo,entidad_id,severidad,created_at FROM auditoria', ['firma_id=:firma_id'], 'created_at DESC', $firmaId, $limit, $filters, 'created_at', null, null, ['modulo', 'accion']),
            default => ['headers' => [], 'rows' => []],
        };
    }

    /** @return array{headers: list<string>, rows: list<array<string, mixed>>} */
    private function finance(int $firmaId, int $limit, array $filters): array
    {
        $where = ['firma_id=:firma_id'];
        $params = ['firma_id' => $firmaId];
        $this->applyCommonFilters($where, $params, $filters, 'fecha', 'estado', 'cliente_id');
        $this->applyMappedFilters($where, $params, $filters, [
            'caso_id' => 'caso_id',
            'tipo_caso' => 'tipo_caso',
            'moneda' => 'moneda',
            'tipo_finanza' => 'tipo',
        ]);
        $min = $this->moneyFilter($filters['monto_min'] ?? null);
        if ($min !== null) {
            $where[] = 'monto>=:monto_min';
            $params['monto_min'] = $min;
        }
        $max = $this->moneyFilter($filters['monto_max'] ?? null);
        if ($max !== null) {
            $where[] = 'monto<=:monto_max';
            $params['monto_max'] = $max;
        }

        $statement = $this->pdo->prepare(
            'SELECT tipo, cliente, caso, concepto, monto, moneda, fecha, estado,
                    CASE
                        WHEN tipo=\'honorario\' AND estado IN (\'pendiente\',\'parcial\',\'pagado\') THEN monto
                        WHEN tipo=\'pago\' AND estado IN (\'registrado\') THEN -monto
                        WHEN tipo=\'gasto\' AND estado IN (\'registrado\') THEN monto
                        ELSE 0
                    END impacto_saldo,
                    CASE
                        WHEN tipo=\'honorario\' AND estado IN (\'pendiente\',\'parcial\',\'pagado\') THEN \'si\'
                        WHEN tipo=\'pago\' AND estado IN (\'registrado\') THEN \'si\'
                        WHEN tipo=\'gasto\' AND estado IN (\'registrado\') THEN \'si\'
                        ELSE \'no\'
                    END incluye_saldo,
                    \'saldo = honorarios_vigentes + gastos_cobrables - pagos_validos\' formula_saldo
             FROM (
                SELECT \'honorario\' tipo, h.firma_id, h.cliente_id, h.caso_id, c.tipo_proceso tipo_caso, cl.nombre_razon_social cliente, c.titulo caso, h.concepto, h.monto, h.moneda, h.fecha_acuerdo fecha, h.estado FROM honorarios h INNER JOIN clientes cl ON cl.id=h.cliente_id AND cl.firma_id=h.firma_id LEFT JOIN casos c ON c.id=h.caso_id AND c.firma_id=h.firma_id WHERE h.firma_id=:firma_h AND h.deleted_at IS NULL
                UNION ALL
                SELECT \'pago\' tipo, p.firma_id, p.cliente_id, p.caso_id, c.tipo_proceso tipo_caso, cl.nombre_razon_social cliente, c.titulo caso, p.metodo_pago concepto, p.monto, p.moneda, p.fecha_pago fecha, p.estado FROM pagos p INNER JOIN clientes cl ON cl.id=p.cliente_id AND cl.firma_id=p.firma_id LEFT JOIN casos c ON c.id=p.caso_id AND c.firma_id=p.firma_id WHERE p.firma_id=:firma_p AND p.deleted_at IS NULL
                UNION ALL
                SELECT \'gasto\' tipo, g.firma_id, g.cliente_id, g.caso_id, c.tipo_proceso tipo_caso, cl.nombre_razon_social cliente, c.titulo caso, g.concepto, g.monto, g.moneda, g.fecha_gasto fecha, g.estado FROM gastos g INNER JOIN clientes cl ON cl.id=g.cliente_id AND cl.firma_id=g.firma_id LEFT JOIN casos c ON c.id=g.caso_id AND c.firma_id=g.firma_id WHERE g.firma_id=:firma_g AND g.deleted_at IS NULL
             ) x WHERE ' . implode(' AND ', $where) . ' ORDER BY fecha DESC LIMIT :limit'
        );
        $statement->bindValue(':firma_h', $firmaId, \PDO::PARAM_INT);
        $statement->bindValue(':firma_p', $firmaId, \PDO::PARAM_INT);
        $statement->bindValue(':firma_g', $firmaId, \PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        return ['headers' => ['tipo','cliente','caso','concepto','monto','moneda','fecha','estado','impacto_saldo','incluye_saldo','formula_saldo'], 'rows' => $statement->fetchAll()];
    }

    /** @param list<string> $headers @param list<string> $where @param list<string> $extraExactFilters @param array<string, string> $mappedFilters @return array{headers: list<string>, rows: list<array<string, mixed>>} */
    private function queryFiltered(array $headers, string $fromSql, array $where, string $orderBy, int $firmaId, int $limit, array $filters, ?string $dateColumn, ?string $statusColumn, ?string $clientColumn = null, array $extraExactFilters = [], array $mappedFilters = []): array
    {
        $params = ['firma_id' => $firmaId];
        $this->applyCommonFilters($where, $params, $filters, $dateColumn, $statusColumn, $clientColumn);
        foreach ($extraExactFilters as $field) {
            $value = trim((string) ($filters[$field] ?? ''));
            if ($value !== '') {
                $where[] = $field . '=:' . $field;
                $params[$field] = mb_substr($value, 0, 120);
            }
        }
        $this->applyMappedFilters($where, $params, $filters, $mappedFilters);
        $sql = $fromSql . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $orderBy . ' LIMIT :limit';
        $statement = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        return ['headers' => $headers, 'rows' => $statement->fetchAll()];
    }

    /** @param list<string> $where @param array<string, int|string> $params */
    private function applyCommonFilters(array &$where, array &$params, array $filters, ?string $dateColumn, ?string $statusColumn, ?string $clientColumn): void
    {
        if ($statusColumn !== null) {
            $status = trim((string) ($filters['estado'] ?? ''));
            if ($status !== '') {
                $where[] = $statusColumn . '=:estado';
                $params['estado'] = mb_substr($status, 0, 40);
            }
        }
        if ($clientColumn !== null && filter_var($filters['cliente_id'] ?? null, FILTER_VALIDATE_INT) !== false) {
            $where[] = $clientColumn . '=:cliente_id';
            $params['cliente_id'] = (int) $filters['cliente_id'];
        }
        if ($dateColumn !== null) {
            $from = $this->dateFilter($filters['desde'] ?? null);
            if ($from !== null) {
                $where[] = $dateColumn . '>=:desde';
                $params['desde'] = $from;
            }
            $to = $this->dateFilter($filters['hasta'] ?? null);
            if ($to !== null) {
                $where[] = $dateColumn . '<=:hasta';
                $params['hasta'] = $to;
            }
        }
    }

    /** @param list<string> $where @param array<string, int|string> $params @param array<string, string> $mapped */
    private function applyMappedFilters(array &$where, array &$params, array $filters, array $mapped): void
    {
        foreach ($mapped as $filter => $column) {
            if ($filter === 'caso_id') {
                if (filter_var($filters[$filter] ?? null, FILTER_VALIDATE_INT) !== false) {
                    $where[] = $column . '=:caso_id';
                    $params['caso_id'] = (int) $filters[$filter];
                }
                continue;
            }
            $value = trim((string) ($filters[$filter] ?? ''));
            if ($value !== '') {
                $where[] = $column . '=:' . $filter;
                $params[$filter] = mb_substr($value, 0, 80);
            }
        }
    }

    private function dateFilter(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }

    private function moneyFilter(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        return number_format((float) str_replace(',', '.', $value), 2, '.', '');
    }
}
