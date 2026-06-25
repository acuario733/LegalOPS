<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\PlanRepository;

final class LimitePlanService
{
    public function __construct(
        private readonly PlanRepository $repository,
        private readonly AuditoriaService $audit
    ) {
    }

    public function canCreate(int $firmaId, string $resource): bool
    {
        $definition = $this->repository->effectiveLimit($firmaId, $resource);
        if ($definition === null || $definition['limite'] === null) {
            return true;
        }

        return $this->repository->usage($firmaId, $resource) < (int) $definition['limite'] || $definition['politica'] !== 'block';
    }

    public function requireCapacity(int $firmaId, string $resource): void
    {
        if (!$this->canCreate($firmaId, $resource)) {
            throw new HttpException(409, 'El límite del plan no permite crear más recursos.', ['code' => 'PLAN_LIMIT_REACHED']);
        }
    }

    public function override(int $firmaId, int $planId, string $resource, ?int $limit, string $policy, string $reason, Request $request): void
    {
        if (!in_array($policy, ['warn', 'upgrade', 'block'], true) || mb_strlen(trim($reason)) < 5) {
            throw new HttpException(422, 'La excepción de límite no es válida.');
        }
        $reason = mb_substr(trim($reason), 0, 255);
        $this->repository->setFirmaOverride($firmaId, $planId, $resource, $limit, $policy, $reason);
        $this->audit->record('LIMITE_EXCEPCION_MODIFICADA', 'limites', 'firma', $firmaId, [
            'plan_id' => $planId, 'recurso' => $resource, 'limite' => $limit, 'politica' => $policy, 'motivo' => $reason,
        ], $request, $firmaId, 'warning');
    }
}
