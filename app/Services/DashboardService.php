<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\DashboardRepository;

final class DashboardService
{
    public function __construct(
        private readonly DashboardRepository $repository,
        private readonly SaldoService $saldos
    )
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
            'finanzas' => $can('finanzas.ver') ? $this->dashboardFinance($firmaId) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function dashboardFinance(int $firmaId): array
    {
        $summary = $this->saldos->resumen($firmaId);

        return [
            'honorarios' => (float) $summary['total_honorarios'],
            'pagos' => (float) $summary['total_pagos'],
            'gastos' => (float) $summary['total_gastos'],
            'saldo' => (float) $summary['saldo'],
            'formula' => $summary['formula'] ?? $this->saldos->formula(),
        ];
    }
}
