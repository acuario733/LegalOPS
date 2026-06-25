<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\DashboardRepository;

final class DashboardService
{
    public function __construct(private readonly DashboardRepository $repository)
    {
    }

    /** @param array<string, mixed> $user @return array<string, mixed> */
    public function summary(int $firmaId, array $user): array
    {
        $permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
        $can = static fn (string $permission): bool => in_array('*', $permissions, true) || in_array($permission, $permissions, true);
        $cards = [];

        if ($can('clientes.ver')) {
            $cards['clientes_activos'] = $this->repository->countActiveClients($firmaId);
        }
        if ($can('casos.ver')) {
            $cards['casos_activos'] = $this->repository->countActiveCases($firmaId);
        }
        if ($can('terminos.ver')) {
            $cards['terminos_criticos'] = $this->repository->countCriticalTerms($firmaId);
        }
        if ($can('audiencias.ver')) {
            $cards['audiencias_proximas'] = $this->repository->countUpcomingAudiences($firmaId);
        }
        if ($can('tareas.ver')) {
            $cards['tareas_pendientes'] = $this->repository->countPendingTasks($firmaId);
        }

        return [
            'cards' => $cards,
            'documentos_recientes' => $can('documentos.ver') ? $this->repository->recentDocuments($firmaId) : null,
            'finanzas' => $can('finanzas.ver') ? $this->withBalance($this->repository->financeSummary($firmaId)) : null,
        ];
    }

    /** @param array<string, float> $summary @return array<string, float> */
    private function withBalance(array $summary): array
    {
        $summary['saldo'] = $summary['honorarios'] + $summary['gastos'] - $summary['pagos'];

        return $summary;
    }
}
