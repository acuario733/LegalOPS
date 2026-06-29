<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\FirmaRepository;
use App\Repositories\PlanRepository;
use App\Repositories\RolRepository;
use App\Repositories\UserSessionRepository;
use App\Validators\FirmaValidator;

final class FirmaService
{
    public function __construct(
        private readonly FirmaRepository $repository,
        private readonly PlanRepository $plans,
        private readonly RolRepository $roles,
        private readonly UserSessionRepository $sessions,
        private readonly FirmaValidator $validator,
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
        return $this->repository->find($id) ?? throw new HttpException(404, 'La firma solicitada no existe.');
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, Request $request): int
    {
        $data = $this->normalize($data);
        if (!$this->validator->validateData($data)) {
            throw new HttpException(422, 'Revise los datos de la firma.', $this->validator->errors());
        }
        if ($this->repository->findBySlug($data['slug']) !== null) {
            throw new HttpException(409, 'El identificador URL de la firma ya esta en uso.', ['slug' => ['El slug ya existe.']]);
        }

        return $this->database->transaction(function () use ($data, $request): int {
            $id = $this->repository->create($data + ['uuid' => $this->uuid()]);
            $roleId = $this->roles->create($id, [
                'codigo' => 'administrador',
                'nombre' => 'Administrador de firma',
                'descripcion' => 'Rol base protegido con administracion completa de la firma.',
                'estado' => 'activo',
                'is_protected' => 1,
            ]);
            $tenantPermissions = array_map('strval', (array) Config::get('permissions.firma', []));
            $permissionIds = array_map(
                static fn (array $permission): int => (int) $permission['id'],
                array_filter($this->roles->permissions(), static fn (array $permission): bool => in_array((string) $permission['codigo'], $tenantPermissions, true))
            );
            $this->roles->syncPermissions($id, $roleId, $permissionIds);
            $this->audit->record('FIRMA_CREADA', 'firmas', 'firma', $id, ['nombre' => $data['nombre']], $request, $id);

            return $id;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data, Request $request): void
    {
        $before = $this->find($id);
        $data = $this->normalize($data);
        if (!$this->validator->validateData($data)) {
            throw new HttpException(422, 'Revise los datos de la firma.', $this->validator->errors());
        }
        $slugOwner = $this->repository->findBySlug($data['slug']);
        if ($slugOwner !== null && (int) $slugOwner['id'] !== $id) {
            throw new HttpException(409, 'El identificador URL de la firma ya esta en uso.');
        }

        $this->database->transaction(function () use ($id, $data, $before, $request): void {
            $this->repository->update($id, $data);
            $this->audit->record('FIRMA_MODIFICADA', 'firmas', 'firma', $id, [
                'anterior' => ['nombre' => $before['nombre'], 'slug' => $before['slug'], 'timezone' => $before['timezone']],
                'nuevo' => $data,
            ], $request, $id);
        });
    }

    /** @param array<string, mixed> $data */
    public function updateBilling(int $id, array $data, Request $request): void
    {
        $firma = $this->find($id);
        $currentStatus = $this->commercialStatus->normalize($firma['estado'] ?? null);
        if (in_array($currentStatus, [CommercialStatusService::SUSPENDED, CommercialStatusService::CANCELLED], true)) {
            throw new HttpException(409, 'Use el flujo de reactivacion antes de modificar facturacion de una firma suspendida o cancelada.');
        }

        $assignment = $this->plans->currentAssignment($id) ?? throw new HttpException(409, 'La firma debe tener un plan activo antes de configurar facturacion.');
        $reason = $this->normalizeCommercialReason($data['motivo'] ?? '');
        $newStatus = $this->billingStatus($data['estado_comercial'] ?? $currentStatus);
        $billing = $this->billing->normalizeForBillingUpdate($data, $assignment, $newStatus);

        $this->database->transaction(function () use ($id, $assignment, $currentStatus, $newStatus, $billing, $reason, $request): void {
            $this->plans->updateBillingRules((int) $assignment['id'], $billing);
            if ($currentStatus !== $newStatus) {
                $this->repository->setCommercialStatus($id, $newStatus, null);
            }

            $historyId = $this->plans->recordCommercialEvent([
                'firma_id' => $id,
                'evento' => 'facturacion_actualizada',
                'estado_comercial' => $newStatus,
                'estado_comercial_anterior' => $currentStatus,
                'plan_id' => $assignment['plan_id'] ?? null,
                'firma_plan_id' => $assignment['id'] ?? null,
                'effective_at' => $assignment['effective_at'] ?? $assignment['starts_at'] ?? null,
                'starts_at' => $assignment['starts_at'] ?? null,
                'renews_at' => $billing['renews_at'] ?? null,
                'motivo' => $reason,
                'usuario_id' => $this->nullableInt($this->auth->id()),
                'metadata' => [
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

            $this->audit->record('FACTURACION_COMERCIAL_ACTUALIZADA', 'firmas', 'firma', $id, [
                'historial_id' => $historyId,
                'estado_anterior' => $currentStatus,
                'estado_nuevo' => $newStatus,
                'renovacion_at' => $billing['renews_at'] ?? null,
                'payment_due_at' => $billing['payment_due_at'] ?? null,
                'grace_ends_at' => $billing['grace_ends_at'] ?? null,
                'auto_suspend_at' => $billing['auto_suspend_at'] ?? null,
                'proration_policy' => $billing['proration_policy'] ?? null,
            ], $request, $id, $newStatus === CommercialStatusService::PAYMENT_DUE ? 'warning' : 'info');
        });
    }

    public function suspend(int $id, string $reason, Request $request): void
    {
        $firma = $this->find($id);
        if (mb_strlen(trim($reason)) < 5) {
            throw new HttpException(422, 'Debe indicar un motivo de suspension valido.');
        }
        $this->database->transaction(function () use ($id, $firma, $reason, $request): void {
            $assignment = $this->plans->currentAssignment($id);
            $previousStatus = $this->commercialStatus->normalize($firma['estado'] ?? null);
            $normalizedReason = mb_substr(trim($reason), 0, 255);
            $this->repository->setStatus($id, CommercialStatusService::SUSPENDED, $normalizedReason);
            $this->sessions->revokeAllForFirma($id, 'firma_suspendida');

            $historyId = $this->plans->recordCommercialEvent([
                'firma_id' => $id,
                'evento' => 'firma_suspendida',
                'estado_comercial' => CommercialStatusService::SUSPENDED,
                'estado_comercial_anterior' => $previousStatus,
                'plan_id' => $assignment['plan_id'] ?? null,
                'firma_plan_id' => $assignment['id'] ?? null,
                'effective_at' => date('Y-m-d H:i:s'),
                'starts_at' => $assignment['starts_at'] ?? null,
                'renews_at' => $assignment['renews_at'] ?? null,
                'motivo' => $normalizedReason,
                'usuario_id' => $this->nullableInt($this->auth->id()),
                'metadata' => [
                    'consequence' => $this->commercialStatus->consequence(CommercialStatusService::SUSPENDED),
                ],
            ]);

            $this->audit->record('FIRMA_SUSPENDIDA', 'firmas', 'firma', $id, [
                'historial_id' => $historyId,
                'estado_anterior' => $previousStatus,
                'motivo' => $normalizedReason,
            ], $request, $id, 'warning');
        });
    }

    public function reactivate(int $id, Request $request): void
    {
        $firma = $this->find($id);
        $reason = $this->normalizeCommercialReason($request->input('motivo', ''));
        $this->database->transaction(function () use ($id, $firma, $reason, $request): void {
            $assignment = $this->plans->currentAssignment($id);
            $previousStatus = $this->commercialStatus->normalize($firma['estado'] ?? null);
            $this->repository->setStatus($id, CommercialStatusService::ACTIVE, null);

            $historyId = $this->plans->recordCommercialEvent([
                'firma_id' => $id,
                'evento' => 'firma_reactivada',
                'estado_comercial' => CommercialStatusService::ACTIVE,
                'estado_comercial_anterior' => $previousStatus,
                'plan_id' => $assignment['plan_id'] ?? null,
                'firma_plan_id' => $assignment['id'] ?? null,
                'effective_at' => date('Y-m-d H:i:s'),
                'starts_at' => $assignment['starts_at'] ?? null,
                'renews_at' => $assignment['renews_at'] ?? null,
                'motivo' => $reason,
                'usuario_id' => $this->nullableInt($this->auth->id()),
                'metadata' => [
                    'consequence' => $this->commercialStatus->consequence(CommercialStatusService::ACTIVE),
                    'payment_due_at' => $assignment['payment_due_at'] ?? null,
                    'grace_ends_at' => $assignment['grace_ends_at'] ?? null,
                ],
            ]);

            $this->audit->record('FIRMA_REACTIVADA', 'firmas', 'firma', $id, [
                'historial_id' => $historyId,
                'estado_anterior' => $previousStatus,
                'motivo' => $reason,
            ], $request, $id);
        });
    }

    /** @return array{checked: int, suspended: int, skipped: int} */
    public function runAutomaticSuspensions(Request $request): array
    {
        return $this->billing->suspendOverdue($request);
    }

    /** @return array<string, int> */
    public function usage(int $id): array
    {
        $this->find($id);

        return $this->repository->usage($id);
    }

    /** @param array<string, mixed> $data @return array<string, string> */
    private function normalize(array $data): array
    {
        $slug = strtolower(trim((string) ($data['slug'] ?? '')));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return [
            'nombre' => trim((string) ($data['nombre'] ?? '')),
            'slug' => trim($slug, '-'),
            'timezone' => trim((string) ($data['timezone'] ?? 'UTC')),
        ];
    }

    private function billingStatus(mixed $value): string
    {
        $status = $this->commercialStatus->normalize($value);
        if (!in_array($status, [CommercialStatusService::ACTIVE, CommercialStatusService::TRIAL, CommercialStatusService::PAYMENT_DUE], true)) {
            throw new HttpException(422, 'Facturacion solo puede marcar activa, prueba o pago vencido en esta fase.');
        }

        return $status;
    }

    private function normalizeCommercialReason(mixed $value): string
    {
        $reason = trim((string) $value);
        if (mb_strlen($reason) < 5) {
            throw new HttpException(422, 'Debe indicar un motivo comercial de al menos 5 caracteres.');
        }

        return mb_substr($reason, 0, 500);
    }

    private function nullableInt(int|string|null $value): ?int
    {
        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : null;
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }
}
