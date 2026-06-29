<?php

declare(strict_types=1);

namespace App\Repositories;

final class DashboardRepository extends BaseRepository
{
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
