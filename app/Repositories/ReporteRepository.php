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
            'casos' => $this->queryFiltered(['id','cliente','titulo','estado','prioridad','radicado'], 'SELECT c.id,cl.nombre_razon_social cliente,c.titulo,c.estado,c.prioridad,c.radicado FROM casos c INNER JOIN clientes cl ON cl.id=c.cliente_id AND cl.firma_id=c.firma_id', ['c.firma_id=:firma_id', 'c.deleted_at IS NULL'], 'c.id DESC', $firmaId, $limit, $filters, 'c.created_at', 'c.estado', 'c.cliente_id'),
            'terminos' => $this->queryFiltered(['id','titulo','estado','fecha_vencimiento','prioridad'], 'SELECT t.id,t.titulo,t.estado,t.fecha_vencimiento,t.prioridad FROM terminos t LEFT JOIN casos c ON c.id=t.caso_id AND c.firma_id=t.firma_id', ['t.firma_id=:firma_id', 't.deleted_at IS NULL'], 't.fecha_vencimiento ASC', $firmaId, $limit, $filters, 't.fecha_vencimiento', 't.estado', 'c.cliente_id'),
            'audiencias' => $this->queryFiltered(['id','titulo','fecha','hora','estado','modalidad'], 'SELECT a.id,a.titulo,a.fecha,a.hora,a.estado,a.modalidad FROM audiencias a LEFT JOIN casos c ON c.id=a.caso_id AND c.firma_id=a.firma_id', ['a.firma_id=:firma_id', 'a.deleted_at IS NULL'], 'a.fecha ASC,a.hora ASC', $firmaId, $limit, $filters, 'a.fecha', 'a.estado', 'c.cliente_id'),
            'tareas' => $this->queryFiltered(['id','titulo','estado','prioridad','fecha_vencimiento'], 'SELECT t.id,t.titulo,t.estado,t.prioridad,t.fecha_vencimiento FROM tareas t LEFT JOIN casos c ON c.id=t.caso_id AND c.firma_id=t.firma_id', ['t.firma_id=:firma_id', 't.deleted_at IS NULL'], 't.id DESC', $firmaId, $limit, $filters, 't.fecha_vencimiento', 't.estado', 'c.cliente_id'),
            'documentos' => $this->queryFiltered(['id','titulo','tipo_documental','cliente','version','updated_at'], 'SELECT d.id,d.titulo,d.tipo_documental,cl.nombre_razon_social cliente,v.version_numero version,d.updated_at FROM documentos d LEFT JOIN clientes cl ON cl.id=d.cliente_id AND cl.firma_id=d.firma_id LEFT JOIN documento_versiones v ON v.id=d.current_version_id', ['d.firma_id=:firma_id', 'd.deleted_at IS NULL'], 'd.updated_at DESC', $firmaId, $limit, $filters, 'd.updated_at', 'd.estado', 'd.cliente_id'),
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
        $statement = $this->pdo->prepare(
            'SELECT tipo, cliente, concepto, monto, moneda, fecha, estado FROM (
                SELECT \'honorario\' tipo, h.firma_id, h.cliente_id, cl.nombre_razon_social cliente, h.concepto, h.monto, h.moneda, h.fecha_acuerdo fecha, h.estado FROM honorarios h INNER JOIN clientes cl ON cl.id=h.cliente_id AND cl.firma_id=h.firma_id WHERE h.firma_id=:firma_h AND h.deleted_at IS NULL
                UNION ALL
                SELECT \'pago\' tipo, p.firma_id, p.cliente_id, cl.nombre_razon_social cliente, p.metodo_pago concepto, p.monto, p.moneda, p.fecha_pago fecha, p.estado FROM pagos p INNER JOIN clientes cl ON cl.id=p.cliente_id AND cl.firma_id=p.firma_id WHERE p.firma_id=:firma_p AND p.deleted_at IS NULL
                UNION ALL
                SELECT \'gasto\' tipo, g.firma_id, g.cliente_id, cl.nombre_razon_social cliente, g.concepto, g.monto, g.moneda, g.fecha_gasto fecha, g.estado FROM gastos g INNER JOIN clientes cl ON cl.id=g.cliente_id AND cl.firma_id=g.firma_id WHERE g.firma_id=:firma_g AND g.deleted_at IS NULL
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

        return ['headers' => ['tipo','cliente','concepto','monto','moneda','fecha','estado'], 'rows' => $statement->fetchAll()];
    }

    /** @param list<string> $headers @param list<string> $where @param list<string> $extraExactFilters @return array{headers: list<string>, rows: list<array<string, mixed>>} */
    private function queryFiltered(array $headers, string $fromSql, array $where, string $orderBy, int $firmaId, int $limit, array $filters, ?string $dateColumn, ?string $statusColumn, ?string $clientColumn = null, array $extraExactFilters = []): array
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
                $where[] = $dateColumn . '<= :hasta';
                $params['hasta'] = $to;
            }
        }
    }

    private function dateFilter(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }
}
