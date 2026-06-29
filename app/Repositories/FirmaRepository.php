<?php

declare(strict_types=1);

namespace App\Repositories;

final class FirmaRepository extends BaseRepository
{
    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->pdo->query(
            'SELECT f.id, f.uuid, f.nombre, f.slug, f.estado, f.timezone, f.suspended_at, f.created_at,
                    fp.id AS firma_plan_id, fp.estado AS plan_estado, fp.effective_at AS plan_effective_at,
                    fp.starts_at AS plan_starts_at, fp.renews_at AS plan_renews_at,
                    fp.billing_period AS plan_billing_period, fp.billing_anchor_day AS plan_billing_anchor_day,
                    fp.trial_started_at AS plan_trial_started_at, fp.trial_ends_at AS plan_trial_ends_at,
                    fp.payment_due_at AS plan_payment_due_at, fp.grace_ends_at AS plan_grace_ends_at,
                    fp.auto_suspend_at AS plan_auto_suspend_at, fp.proration_policy AS plan_proration_policy,
                    fp.proration_note AS plan_proration_note, fp.motivo AS plan_motivo,
                    fp.assigned_by_usuario_id, p.nombre AS plan_nombre, p.codigo AS plan_codigo,
                    u.nombre AS plan_assigned_by_nombre
             FROM firmas f
             LEFT JOIN firma_planes fp ON fp.firma_id = f.id AND fp.estado = \'activo\' AND fp.ends_at IS NULL
             LEFT JOIN planes p ON p.id = fp.plan_id
             LEFT JOIN usuarios u ON u.id = fp.assigned_by_usuario_id
             WHERE f.deleted_at IS NULL
             ORDER BY f.nombre'
        )->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM firmas WHERE id = :id AND deleted_at IS NULL');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM firmas WHERE slug = :slug AND deleted_at IS NULL');
        $statement->execute(['slug' => $slug]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO firmas (uuid, nombre, slug, estado, timezone, created_at, updated_at)
             VALUES (:uuid, :nombre, :slug, \'activa\', :timezone, CURRENT_TIMESTAMP(6), CURRENT_TIMESTAMP(6))'
        );
        $statement->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE firmas SET nombre = :nombre, slug = :slug, timezone = :timezone, updated_at = CURRENT_TIMESTAMP(6)
             WHERE id = :id AND deleted_at IS NULL'
        );
        $statement->execute($data + ['id' => $id]);
    }

    public function setStatus(int $id, string $status, ?string $reason): void
    {
        $this->setCommercialStatus($id, $status, $reason);
    }

    public function setCommercialStatus(int $id, string $status, ?string $reason): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE firmas
             SET estado = :estado,
                 suspended_at = CASE WHEN :estado_suspended = \'suspendida\' THEN COALESCE(suspended_at, CURRENT_TIMESTAMP(6)) ELSE NULL END,
                 suspension_reason = CASE WHEN :estado_reason = \'suspendida\' THEN :motivo ELSE NULL END,
                 updated_at = CURRENT_TIMESTAMP(6)
             WHERE id = :id AND deleted_at IS NULL'
        );
        $statement->execute([
            'estado' => $status,
            'estado_suspended' => $status,
            'estado_reason' => $status,
            'motivo' => $reason,
            'id' => $id,
        ]);
    }

    /** @return array<string, int> */
    public function usage(int $id): array
    {
        $statement = $this->pdo->prepare(
            'SELECT
                (SELECT COUNT(*) FROM usuarios WHERE firma_id = :firma_usuarios AND deleted_at IS NULL) AS usuarios,
                (SELECT COUNT(*) FROM roles WHERE firma_id = :firma_roles AND deleted_at IS NULL) AS roles,
                (SELECT COUNT(*) FROM catalogos WHERE firma_id = :firma_catalogos AND deleted_at IS NULL) AS catalogos'
        );
        $statement->execute(['firma_usuarios' => $id, 'firma_roles' => $id, 'firma_catalogos' => $id]);
        $row = $statement->fetch() ?: [];

        return array_map('intval', $row);
    }
}
