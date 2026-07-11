<?php

declare(strict_types=1);

namespace App\Repositories;

final class DashboardRepository extends BaseRepository
{
    /** @return array<string, float> */
    public function financeKpis(int $firmaId): array
    {
        $query = static function (\PDO $pdo, string $sql, int $firmaId): float {
            $statement = $pdo->prepare($sql);
            $statement->execute(['firma_id' => $firmaId]);
            return (float) $statement->fetchColumn();
        };
        $collected = $query($this->pdo, 'SELECT COALESCE(SUM(monto),0) FROM pagos WHERE firma_id=:firma_id AND estado=\'registrado\' AND fecha_pago>=DATE_FORMAT(CURRENT_DATE,\'%Y-%m-01\') AND deleted_at IS NULL', $firmaId);
        $billed = $query($this->pdo, 'SELECT COALESCE(SUM(monto),0) FROM honorarios WHERE firma_id=:firma_id AND fecha_acuerdo>=DATE_FORMAT(CURRENT_DATE,\'%Y-%m-01\') AND estado NOT IN (\'cancelado\',\'anulado\') AND deleted_at IS NULL', $firmaId);

        return [
            'ingresos_cobrados_mes' => $collected,
            'pendiente_cobro' => $query($this->pdo, 'SELECT COALESCE(SUM(monto),0) FROM honorarios WHERE firma_id=:firma_id AND estado IN (\'pendiente\',\'parcial\') AND deleted_at IS NULL', $firmaId),
            'horas_facturables_mes' => $query($this->pdo, 'SELECT COALESCE(SUM(duracion_minutos),0)/60 FROM time_entries WHERE firma_id=:firma_id AND es_facturable=1 AND fecha>=DATE_FORMAT(CURRENT_DATE,\'%Y-%m-01\') AND deleted_at IS NULL', $firmaId),
            'tasa_cobro' => $billed > 0 ? round(($collected / $billed) * 100, 2) : 0.0,
            'saldo_trust_total' => $query($this->pdo, 'SELECT COALESCE(SUM(saldo),0) FROM trust_accounts WHERE firma_id=:firma_id AND deleted_at IS NULL', $firmaId),
        ];
    }

    public function countActiveClients(int $firmaId): int
    {
        return $this->count('SELECT COUNT(*) FROM clientes WHERE firma_id=:firma_id AND estado=\'activo\' AND deleted_at IS NULL', $firmaId);
    }

    public function countActiveCases(int $firmaId): int
    {
        return $this->count('SELECT COUNT(*) FROM casos WHERE firma_id=:firma_id AND estado=\'activo\' AND deleted_at IS NULL', $firmaId);
    }

    public function countCriticalTerms(int $firmaId): int
    {
        return $this->count('SELECT COUNT(*) FROM terminos WHERE firma_id=:firma_id AND deleted_at IS NULL AND estado NOT IN (\'cumplido\',\'cancelado\') AND fecha_vencimiento <= DATE_ADD(CURRENT_DATE(), INTERVAL alerta_dias DAY)', $firmaId);
    }

    public function countUpcomingAudiences(int $firmaId): int
    {
        return $this->count('SELECT COUNT(*) FROM audiencias WHERE firma_id=:firma_id AND deleted_at IS NULL AND estado=\'programada\' AND fecha BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 14 DAY)', $firmaId);
    }

    public function countPendingTasks(int $firmaId): int
    {
        return $this->count('SELECT COUNT(*) FROM tareas WHERE firma_id=:firma_id AND deleted_at IS NULL AND estado IN (\'pendiente\',\'en_proceso\',\'vencida\')', $firmaId);
    }

    /** @return list<array<string, mixed>> */
    public function recentDocuments(int $firmaId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT d.id, d.titulo, d.updated_at, cl.nombre_razon_social AS cliente_nombre
             FROM documentos d
             LEFT JOIN clientes cl ON cl.id=d.cliente_id AND cl.firma_id=d.firma_id
             WHERE d.firma_id=:firma_id AND d.deleted_at IS NULL
             ORDER BY d.updated_at DESC LIMIT 5'
        );
        $statement->execute(['firma_id' => $firmaId]);

        return $statement->fetchAll();
    }

    private function count(string $sql, int $firmaId): int
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['firma_id' => $firmaId]);

        return (int) $statement->fetchColumn();
    }
}
