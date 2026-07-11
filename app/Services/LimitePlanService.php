<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\PlanRepository;

final class LimitePlanService
{
    public function __construct(
        private readonly PlanRepository $repository,
        private readonly AuditoriaService $audit,
        private readonly Auth $auth
    ) {
    }

    /** @return array<string, mixed> */
    public function evaluate(int $firmaId, string $resource): array
    {
        $definition = $this->repository->effectiveLimit($firmaId, $resource);
        if ($definition === null || $definition['limite'] === null) {
            return [
                'allowed' => true,
                'resource' => $resource,
                'policy' => 'none',
                'origin' => $definition['origen'] ?? null,
                'limit' => null,
                'usage' => $this->repository->usage($firmaId, $resource),
                'remaining' => null,
                'exceeded' => false,
                'code' => 'PLAN_LIMIT_NOT_CONFIGURED',
                'message' => 'No existe límite configurado para este recurso.',
            ];
        }

        $limit = (int) $definition['limite'];
        $usage = $this->repository->usage($firmaId, $resource);
        $policy = (string) ($definition['politica'] ?? 'block');
        if (!in_array($policy, ['warn', 'upgrade', 'block'], true)) {
            $policy = 'block';
        }
        $exceeded = $usage >= $limit;
        $allowed = !$exceeded || $policy !== 'block';
        $messages = [
            'warn' => 'El recurso supera el límite del plan, pero la política permite continuar con advertencia.',
            'upgrade' => 'El recurso supera el límite del plan y debe revisarse una ampliación o cambio de plan.',
            'block' => 'El límite del plan no permite crear más recursos.',
        ];

        return [
            'allowed' => $allowed,
            'resource' => $resource,
            'policy' => $policy,
            'origin' => $definition['origen'] ?? null,
            'limit' => $limit,
            'usage' => $usage,
            'remaining' => max(0, $limit - $usage),
            'exceeded' => $exceeded,
            'code' => $allowed ? 'PLAN_LIMIT_ALLOWED' : 'PLAN_LIMIT_REACHED',
            'message' => $exceeded ? $messages[$policy] : 'El uso actual está dentro del límite del plan.',
        ];
    }

    public function canCreate(int $firmaId, string $resource, ?Request $request = null): bool
    {
        $evaluation = $this->evaluate($firmaId, $resource);

        return (bool) $evaluation['allowed'];
    }

    public function requireCapacity(int $firmaId, string $resource, ?Request $request = null): void
    {
        $evaluation = $this->evaluate($firmaId, $resource);
        $this->recordEvaluationIfNeeded($firmaId, $evaluation, $request);
        if (!(bool) $evaluation['allowed']) {
            throw new HttpException(409, (string) $evaluation['message'], [
                'code' => 'PLAN_LIMIT_REACHED',
                'recurso' => $resource,
                'politica' => $evaluation['policy'],
                'limite' => $evaluation['limit'],
                'uso_actual' => $evaluation['usage'],
            ]);
        }
    }

    public function override(int $firmaId, int $planId, string $resource, ?int $limit, string $policy, string $reason, Request $request): void
    {
        if (!in_array($policy, ['warn', 'upgrade', 'block'], true) || mb_strlen(trim($reason)) < 5) {
            throw new HttpException(422, 'La excepción de límite no es válida.');
        }
        $reason = mb_substr(trim($reason), 0, 255);
        $before = $this->repository->firmaOverride($firmaId, $resource);
        $this->repository->setFirmaOverride($firmaId, $planId, $resource, $limit, $policy, $reason);
        $historyId = $this->repository->recordCommercialEvent([
            'firma_id' => $firmaId,
            'evento' => 'limite_excepcion_modificada',
            'plan_id' => $planId,
            'recurso' => $resource,
            'limite_anterior' => $before['limite'] ?? null,
            'limite_nuevo' => $limit,
            'politica_anterior' => $before['politica'] ?? null,
            'politica_nueva' => $policy,
            'motivo' => $reason,
            'usuario_id' => $this->nullableInt($this->auth->id()),
        ]);
        $this->audit->record('LIMITE_EXCEPCION_MODIFICADA', 'limites', 'firma', $firmaId, [
            'plan_id' => $planId, 'recurso' => $resource, 'limite' => $limit, 'politica' => $policy, 'motivo' => $reason, 'historial_id' => $historyId,
        ], $request, $firmaId, 'warning');
    }

    private function nullableInt(int|string|null $value): ?int
    {
        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : null;
    }

    /** @param array<string, mixed> $evaluation */
    private function recordEvaluationIfNeeded(int $firmaId, array $evaluation, ?Request $request): void
    {
        if ($request === null || !(bool) ($evaluation['exceeded'] ?? false)) {
            return;
        }

        $policy = (string) ($evaluation['policy'] ?? 'block');
        $action = match ($policy) {
            'warn' => 'LIMITE_PLAN_ADVERTENCIA',
            'upgrade' => 'LIMITE_PLAN_UPGRADE_REQUERIDO',
            default => 'LIMITE_PLAN_BLOQUEADO',
        };
        $severity = $policy === 'block' ? 'warning' : 'info';
        $this->audit->record($action, 'limites', 'firma', $firmaId, [
            'recurso' => $evaluation['resource'] ?? null,
            'politica' => $policy,
            'limite' => $evaluation['limit'] ?? null,
            'uso_actual' => $evaluation['usage'] ?? null,
            'origen' => $evaluation['origin'] ?? null,
        ], $request, $firmaId, $severity);
    }
}
