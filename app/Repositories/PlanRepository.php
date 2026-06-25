<?php

declare(strict_types=1);

namespace App\Repositories;

final class PlanRepository extends BaseRepository
{
    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->pdo->query('SELECT * FROM planes WHERE deleted_at IS NULL ORDER BY nombre')->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM planes WHERE id = :id AND deleted_at IS NULL');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO planes (codigo, nombre, descripcion, estado, created_at, updated_at)
             VALUES (:codigo, :nombre, :descripcion, :estado, CURRENT_TIMESTAMP(6), CURRENT_TIMESTAMP(6))'
        );
        $statement->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE planes SET codigo=:codigo, nombre=:nombre, descripcion=:descripcion, estado=:estado, updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id AND deleted_at IS NULL'
        );
        $statement->execute($data + ['id' => $id]);
    }

    /** @param list<array{recurso: string, limite: int|null, politica: string}> $limits */
    public function replaceLimits(int $planId, array $limits): void
    {
        $delete = $this->pdo->prepare('DELETE FROM plan_limites WHERE plan_id = :plan_id');
        $delete->execute(['plan_id' => $planId]);
        $insert = $this->pdo->prepare(
            'INSERT INTO plan_limites (plan_id,recurso,limite,politica,created_at,updated_at)
             VALUES (:plan_id,:recurso,:limite,:politica,CURRENT_TIMESTAMP(6),CURRENT_TIMESTAMP(6))'
        );
        foreach ($limits as $limit) {
            $insert->execute($limit + ['plan_id' => $planId]);
        }
    }

    /** @return list<array<string, mixed>> */
    public function limits(int $planId): array
    {
        $statement = $this->pdo->prepare('SELECT recurso, limite, politica FROM plan_limites WHERE plan_id=:plan_id ORDER BY recurso');
        $statement->execute(['plan_id' => $planId]);

        return $statement->fetchAll();
    }

    public function assignToFirma(int $firmaId, int $planId): void
    {
        $close = $this->pdo->prepare(
            'UPDATE firma_planes SET estado=\'finalizado\', ends_at=CURRENT_TIMESTAMP(6)
             WHERE firma_id=:firma_id AND estado=\'activo\' AND ends_at IS NULL'
        );
        $close->execute(['firma_id' => $firmaId]);
        $insert = $this->pdo->prepare(
            'INSERT INTO firma_planes (firma_id,plan_id,estado,starts_at,created_at)
             VALUES (:firma_id,:plan_id,\'activo\',CURRENT_TIMESTAMP(6),CURRENT_TIMESTAMP(6))'
        );
        $insert->execute(['firma_id' => $firmaId, 'plan_id' => $planId]);
    }

    /** @return array<string, mixed>|null */
    public function effectiveLimit(int $firmaId, string $resource): ?array
    {
        $override = $this->pdo->prepare('SELECT limite, politica, \'firma\' AS origen FROM firma_limites WHERE firma_id=:firma_id AND recurso=:recurso');
        $override->execute(['firma_id' => $firmaId, 'recurso' => $resource]);
        $row = $override->fetch();
        if (is_array($row)) {
            return $row;
        }

        $statement = $this->pdo->prepare(
            'SELECT pl.limite, pl.politica, \'plan\' AS origen
             FROM firma_planes fp
             INNER JOIN plan_limites pl ON pl.plan_id=fp.plan_id AND pl.recurso=:recurso
             WHERE fp.firma_id=:firma_id AND fp.estado=\'activo\' AND fp.ends_at IS NULL
             ORDER BY fp.starts_at DESC LIMIT 1'
        );
        $statement->execute(['firma_id' => $firmaId, 'recurso' => $resource]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function usage(int $firmaId, string $resource): int
    {
        $tables = [
            'usuarios' => ['table' => 'usuarios', 'where' => 'deleted_at IS NULL'],
            'roles' => ['table' => 'roles', 'where' => 'deleted_at IS NULL'],
            'catalogos' => ['table' => 'catalogos', 'where' => 'deleted_at IS NULL'],
            'clientes' => ['table' => 'clientes', 'where' => 'deleted_at IS NULL'],
            'prospectos' => ['table' => 'prospectos', 'where' => 'deleted_at IS NULL'],
            'casos' => ['table' => 'casos', 'where' => 'deleted_at IS NULL'],
            'terminos' => ['table' => 'terminos', 'where' => 'deleted_at IS NULL'],
            'audiencias' => ['table' => 'audiencias', 'where' => 'deleted_at IS NULL'],
            'tareas' => ['table' => 'tareas', 'where' => 'deleted_at IS NULL'],
            'documentos' => ['table' => 'documentos', 'where' => 'deleted_at IS NULL'],
            'honorarios' => ['table' => 'honorarios', 'where' => 'deleted_at IS NULL'],
            'pagos' => ['table' => 'pagos', 'where' => 'deleted_at IS NULL'],
            'gastos' => ['table' => 'gastos', 'where' => 'deleted_at IS NULL'],
            'exportaciones' => ['table' => 'exportaciones', 'where' => 'estado<>\'expirada\''],
            'importaciones' => ['table' => 'importaciones', 'where' => 'estado<>\'expirada\''],
            'tickets_soporte' => ['table' => 'tickets_soporte', 'where' => 'deleted_at IS NULL'],
        ];
        if (!isset($tables[$resource])) {
            return 0;
        }
        $definition = $tables[$resource];
        $statement = $this->pdo->prepare(sprintf('SELECT COUNT(*) FROM %s WHERE firma_id=:firma_id AND %s', $definition['table'], $definition['where']));
        $statement->execute(['firma_id' => $firmaId]);

        return (int) $statement->fetchColumn();
    }

    public function setFirmaOverride(int $firmaId, int $planId, string $resource, ?int $limit, string $policy, string $reason): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO firma_limites (firma_id,plan_id,recurso,limite,politica,motivo,created_at,updated_at)
             VALUES (:firma_id,:plan_id,:recurso,:limite,:politica,:motivo,CURRENT_TIMESTAMP(6),CURRENT_TIMESTAMP(6))
             ON DUPLICATE KEY UPDATE plan_id=VALUES(plan_id),limite=VALUES(limite),politica=VALUES(politica),motivo=VALUES(motivo),updated_at=CURRENT_TIMESTAMP(6)'
        );
        $statement->execute(['firma_id' => $firmaId, 'plan_id' => $planId, 'recurso' => $resource, 'limite' => $limit, 'politica' => $policy, 'motivo' => $reason]);
    }
}
