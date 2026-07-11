<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\DashboardRepository;
use App\Services\CasoComunicacionService;

final class DashboardService
{
    public function __construct(
        private readonly DashboardRepository $repository,
        private readonly SaldoService $saldos,
        private readonly CasoComunicacionService $communications
    ) {
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
            'kpis' => $can('finanzas.ver') ? $this->getKpis($firmaId) : null,
            'mensajes_no_leidos' => isset($user['id']) ? $this->communications->unreadCount($firmaId, (int) $user['id']) : 0,
        ];
    }

    /** @return array<string, float> */
    public function getKpis(int $firmaId): array
    {
        $key = 'dashboard_kpis_' . $firmaId;
        try {
            if (class_exists(\Redis::class)) {
                $redis = new \Redis();
                $host = (string) \App\Core\Config::env('REDIS_HOST', '');
                if ($host !== '' && $redis->connect($host, (int) \App\Core\Config::env('REDIS_PORT', 6379), 0.2)) {
                    $cached = $redis->get($key);
                    if (is_string($cached)) {
                        $decoded = json_decode($cached, true);
                        if (is_array($decoded)) {
                            return $decoded;
                        }
                    }
                    $kpis = $this->repository->financeKpis($firmaId);
                    $redis->setex($key, 300, json_encode($kpis, JSON_THROW_ON_ERROR));
                    return $kpis;
                }
            }
        } catch (\Throwable) {
        }

        return $this->repository->financeKpis($firmaId);
    }

    public function invalidateKpis(int $firmaId): void
    {
        try {
            if (class_exists(\Redis::class)) {
                $redis = new \Redis();
                $host = (string) \App\Core\Config::env('REDIS_HOST', '');
                if ($host !== '' && $redis->connect($host, (int) \App\Core\Config::env('REDIS_PORT', 6379), 0.2)) {
                    $redis->del('dashboard_kpis_' . $firmaId);
                }
            }
        } catch (\Throwable) {
        }
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
