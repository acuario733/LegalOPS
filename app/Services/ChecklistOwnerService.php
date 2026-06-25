<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\ChecklistOwnerRepository;

final class ChecklistOwnerService
{
    /** @var array<string, string> */
    private array $defaultItems = [
        'exp_001_008' => 'EXP-001 a EXP-008 verificados',
        'ops_001' => 'OPS-001 procesos programados revisados',
        'ops_002' => 'OPS-002 despliegue, logs y recuperacion revisados',
        'restore_drill' => 'Restauracion de backup verificada en ambiente controlado',
        'decisiones_produccion' => 'Decisiones de produccion aprobadas por propietario',
        'seguridad' => 'Seguridad y permisos revisados',
        'datos' => 'Datos sensibles y exportaciones revisados',
        'venta' => 'Criterio comercial humano documentado',
    ];

    public function __construct(
        private readonly ChecklistOwnerRepository $repository,
        private readonly AuditoriaService $audit,
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
        $checklist = $this->repository->find($id) ?? throw new HttpException(404, 'El checklist no existe.');
        $this->ensureDefaultItems($id);
        $checklist['items'] = $this->repository->items($id);

        return $checklist;
    }

    public function create(?int $firmaId, string $title, Request $request): int
    {
        $title = trim($title) === '' ? 'Checklist owner ' . date('Y-m-d') : mb_substr(trim($title), 0, 180);
        $id = $this->repository->create($firmaId, $title, $this->userId());
        foreach ($this->defaultItems as $code => $itemTitle) {
            $this->repository->addItem($id, $code, $itemTitle);
        }
        $this->audit->record('CHECKLIST_OWNER_CREADO', 'checklist', 'checklist_owner', $id, ['firma_id' => $firmaId], $request, $firmaId);

        return $id;
    }

    /** @param array<string, mixed> $data */
    public function evaluate(int $id, int $itemId, array $data, Request $request): void
    {
        $this->find($id);
        $status = (string) ($data['estado'] ?? '');
        if (!in_array($status, ['aprobado', 'fallido', 'pendiente'], true)) {
            throw new HttpException(422, 'Estado de item no valido.');
        }
        $this->repository->evaluateItem($id, $itemId, $status, $this->nullableString($data['observacion'] ?? null, 2000), $this->nullableString($data['evidencia'] ?? null, 500), $this->userId());
        $this->audit->record('CHECKLIST_OWNER_ITEM_EVALUADO', 'checklist', 'checklist_owner_item', $itemId, ['checklist_id' => $id, 'estado' => $status], $request, null, 'warning');
    }

    /** @param array<string, mixed> $data */
    public function decide(int $id, array $data, Request $request): void
    {
        $checklist = $this->find($id);
        $decision = (string) ($data['decision'] ?? '');
        $observation = trim((string) ($data['observacion'] ?? ''));
        if (!in_array($decision, ['aprobado', 'rechazado'], true) || mb_strlen($observation) < 5) {
            throw new HttpException(422, 'La decision requiere observacion humana.');
        }
        if ($decision === 'aprobado') {
            foreach ($checklist['items'] as $item) {
                if (($item['estado'] ?? '') !== 'aprobado') {
                    throw new HttpException(409, 'No se puede aprobar salida comercial con items pendientes o fallidos.');
                }
            }
            foreach (['restore_drill', 'decisiones_produccion'] as $blockingCode) {
                $found = array_values(array_filter($checklist['items'], static fn (array $item): bool => ($item['codigo'] ?? '') === $blockingCode));
                if ($found === [] || (($found[0]['estado'] ?? '') !== 'aprobado')) {
                    throw new HttpException(409, 'No se puede aprobar salida comercial sin restauracion verificada y decisiones de produccion aprobadas.');
                }
            }
        }
        $this->repository->decide($id, $decision, mb_substr($observation, 0, 4000), $this->userId());
        $this->audit->record('CHECKLIST_OWNER_DECISION', 'checklist', 'checklist_owner', $id, ['decision' => $decision], $request, $checklist['firma_id'] === null ? null : (int) $checklist['firma_id'], 'warning');
    }

    private function ensureDefaultItems(int $id): void
    {
        foreach ($this->defaultItems as $code => $itemTitle) {
            $this->repository->addItem($id, $code, $itemTitle);
        }
    }

    private function nullableString(mixed $value, int $max): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private function userId(): ?int
    {
        $id = $this->auth->id();

        return $id === null ? null : (int) $id;
    }
}
