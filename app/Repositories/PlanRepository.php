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

    /** @param array<string, mixed> $billing @return array{firma_plan_id: int, anterior: array<string, mixed>|null} */
    public function assignToFirma(int $firmaId, int $planId, string $effectiveAt, ?int $userId, string $reason, array $billing): array
    {
        $current = $this->currentAssignment($firmaId);
        if ($current !== null) {
            $close = $this->pdo->prepare(
                'UPDATE firma_planes
                 SET estado=\'finalizado\', ends_at=:effective_at
                 WHERE id=:id AND firma_id=:firma_id AND estado=\'activo\' AND ends_at IS NULL'
            );
            $close->execute(['effective_at' => $effectiveAt, 'id' => (int) $current['id'], 'firma_id' => $firmaId]);
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO firma_planes (
                firma_id,plan_id,estado,effective_at,starts_at,renews_at,billing_period,billing_anchor_day,
                trial_started_at,trial_ends_at,payment_due_at,grace_ends_at,auto_suspend_at,proration_policy,proration_note,
                assigned_by_usuario_id,motivo,created_at
             ) VALUES (
                :firma_id,:plan_id,\'activo\',:effective_at,:starts_at,:renews_at,:billing_period,:billing_anchor_day,
                :trial_started_at,:trial_ends_at,:payment_due_at,:grace_ends_at,:auto_suspend_at,:proration_policy,:proration_note,
                :assigned_by_usuario_id,:motivo,CURRENT_TIMESTAMP(6)
             )'
        );
        $insert->execute([
            'firma_id' => $firmaId,
            'plan_id' => $planId,
            'effective_at' => $effectiveAt,
            'starts_at' => $effectiveAt,
            'renews_at' => $billing['renews_at'] ?? null,
            'billing_period' => $billing['billing_period'] ?? 'monthly',
            'billing_anchor_day' => $billing['billing_anchor_day'] ?? null,
            'trial_started_at' => $billing['trial_started_at'] ?? null,
            'trial_ends_at' => $billing['trial_ends_at'] ?? null,
            'payment_due_at' => $billing['payment_due_at'] ?? null,
            'grace_ends_at' => $billing['grace_ends_at'] ?? null,
            'auto_suspend_at' => $billing['auto_suspend_at'] ?? null,
            'proration_policy' => $billing['proration_policy'] ?? 'manual_review',
            'proration_note' => $billing['proration_note'] ?? null,
            'assigned_by_usuario_id' => $userId,
            'motivo' => $reason,
        ]);
        $newId = (int) $this->pdo->lastInsertId();

        if ($current !== null) {
            $replace = $this->pdo->prepare(
                'UPDATE firma_planes
                 SET replaced_by_firma_plan_id=:new_id
                 WHERE id=:id AND firma_id=:firma_id'
            );
            $replace->execute(['new_id' => $newId, 'id' => (int) $current['id'], 'firma_id' => $firmaId]);
        }

        return ['firma_plan_id' => $newId, 'anterior' => $current];
    }

    /** @return array<string, mixed>|null */
    public function currentAssignment(int $firmaId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT fp.*, p.codigo AS plan_codigo, p.nombre AS plan_nombre, u.nombre AS assigned_by_nombre
             FROM firma_planes fp
             INNER JOIN planes p ON p.id=fp.plan_id
             LEFT JOIN usuarios u ON u.id=fp.assigned_by_usuario_id
             WHERE fp.firma_id=:firma_id AND fp.estado=\'activo\' AND fp.ends_at IS NULL
             ORDER BY fp.starts_at DESC, fp.id DESC
             LIMIT 1'
        );
        $statement->execute(['firma_id' => $firmaId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $billing */
    public function updateBillingRules(int $firmaPlanId, array $billing): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE firma_planes
             SET renews_at=:renews_at,
                 billing_period=:billing_period,
                 billing_anchor_day=:billing_anchor_day,
                 trial_started_at=:trial_started_at,
                 trial_ends_at=:trial_ends_at,
                 payment_due_at=:payment_due_at,
                 grace_ends_at=:grace_ends_at,
                 auto_suspend_at=:auto_suspend_at,
                 proration_policy=:proration_policy,
                 proration_note=:proration_note
             WHERE id=:id AND estado=\'activo\' AND ends_at IS NULL'
        );
        $statement->execute([
            'id' => $firmaPlanId,
            'renews_at' => $billing['renews_at'] ?? null,
            'billing_period' => $billing['billing_period'] ?? 'monthly',
            'billing_anchor_day' => $billing['billing_anchor_day'] ?? null,
            'trial_started_at' => $billing['trial_started_at'] ?? null,
            'trial_ends_at' => $billing['trial_ends_at'] ?? null,
            'payment_due_at' => $billing['payment_due_at'] ?? null,
            'grace_ends_at' => $billing['grace_ends_at'] ?? null,
            'auto_suspend_at' => $billing['auto_suspend_at'] ?? null,
            'proration_policy' => $billing['proration_policy'] ?? 'manual_review',
            'proration_note' => $billing['proration_note'] ?? null,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function automaticSuspensionCandidates(string $now): array
    {
        $statement = $this->pdo->prepare(
            'SELECT fp.*, f.estado AS firma_estado, f.nombre AS firma_nombre
             FROM firma_planes fp
             INNER JOIN firmas f ON f.id=fp.firma_id
             WHERE fp.estado=\'activo\'
               AND fp.ends_at IS NULL
               AND f.deleted_at IS NULL
               AND f.estado=\'pago_vencido\'
               AND fp.payment_due_at IS NOT NULL
               AND fp.payment_due_at<=:now_due
               AND fp.auto_suspend_at IS NOT NULL
               AND fp.auto_suspend_at<=:now_suspend
             ORDER BY fp.auto_suspend_at ASC, fp.id ASC'
        );
        $statement->execute(['now_due' => $now, 'now_suspend' => $now]);

        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function commercialHistory(int $firmaId, int $limit = 20): array
    {
        $limit = min(100, max(1, $limit));
        $statement = $this->pdo->prepare(
            sprintf(
                'SELECT h.*, p.nombre AS plan_nombre, pa.nombre AS plan_anterior_nombre, u.nombre AS usuario_nombre
                 FROM firma_comercial_historial h
                 LEFT JOIN planes p ON p.id=h.plan_id
                 LEFT JOIN planes pa ON pa.id=h.plan_anterior_id
                 LEFT JOIN usuarios u ON u.id=h.usuario_id
                 WHERE h.firma_id=:firma_id
                 ORDER BY h.created_at DESC, h.id DESC
                 LIMIT %d',
                $limit
            )
        );
        $statement->execute(['firma_id' => $firmaId]);

        return $statement->fetchAll();
    }

    /** @param array<string, mixed> $data */
    public function recordCommercialEvent(array $data): int
    {
        $metadata = null;
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $encoded = json_encode($data['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $metadata = is_string($encoded) ? $encoded : null;
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO firma_comercial_historial (
                firma_id, evento, estado_comercial, estado_comercial_anterior, plan_id, plan_anterior_id,
                firma_plan_id, recurso, limite_anterior, limite_nuevo, politica_anterior, politica_nueva,
                effective_at, starts_at, ends_at, renews_at, motivo, usuario_id, metadata, created_at
             ) VALUES (
                :firma_id, :evento, :estado_comercial, :estado_comercial_anterior, :plan_id, :plan_anterior_id,
                :firma_plan_id, :recurso, :limite_anterior, :limite_nuevo, :politica_anterior, :politica_nueva,
                :effective_at, :starts_at, :ends_at, :renews_at, :motivo, :usuario_id, :metadata, CURRENT_TIMESTAMP(6)
             )'
        );
        $statement->execute([
            'firma_id' => (int) $data['firma_id'],
            'evento' => (string) $data['evento'],
            'estado_comercial' => $data['estado_comercial'] ?? null,
            'estado_comercial_anterior' => $data['estado_comercial_anterior'] ?? null,
            'plan_id' => $data['plan_id'] ?? null,
            'plan_anterior_id' => $data['plan_anterior_id'] ?? null,
            'firma_plan_id' => $data['firma_plan_id'] ?? null,
            'recurso' => $data['recurso'] ?? null,
            'limite_anterior' => $data['limite_anterior'] ?? null,
            'limite_nuevo' => $data['limite_nuevo'] ?? null,
            'politica_anterior' => $data['politica_anterior'] ?? null,
            'politica_nueva' => $data['politica_nueva'] ?? null,
            'effective_at' => $data['effective_at'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'renews_at' => $data['renews_at'] ?? null,
            'motivo' => mb_substr((string) ($data['motivo'] ?? 'Cambio comercial registrado.'), 0, 500),
            'usuario_id' => $data['usuario_id'] ?? null,
            'metadata' => $metadata,
        ]);

        return (int) $this->pdo->lastInsertId();
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

    /** @return array<string, mixed>|null */
    public function firmaOverride(int $firmaId, string $resource): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM firma_limites WHERE firma_id=:firma_id AND recurso=:recurso');
        $statement->execute(['firma_id' => $firmaId, 'recurso' => $resource]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }
}
