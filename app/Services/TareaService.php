<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\CasoRepository;
use App\Repositories\FirmaRepository;
use App\Repositories\TareaRepository;
use App\Repositories\TerminoRepository;
use App\Repositories\UsuarioRepository;
use App\Validators\TareaValidator;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class TareaService
{
    public function __construct(
        private readonly TareaRepository $repository,
        private readonly CasoRepository $casos,
        private readonly TerminoRepository $terminos,
        private readonly UsuarioRepository $usuarios,
        private readonly FirmaRepository $firmas,
        private readonly TareaValidator $validator,
        private readonly LimitePlanService $limits,
        private readonly AuditoriaService $audit,
        private readonly Database $database,
        private readonly Auth $auth
    ) {
    }

    /** @param array<string, mixed> $filters @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int} */
    public function list(int $firmaId, array $filters, int $page = 1, int $perPage = 25): array
    {
        $result = $this->repository->paginate($firmaId, $this->normalizeFilters($filters), max(1, $page), min(100, max(1, $perPage)));
        $result['items'] = array_map(fn (array $item): array => $this->withVisualState($firmaId, $item), $result['items']);
        $result['page'] = max(1, $page);
        $result['per_page'] = min(100, max(1, $perPage));

        return $result;
    }

    /** @return array<string, mixed> */
    public function find(int $firmaId, int $id): array
    {
        $task = $this->repository->findForFirma($firmaId, $id) ?? throw new HttpException(404, 'La tarea no existe en la firma.');

        return $this->withVisualState($firmaId, $task);
    }

    /** @param array<string, mixed> $data */
    public function create(int $firmaId, array $data, Request $request): int
    {
        $this->limits->requireCapacity($firmaId, 'tareas', $request);
        $normalized = $this->validateRelations($firmaId, $this->normalize($data, null, true));
        if (!$this->validator->validateData($normalized)) {
            throw new HttpException(422, 'Revise los datos de la tarea.', $this->validator->errors());
        }

        return $this->database->transaction(function () use ($firmaId, $normalized, $request): int {
            $id = $this->repository->create($this->recordData($firmaId, $normalized));
            $this->syncTerm($firmaId, $id, null, $normalized['termino_id']);
            $this->audit->record('TAREA_CREADA', 'tareas', 'tarea', $id, [
                'caso_id' => $normalized['caso_id'],
                'termino_id' => $normalized['termino_id'],
                'responsable_usuario_id' => $normalized['responsable_usuario_id'],
                'fecha_vencimiento' => $normalized['fecha_vencimiento'],
            ], $request, $firmaId);

            return $id;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(int $firmaId, int $id, array $data, Request $request): void
    {
        $before = $this->find($firmaId, $id);
        $normalized = $this->validateRelations($firmaId, $this->normalize($data, $before, false), $id);
        if (!$this->validator->validateData($normalized)) {
            throw new HttpException(422, 'Revise los datos de la tarea.', $this->validator->errors());
        }

        $this->database->transaction(function () use ($firmaId, $id, $before, $normalized, $request): void {
            $this->repository->update($firmaId, $id, $this->recordData($firmaId, $normalized));
            $this->syncTerm($firmaId, $id, $this->nullableInt($before['termino_id'] ?? null), $normalized['termino_id']);
            $this->audit->record('TAREA_MODIFICADA', 'tareas', 'tarea', $id, [
                'anterior' => ['caso_id' => $before['caso_id'] ?? null, 'termino_id' => $before['termino_id'] ?? null, 'fecha_vencimiento' => $before['fecha_vencimiento'] ?? null],
                'nuevo' => ['caso_id' => $normalized['caso_id'], 'termino_id' => $normalized['termino_id'], 'fecha_vencimiento' => $normalized['fecha_vencimiento']],
            ], $request, $firmaId);
        });
    }

    public function reassign(int $firmaId, int $id, int $userId, Request $request): void
    {
        $task = $this->find($firmaId, $id);
        $data = ['responsable_usuario_id' => $userId];
        if (!$this->validator->validateReassign($data)) {
            throw new HttpException(422, 'Seleccione un responsable valido.', $this->validator->errors());
        }
        if ($this->usuarios->findForFirma($firmaId, $userId) === null) {
            throw new HttpException(422, 'El responsable no pertenece a la firma.');
        }
        $from = $this->nullableInt($task['responsable_usuario_id'] ?? null);
        if ($from === $userId) {
            return;
        }

        $this->repository->reassign($firmaId, $id, $from, $userId);
        $this->audit->record('TAREA_REASIGNADA', 'tareas', 'tarea', $id, [
            'responsable_anterior' => $from,
            'responsable_nuevo' => $userId,
        ], $request, $firmaId, 'warning');
    }

    public function changeStatus(int $firmaId, int $id, string $status, Request $request): void
    {
        $task = $this->find($firmaId, $id);
        $data = ['estado' => $status];
        if (!$this->validator->validateStatus($data)) {
            throw new HttpException(422, 'Seleccione un estado valido.', $this->validator->errors());
        }
        if ($task['estado'] === $status) {
            return;
        }

        $this->repository->changeStatus($firmaId, $id, $status, (int) $this->auth->id());
        $this->audit->record('TAREA_ESTADO_CAMBIADO', 'tareas', 'tarea', $id, [
            'estado_anterior' => $task['estado'],
            'estado_nuevo' => $status,
        ], $request, $firmaId, $status === 'completada' ? 'warning' : 'info');
    }

    /** @param array<string, mixed> $data @param array<string, mixed>|null $before @return array<string, mixed> */
    private function normalize(array $data, ?array $before = null, bool $allowAssignment = false): array
    {
        $title = trim((string) ($data['titulo'] ?? ($before['titulo'] ?? '')));

        return [
            'caso_id' => $this->nullableInt($data['caso_id'] ?? ($before['caso_id'] ?? null)),
            'termino_id' => $this->nullableInt($data['termino_id'] ?? ($before['termino_id'] ?? null)),
            'responsable_usuario_id' => $allowAssignment
                ? $this->nullableInt($data['responsable_usuario_id'] ?? null)
                : $this->nullableInt($before['responsable_usuario_id'] ?? null),
            'titulo' => mb_substr($title, 0, 180),
            'titulo_normalizado' => $this->normalizeText($title, 180),
            'descripcion' => $this->nullableString($data['descripcion'] ?? ($before['descripcion'] ?? null), 2000),
            'prioridad' => (string) ($data['prioridad'] ?? ($before['prioridad'] ?? 'media')),
            'estado' => (string) ($before['estado'] ?? 'pendiente'),
            'fecha_vencimiento' => $this->nullableDate($data['fecha_vencimiento'] ?? ($before['fecha_vencimiento'] ?? null)),
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function recordData(int $firmaId, array $data): array
    {
        return [
            'firma_id' => $firmaId,
            'caso_id' => $data['caso_id'],
            'termino_id' => $data['termino_id'],
            'responsable_usuario_id' => $data['responsable_usuario_id'],
            'titulo' => $data['titulo'],
            'titulo_normalizado' => $data['titulo_normalizado'],
            'descripcion' => $data['descripcion'],
            'prioridad' => $data['prioridad'],
            'estado' => $data['estado'],
            'fecha_vencimiento' => $data['fecha_vencimiento'],
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function validateRelations(int $firmaId, array $data, ?int $taskId = null): array
    {
        $this->validateDueDate($firmaId, $data['fecha_vencimiento']);
        $caseId = $data['caso_id'];
        if ($data['termino_id'] !== null) {
            $term = $this->terminos->findForFirma($firmaId, (int) $data['termino_id']) ?? throw new HttpException(422, 'El termino seleccionado no pertenece a la firma.');
            if (($term['tarea_id'] ?? null) !== null && (int) $term['tarea_id'] !== (int) ($taskId ?? 0)) {
                throw new HttpException(409, 'El termino ya esta asociado a otra tarea.');
            }
            if ($caseId === null && ($term['caso_id'] ?? null) !== null) {
                $data['caso_id'] = (int) $term['caso_id'];
                $caseId = $data['caso_id'];
            }
            if ($caseId !== null && ($term['caso_id'] ?? null) !== null && (int) $term['caso_id'] !== (int) $caseId) {
                throw new HttpException(422, 'La tarea y el termino no pertenecen al mismo expediente.');
            }
        }
        if ($caseId !== null && $this->casos->findForFirma($firmaId, (int) $caseId) === null) {
            throw new HttpException(422, 'El caso seleccionado no pertenece a la firma.');
        }
        if ($data['responsable_usuario_id'] !== null && $this->usuarios->findForFirma($firmaId, (int) $data['responsable_usuario_id']) === null) {
            throw new HttpException(422, 'El responsable no pertenece a la firma.');
        }

        return $data;
    }

    private function validateDueDate(int $firmaId, ?string $date): void
    {
        if ($date === null || $date === '') {
            return;
        }
        $today = new DateTimeImmutable('today', $this->timezone($firmaId));
        $due = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $this->timezone($firmaId));
        if ($due !== false && $due < $today) {
            throw new HttpException(422, 'La fecha de vencimiento no puede ser anterior a la fecha actual.');
        }
    }

    private function syncTerm(int $firmaId, int $taskId, ?int $oldTermId, ?int $newTermId): void
    {
        if ($oldTermId !== null && $oldTermId !== $newTermId) {
            $this->terminos->clearTaskIfMatches($firmaId, $oldTermId, $taskId);
        }
        if ($newTermId !== null) {
            $this->terminos->setTask($firmaId, $newTermId, $taskId);
        }
    }

    /** @param array<string, mixed> $task @return array<string, mixed> */
    private function withVisualState(int $firmaId, array $task): array
    {
        $task['dias_para_vencer'] = null;
        $task['vencida'] = false;
        if (in_array(($task['estado'] ?? ''), ['completada', 'cancelada'], true) || ($task['fecha_vencimiento'] ?? null) === null) {
            $task['estado_visual'] = (string) ($task['estado'] ?? 'pendiente');

            return $task;
        }

        $today = new DateTimeImmutable('today', $this->timezone($firmaId));
        $due = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $task['fecha_vencimiento'], $this->timezone($firmaId));
        if ($due === false) {
            $task['estado_visual'] = (string) ($task['estado'] ?? 'pendiente');

            return $task;
        }
        $days = (int) $today->diff($due)->format('%r%a');
        $task['dias_para_vencer'] = $days;
        $task['vencida'] = $days < 0;
        $task['estado_visual'] = $days < 0 ? 'vencida' : (string) $task['estado'];

        return $task;
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function normalizeFilters(array $filters): array
    {
        $q = trim((string) ($filters['q'] ?? ''));

        return [
            'q' => $this->normalizeText($q, 180),
            'q_raw' => mb_substr($q, 0, 180),
            'estado' => in_array(($filters['estado'] ?? ''), ['pendiente', 'en_proceso', 'completada', 'vencida', 'cancelada'], true) ? $filters['estado'] : '',
            'caso_id' => $this->nullableInt($filters['caso_id'] ?? null),
            'termino_id' => $this->nullableInt($filters['termino_id'] ?? null),
            'responsable_usuario_id' => $this->nullableInt($filters['responsable_usuario_id'] ?? null),
        ];
    }

    private function timezone(int $firmaId): DateTimeZone
    {
        $timezone = (string) (($this->firmas->find($firmaId)['timezone'] ?? '') ?: 'America/Bogota');
        try {
            return new DateTimeZone($timezone);
        } catch (Throwable) {
            return new DateTimeZone('America/Bogota');
        }
    }

    private function nullableDate(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : mb_substr($value, 0, 10);
    }

    private function normalizeText(string $value, int $max): string
    {
        $value = preg_replace('/\s+/', ' ', mb_strtolower(trim($value))) ?? '';

        return mb_substr($value, 0, $max);
    }

    private function nullableString(mixed $value, int $max): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private function nullableInt(mixed $value): ?int
    {
        return filter_var($value, FILTER_VALIDATE_INT) === false ? null : (int) $value;
    }
}
