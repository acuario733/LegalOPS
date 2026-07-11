<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ReporteRepository;
use App\Jobs\AuditExportJob;

final class ReporteService
{
    public function __construct(
        private readonly ReporteRepository $repository,
        private readonly ?QueueService $queue = null
    ) {
    }

    /** @var array<string, string> */
    private array $permissions = [
        'clientes' => 'clientes.ver',
        'casos' => 'casos.ver',
        'terminos' => 'terminos.ver',
        'audiencias' => 'audiencias.ver',
        'tareas' => 'tareas.ver',
        'documentos' => 'documentos.ver',
        'finanzas' => 'finanzas.ver',
        'auditoria' => 'auditoria.ver',
    ];

    /** @param array<string, mixed> $user @return array<string, string> */
    public function available(array $user): array
    {
        $available = [];
        foreach ($this->permissions as $type => $permission) {
            if ($this->can($user, $permission)) {
                $available[$type] = ucfirst($type);
            }
        }

        return $available;
    }

    /** @param array<string, mixed> $user */
    public function canExport(array $user, string $type): bool
    {
        return isset($this->permissions[$type]) && $this->can($user, $this->permissions[$type]);
    }

    /** @return array<string, array{items: list<array<string, mixed>>, total: float}> */
    public function getAging(int $firmaId, ?string $cutoff = null): array
    {
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $cutoff) === 1 ? (string) $cutoff : date('Y-m-d');
        $result = [
            '0-30' => ['items' => [], 'total' => 0.0],
            '31-60' => ['items' => [], 'total' => 0.0],
            '61-90' => ['items' => [], 'total' => 0.0],
            'mas90' => ['items' => [], 'total' => 0.0],
        ];
        foreach ($this->repository->aging($firmaId, $date) as $item) {
            $days = (int) $item['dias_vencido'];
            $bucket = $days <= 30 ? '0-30' : ($days <= 60 ? '31-60' : ($days <= 90 ? '61-90' : 'mas90'));
            $result[$bucket]['items'][] = $item;
            $result[$bucket]['total'] += (float) $item['monto'];
        }

        return $result;
    }

    /** @return array{tasa_conversion: float,tiempo_promedio_por_etapa: array<string, float>,leads_por_fuente: array<string, int>,total: int,convertidos: int} */
    public function getCrm(int $firmaId, ?string $from = null, ?string $to = null): array
    {
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $from) === 1 ? (string) $from : date('Y-m-01');
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $to) === 1 ? (string) $to : date('Y-m-d');
        $prospects = $this->repository->crmProspects($firmaId, $from, $to);
        $sources = [];
        $durations = [];
        $converted = 0;
        foreach ($prospects as $prospect) {
            $source = trim((string) ($prospect['fuente'] ?? '')) ?: 'sin_fuente';
            $sources[$source] = ($sources[$source] ?? 0) + 1;
            if ((string) $prospect['estado'] === 'ganado') {
                $converted++;
            }
            $cursor = new \DateTimeImmutable((string) $prospect['created_at']);
            $stage = 'nuevo';
            foreach ($this->repository->prospectStatusHistory($firmaId, (int) $prospect['id']) as $change) {
                $changedAt = new \DateTimeImmutable((string) $change['created_at']);
                $stage = trim((string) ($change['estado_anterior'] ?? '')) ?: $stage;
                $durations[$stage][] = max(0, ($changedAt->getTimestamp() - $cursor->getTimestamp()) / 86400);
                $cursor = $changedAt;
                $stage = (string) $change['estado_nuevo'];
            }
        }
        $averages = [];
        foreach ($durations as $stage => $values) {
            $averages[$stage] = round(array_sum($values) / count($values), 2);
        }
        ksort($sources);
        ksort($averages);
        $total = count($prospects);

        return [
            'tasa_conversion' => $total > 0 ? round(($converted / $total) * 100, 2) : 0.0,
            'tiempo_promedio_por_etapa' => $averages,
            'leads_por_fuente' => $sources,
            'total' => $total,
            'convertidos' => $converted,
        ];
    }

    /** @param array<string, mixed> $filters */
    public function exportAudit(int $firmaId, array $filters, int $userId): void
    {
        if ($this->queue === null) {
            throw new \RuntimeException('La cola no esta disponible para exportar auditoria.');
        }
        $this->queue->dispatch(AuditExportJob::class, [
            'firma_id' => $firmaId,
            'usuario_id' => $userId,
            'filtros' => $filters,
        ], 'exports');
    }

    /** @param array<string, mixed> $user */
    private function can(array $user, string $permission): bool
    {
        $permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }
}
