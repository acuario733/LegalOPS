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
use App\Validators\TerminoValidator;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class TerminoService
{
    public function __construct(
        private readonly TerminoRepository $repository,
        private readonly CasoRepository $casos,
        private readonly TareaRepository $tareas,
        private readonly UsuarioRepository $usuarios,
        private readonly FirmaRepository $firmas,
        private readonly TerminoValidator $validator,
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

    /** @return list<array<string, mixed>> */
    public function allForSelect(int $firmaId): array
    {
        return array_map(fn (array $item): array => $this->withVisualState($firmaId, $item), $this->repository->allForSelect($firmaId));
    }

    /** @return array<string, mixed> */
    public function find(int $firmaId, int $id): array
    {
        $term = $this->repository->findForFirma($firmaId, $id) ?? throw new HttpException(404, 'El termino no existe en la firma.');

        return $this->withVisualState($firmaId, $term);
    }

    /** @param array<string, mixed> $data */
    public function create(int $firmaId, array $data, Request $request): int
    {
        $this->limits->requireCapacity($firmaId, 'terminos');
        $normalized = $this->validateRelations($firmaId, $this->normalize($firmaId, $data));
        if (!$this->validator->validateData($normalized)) {
            throw new HttpException(422, 'Revise los datos del termino.', $this->validator->errors());
        }

        return $this->database->transaction(function () use ($firmaId, $normalized, $request): int {
            $id = $this->repository->create($this->recordData($normalized));
            $this->syncTask($firmaId, $id, null, $normalized['tarea_id']);
            $this->audit->record('TERMINO_CREADO', 'terminos', 'termino', $id, [
                'caso_id' => $normalized['caso_id'],
                'tarea_id' => $normalized['tarea_id'],
                'fecha_vencimiento' => $normalized['fecha_vencimiento'],
                'responsable_usuario_id' => $normalized['responsable_usuario_id'],
            ], $request, $firmaId);

            return $id;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(int $firmaId, int $id, array $data, Request $request): void
    {
        $before = $this->find($firmaId, $id);
        $normalized = $this->validateRelations($firmaId, $this->normalize($firmaId, $data, $before), $id);
        if (!$this->validator->validateData($normalized)) {
            throw new HttpException(422, 'Revise los datos del termino.', $this->validator->errors());
        }

        $this->database->transaction(function () use ($firmaId, $id, $before, $normalized, $request): void {
            $this->repository->update($firmaId, $id, $this->recordData($normalized));
            $this->syncTask($firmaId, $id, $this->nullableInt($before['tarea_id'] ?? null), $normalized['tarea_id']);
            $critical = $before['fecha_vencimiento'] !== $normalized['fecha_vencimiento']
                || (int) ($before['responsable_usuario_id'] ?? 0) !== (int) ($normalized['responsable_usuario_id'] ?? 0)
                || (int) ($before['caso_id'] ?? 0) !== (int) ($normalized['caso_id'] ?? 0)
                || (int) ($before['tarea_id'] ?? 0) !== (int) ($normalized['tarea_id'] ?? 0);
            $this->audit->record('TERMINO_MODIFICADO', 'terminos', 'termino', $id, [
                'anterior' => [
                    'fecha_vencimiento' => $before['fecha_vencimiento'],
                    'responsable_usuario_id' => $before['responsable_usuario_id'] ?? null,
                    'caso_id' => $before['caso_id'] ?? null,
                    'tarea_id' => $before['tarea_id'] ?? null,
                ],
                'nuevo' => [
                    'fecha_vencimiento' => $normalized['fecha_vencimiento'],
                    'responsable_usuario_id' => $normalized['responsable_usuario_id'],
                    'caso_id' => $normalized['caso_id'],
                    'tarea_id' => $normalized['tarea_id'],
                ],
            ], $request, $firmaId, $critical ? 'warning' : 'info');
        });
    }

    public function complete(int $firmaId, int $id, string $observation, Request $request): void
    {
        $term = $this->find($firmaId, $id);
        $data = ['observacion' => $this->nullableString($observation, 1000)];
        if (!$this->validator->validateComplete($data)) {
            throw new HttpException(422, 'Revise la observacion de cumplimiento.', $this->validator->errors());
        }
        if ($term['estado'] === 'cumplido') {
            return;
        }

        $userId = (int) $this->auth->id();
        $this->repository->markFulfilled($firmaId, $id, $userId, $data['observacion']);
        $this->audit->record('TERMINO_CUMPLIDO', 'terminos', 'termino', $id, [
            'fecha_cumplimiento' => (new DateTimeImmutable('now', $this->timezone($firmaId)))->format('Y-m-d H:i:s'),
            'usuario_id' => $userId,
            'fecha_vencimiento' => $term['fecha_vencimiento'],
        ], $request, $firmaId, 'warning');
    }

    public function delete(int $firmaId, int $id, Request $request): void
    {
        $term = $this->find($firmaId, $id);
        $this->repository->softDelete($firmaId, $id);
        $this->audit->record('TERMINO_ELIMINADO', 'terminos', 'termino', $id, [
            'estado' => $term['estado'],
            'estado_visual' => $term['estado_visual'],
            'fecha_vencimiento' => $term['fecha_vencimiento'],
        ], $request, $firmaId, 'warning');
    }

    /** @param array<string, mixed> $data @param array<string, mixed>|null $before @return array<string, mixed> */
    private function normalize(int $firmaId, array $data, ?array $before = null): array
    {
        $timezone = $this->timezone($firmaId)->getName();
        $today = (new DateTimeImmutable('today', new DateTimeZone($timezone)))->format('Y-m-d');
        $title = trim((string) ($data['titulo'] ?? ($before['titulo'] ?? '')));
        $estado = (string) ($data['estado'] ?? ($before['estado'] ?? 'vigente'));
        if (($before['estado'] ?? null) !== 'cumplido' && $estado === 'cumplido') {
            $estado = (string) ($before['estado'] ?? 'vigente');
        }

        return [
            'firma_id' => $firmaId,
            'caso_id' => $this->nullableInt($data['caso_id'] ?? ($before['caso_id'] ?? null)),
            'tarea_id' => $this->nullableInt($data['tarea_id'] ?? ($before['tarea_id'] ?? null)),
            'responsable_usuario_id' => $this->nullableInt($data['responsable_usuario_id'] ?? ($before['responsable_usuario_id'] ?? null)),
            'titulo' => mb_substr($title, 0, 180),
            'titulo_normalizado' => $this->normalizeText($title, 180),
            'descripcion' => $this->nullableString($data['descripcion'] ?? ($before['descripcion'] ?? null), 2000),
            'fecha_inicio' => $this->dateValue($data['fecha_inicio'] ?? ($before['fecha_inicio'] ?? $today), $today),
            'fecha_vencimiento' => $this->dateValue($data['fecha_vencimiento'] ?? ($before['fecha_vencimiento'] ?? $today), $today),
            'timezone' => $timezone,
            'prioridad' => (string) ($data['prioridad'] ?? ($before['prioridad'] ?? 'media')),
            'estado' => $estado === '' ? 'vigente' : $estado,
            'alerta_dias' => min(90, max(0, $this->intValue($data['alerta_dias'] ?? ($before['alerta_dias'] ?? 3)))),
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function recordData(array $data): array
    {
        return [
            'firma_id' => $data['firma_id'],
            'caso_id' => $data['caso_id'],
            'tarea_id' => $data['tarea_id'],
            'responsable_usuario_id' => $data['responsable_usuario_id'],
            'titulo' => $data['titulo'],
            'titulo_normalizado' => $data['titulo_normalizado'],
            'descripcion' => $data['descripcion'],
            'fecha_inicio' => $data['fecha_inicio'],
            'fecha_vencimiento' => $data['fecha_vencimiento'],
            'timezone' => $data['timezone'],
            'prioridad' => $data['prioridad'],
            'estado' => $data['estado'],
            'alerta_dias' => $data['alerta_dias'],
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function validateRelations(int $firmaId, array $data, ?int $termId = null): array
    {
        $caseId = $data['caso_id'];
        if ($data['tarea_id'] !== null) {
            $task = $this->tareas->findForFirma($firmaId, (int) $data['tarea_id']) ?? throw new HttpException(422, 'La tarea seleccionada no pertenece a la firma.');
            if (($task['termino_id'] ?? null) !== null && (int) $task['termino_id'] !== (int) ($termId ?? 0)) {
                throw new HttpException(409, 'La tarea ya esta asociada a otro termino.');
            }
            if ($caseId === null && ($task['caso_id'] ?? null) !== null) {
                $data['caso_id'] = (int) $task['caso_id'];
                $caseId = $data['caso_id'];
            }
            if ($caseId !== null && ($task['caso_id'] ?? null) !== null && (int) $task['caso_id'] !== (int) $caseId) {
                throw new HttpException(422, 'La tarea y el caso no pertenecen al mismo expediente.');
            }
        }
        if ($caseId === null && $data['tarea_id'] === null) {
            throw new HttpException(422, 'El termino debe asociarse a un caso o una tarea.', ['caso_id' => ['Seleccione un caso o una tarea.']]);
        }
        if ($caseId !== null && $this->casos->findForFirma($firmaId, (int) $caseId) === null) {
            throw new HttpException(422, 'El caso seleccionado no pertenece a la firma.');
        }
        if ($data['responsable_usuario_id'] !== null && $this->usuarios->findForFirma($firmaId, (int) $data['responsable_usuario_id']) === null) {
            throw new HttpException(422, 'El responsable no pertenece a la firma.');
        }

        return $data;
    }

    private function syncTask(int $firmaId, int $termId, ?int $oldTaskId, ?int $newTaskId): void
    {
        if ($oldTaskId !== null && $oldTaskId !== $newTaskId) {
            $this->tareas->clearTerminoIfMatches($firmaId, $oldTaskId, $termId);
        }
        if ($newTaskId !== null) {
            $this->tareas->setTermino($firmaId, $newTaskId, $termId);
        }
    }

    /** @param array<string, mixed> $term @return array<string, mixed> */
    private function withVisualState(int $firmaId, array $term): array
    {
        $term['dias_para_vencer'] = null;
        $term['vencido'] = false;
        if (($term['estado'] ?? '') === 'cumplido') {
            $term['estado_visual'] = 'cumplido';

            return $term;
        }

        $today = new DateTimeImmutable('today', $this->timezone($firmaId));
        $due = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $term['fecha_vencimiento'], $this->timezone($firmaId));
        if ($due === false) {
            $term['estado_visual'] = (string) ($term['estado'] ?? 'vigente');

            return $term;
        }

        $days = (int) $today->diff($due)->format('%r%a');
        $term['dias_para_vencer'] = $days;
        $term['vencido'] = $days < 0;
        $alertDays = max(0, (int) ($term['alerta_dias'] ?? 3));
        $term['estado_visual'] = match (true) {
            $days < 0 => 'vencido',
            $days <= $alertDays => 'critico',
            $days <= 7 => 'proximo',
            default => 'vigente',
        };

        return $term;
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function normalizeFilters(array $filters): array
    {
        $q = trim((string) ($filters['q'] ?? ''));

        return [
            'q' => $this->normalizeText($q, 180),
            'q_raw' => mb_substr($q, 0, 180),
            'estado' => in_array(($filters['estado'] ?? ''), ['vigente', 'proximo', 'critico', 'vencido', 'cumplido'], true) ? $filters['estado'] : '',
            'caso_id' => $this->nullableInt($filters['caso_id'] ?? null),
            'tarea_id' => $this->nullableInt($filters['tarea_id'] ?? null),
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

    private function dateValue(mixed $value, string $default): string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? $default : mb_substr($value, 0, 10);
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

    private function intValue(mixed $value): int
    {
        return filter_var($value, FILTER_VALIDATE_INT) === false ? 0 : (int) $value;
    }
}
