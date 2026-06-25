<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\FirmaRepository;
use App\Repositories\PlanRepository;
use App\Validators\PlanValidator;

final class PlanService
{
    public function __construct(
        private readonly PlanRepository $repository,
        private readonly FirmaRepository $firmas,
        private readonly PlanValidator $validator,
        private readonly Database $database,
        private readonly AuditoriaService $audit
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->repository->all();
    }

    /** @return array<string, mixed> */
    public function find(int $id): array
    {
        $plan = $this->repository->find($id) ?? throw new HttpException(404, 'El plan solicitado no existe.');
        $plan['limites'] = $this->repository->limits($id);

        return $plan;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, Request $request): int
    {
        $data = $this->normalize($data);
        if (!$this->validator->validateData($data)) {
            throw new HttpException(422, 'Revise los datos del plan.', $this->validator->errors());
        }

        return $this->database->transaction(function () use ($data, $request): int {
            $id = $this->repository->create($this->recordData($data));
            $this->repository->replaceLimits($id, $this->normalizeLimits((array) ($data['limites'] ?? [])));
            $this->audit->record('PLAN_CREADO', 'planes', 'plan', $id, ['codigo' => $data['codigo']], $request);

            return $id;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data, Request $request): void
    {
        $this->find($id);
        $updatesLimits = array_key_exists('limites', $data);
        $data = $this->normalize($data);
        if (!$this->validator->validateData($data)) {
            throw new HttpException(422, 'Revise los datos del plan.', $this->validator->errors());
        }
        $this->database->transaction(function () use ($id, $data, $request, $updatesLimits): void {
            $this->repository->update($id, $this->recordData($data));
            if ($updatesLimits) {
                $this->repository->replaceLimits($id, $this->normalizeLimits((array) ($data['limites'] ?? [])));
            }
            $this->audit->record('PLAN_MODIFICADO', 'planes', 'plan', $id, ['codigo' => $data['codigo']], $request);
        });
    }

    public function assign(int $firmaId, int $planId, Request $request): void
    {
        $this->firmas->find($firmaId) ?? throw new HttpException(404, 'La firma no existe.');
        $this->repository->find($planId) ?? throw new HttpException(404, 'El plan no existe.');
        $this->database->transaction(function () use ($firmaId, $planId, $request): void {
            $this->repository->assignToFirma($firmaId, $planId);
            $this->audit->record('PLAN_ASIGNADO', 'planes', 'firma', $firmaId, ['plan_id' => $planId], $request, $firmaId);
        });
    }

    /** @param array<string, mixed> $data */
    private function normalize(array $data): array
    {
        return [
            'codigo' => strtolower(trim((string) ($data['codigo'] ?? ''))),
            'nombre' => trim((string) ($data['nombre'] ?? '')),
            'descripcion' => trim((string) ($data['descripcion'] ?? '')),
            'estado' => (string) ($data['estado'] ?? 'activo'),
            'limites' => $data['limites'] ?? [],
        ];
    }

    /** @param array<string, mixed> $data @return array<string, string> */
    private function recordData(array $data): array
    {
        return [
            'codigo' => (string) $data['codigo'],
            'nombre' => (string) $data['nombre'],
            'descripcion' => (string) $data['descripcion'],
            'estado' => (string) $data['estado'],
        ];
    }

    /** @param array<mixed> $limits @return list<array{recurso: string, limite: int|null, politica: string}> */
    private function normalizeLimits(array $limits): array
    {
        $normalized = [];
        foreach ($limits as $resource => $definition) {
            if (is_array($definition)) {
                $limit = $definition['limite'] ?? null;
                $policy = (string) ($definition['politica'] ?? 'block');
            } else {
                $limit = $definition;
                $policy = 'block';
            }
            if (!in_array($policy, ['warn', 'upgrade', 'block'], true)) {
                $policy = 'block';
            }
            $normalized[] = ['recurso' => (string) $resource, 'limite' => $limit === '' || $limit === null ? null : max(0, (int) $limit), 'politica' => $policy];
        }

        return $normalized;
    }
}
