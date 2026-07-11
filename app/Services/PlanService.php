<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\FirmaRepository;
use App\Repositories\PlanRepository;
use App\Validators\PlanValidator;
use DateTimeImmutable;
use Exception;

final class PlanService
{
    public function __construct(
        private readonly PlanRepository $repository,
        private readonly FirmaRepository $firmas,
        private readonly PlanValidator $validator,
        private readonly Database $database,
        private readonly AuditoriaService $audit,
        private readonly CommercialBillingService $billing,
        private readonly CommercialStatusService $commercialStatus,
        private readonly Auth $auth
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

    /** @param array<string, mixed> $data */
    public function assign(int $firmaId, int $planId, array $data, Request $request): void
    {
        $firma = $this->firmas->find($firmaId) ?? throw new HttpException(404, 'La firma no existe.');
        $plan = $this->repository->find($planId) ?? throw new HttpException(404, 'El plan no existe.');
        $reason = $this->normalizeReason($data['motivo'] ?? '');
        $effectiveAt = $this->parseEffectiveAt($data['effective_at'] ?? null);
        $commercialStatus = $this->assignmentStatus($data['estado_comercial'] ?? ($firma['estado'] ?? CommercialStatusService::ACTIVE));
        $billing = $this->billing->normalizeForPlanAssignment($data, $effectiveAt, $commercialStatus);

        $this->database->transaction(function () use ($firmaId, $planId, $firma, $plan, $reason, $effectiveAt, $commercialStatus, $billing, $request): void {
            $current = $this->repository->currentAssignment($firmaId);
            if ($current !== null && (int) $current['plan_id'] === $planId) {
                throw new HttpException(409, 'La firma ya tiene este plan activo. Registre un cambio solo cuando el plan sea distinto.');
            }
            if ($current !== null && !empty($current['starts_at']) && $effectiveAt < new DateTimeImmutable((string) $current['starts_at'])) {
                throw new HttpException(422, 'La fecha efectiva no puede ser anterior al inicio del plan vigente.');
            }

            $userId = $this->nullableInt($this->auth->id());
            $effective = $effectiveAt->format('Y-m-d H:i:s');
            $currentStatus = $this->commercialStatus->normalize($firma['estado'] ?? null);
            $result = $this->repository->assignToFirma($firmaId, $planId, $effective, $userId, $reason, $billing);
            $previous = $result['anterior'];
            $event = $previous === null ? 'PLAN_ASIGNADO' : 'PLAN_CAMBIADO';
            if ($currentStatus !== $commercialStatus) {
                $this->firmas->setCommercialStatus($firmaId, $commercialStatus, null);
            }

            $historyId = $this->repository->recordCommercialEvent([
                'firma_id' => $firmaId,
                'evento' => strtolower($event),
                'estado_comercial' => $commercialStatus,
                'estado_comercial_anterior' => $currentStatus,
                'plan_id' => $planId,
                'plan_anterior_id' => $previous['plan_id'] ?? null,
                'firma_plan_id' => $result['firma_plan_id'],
                'effective_at' => $effective,
                'starts_at' => $effective,
                'renews_at' => $billing['renews_at'] ?? null,
                'motivo' => $reason,
                'usuario_id' => $userId,
                'metadata' => [
                    'plan_codigo' => $plan['codigo'] ?? null,
                    'plan_nombre' => $plan['nombre'] ?? null,
                    'plan_anterior_codigo' => $previous['plan_codigo'] ?? null,
                    'plan_anterior_nombre' => $previous['plan_nombre'] ?? null,
                    'billing_period' => $billing['billing_period'] ?? null,
                    'billing_anchor_day' => $billing['billing_anchor_day'] ?? null,
                    'trial_started_at' => $billing['trial_started_at'] ?? null,
                    'trial_ends_at' => $billing['trial_ends_at'] ?? null,
                    'payment_due_at' => $billing['payment_due_at'] ?? null,
                    'grace_ends_at' => $billing['grace_ends_at'] ?? null,
                    'auto_suspend_at' => $billing['auto_suspend_at'] ?? null,
                    'proration_policy' => $billing['proration_policy'] ?? null,
                    'proration_note' => $billing['proration_note'] ?? null,
                ],
            ]);

            $this->audit->record($event, 'planes', 'firma', $firmaId, [
                'plan_id' => $planId,
                'plan_anterior_id' => $previous['plan_id'] ?? null,
                'firma_plan_id' => $result['firma_plan_id'],
                'historial_id' => $historyId,
                'fecha_efectiva' => $effective,
                'renovacion_at' => $billing['renews_at'] ?? null,
                'estado_comercial' => $commercialStatus,
                'estado_comercial_anterior' => $currentStatus,
                'proration_policy' => $billing['proration_policy'] ?? null,
                'motivo' => $reason,
            ], $request, $firmaId, $previous === null ? 'info' : 'warning');
        });
    }

    /** @return list<array<string, mixed>> */
    public function commercialHistory(int $firmaId, int $limit = 20): array
    {
        $this->firmas->find($firmaId) ?? throw new HttpException(404, 'La firma no existe.');

        return $this->repository->commercialHistory($firmaId, $limit);
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

    private function normalizeReason(mixed $value): string
    {
        $reason = trim((string) $value);
        if (mb_strlen($reason) < 5) {
            throw new HttpException(422, 'Debe indicar un motivo comercial de al menos 5 caracteres.');
        }

        return mb_substr($reason, 0, 500);
    }

    private function assignmentStatus(mixed $value): string
    {
        $status = $this->commercialStatus->normalize($value);
        if (!in_array($status, [CommercialStatusService::ACTIVE, CommercialStatusService::TRIAL], true)) {
            throw new HttpException(422, 'La asignacion de plan solo puede iniciar como activa o prueba. Use facturacion para marcar pago vencido.');
        }

        return $status;
    }

    private function parseEffectiveAt(mixed $value): DateTimeImmutable
    {
        $date = $this->parseOptionalDateTime($value) ?? new DateTimeImmutable();
        if ($date > new DateTimeImmutable('+60 seconds')) {
            throw new HttpException(422, 'No se permiten cambios de plan con fecha futura hasta aprobar la regla de programacion comercial.');
        }

        return $date;
    }

    private function parseOptionalDateTime(mixed $value): ?DateTimeImmutable
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }
        $raw = str_replace('T', ' ', $raw);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            $raw .= ' 00:00:00';
        }

        try {
            return new DateTimeImmutable($raw);
        } catch (Exception) {
            throw new HttpException(422, 'La fecha comercial indicada no es valida.');
        }
    }

    private function nullableInt(int|string|null $value): ?int
    {
        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : null;
    }
}
