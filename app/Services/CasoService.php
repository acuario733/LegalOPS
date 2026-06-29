<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\CasoRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\UsuarioRepository;
use App\Validators\CasoValidator;

final class CasoService
{
    public function __construct(
        private readonly CasoRepository $repository,
        private readonly ClienteRepository $clientes,
        private readonly UsuarioRepository $usuarios,
        private readonly CasoValidator $validator,
        private readonly LimitePlanService $limits,
        private readonly AuditoriaService $audit,
        private readonly Auth $auth,
        private readonly CatalogoLookupService $catalogs
    ) {
    }

    /** @param array<string, mixed> $filters @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int} */
    public function list(int $firmaId, array $filters, int $page = 1, int $perPage = 25): array
    {
        $result = $this->repository->paginate($firmaId, $this->normalizeFilters($filters), max(1, $page), min(100, max(1, $perPage)));
        $result['page'] = max(1, $page);
        $result['per_page'] = min(100, max(1, $perPage));

        return $result;
    }

    /** @return array<string, mixed> */
    public function find(int $firmaId, int $id): array
    {
        return $this->repository->findForFirma($firmaId, $id) ?? throw new HttpException(404, 'El caso no existe en la firma.');
    }

    /** @param array<string, mixed> $data */
    public function create(int $firmaId, array $data, Request $request): int
    {
        $this->limits->requireCapacity($firmaId, 'casos', $request);
        $normalized = $this->normalize($firmaId, $data);
        $this->validateRelations($firmaId, $normalized);
        $this->ensureRadicadoAvailable($firmaId, $normalized['radicado']);
        if (!$this->validator->validateData($normalized)) {
            throw new HttpException(422, 'Revise los datos del caso.', $this->validator->errors());
        }
        $id = $this->repository->create($this->recordData($normalized));
        $this->audit->record('CASO_CREADO', 'casos', 'caso', $id, [
            'cliente_id' => $normalized['cliente_id'],
            'responsable_usuario_id' => $normalized['responsable_usuario_id'],
            'radicado' => $normalized['radicado'],
        ], $request, $firmaId);

        return $id;
    }

    /** @param array<string, mixed> $data */
    public function update(int $firmaId, int $id, array $data, Request $request): void
    {
        $before = $this->find($firmaId, $id);
        $normalized = $this->normalize($firmaId, $data, $before);
        $this->validateRelations($firmaId, $normalized);
        $this->ensureRadicadoAvailable($firmaId, $normalized['radicado'], $id);
        if (!$this->validator->validateData($normalized)) {
            throw new HttpException(422, 'Revise los datos del caso.', $this->validator->errors());
        }
        $this->repository->update($firmaId, $id, $this->recordData($normalized));
        $this->audit->record('CASO_MODIFICADO', 'casos', 'caso', $id, [
            'anterior' => ['estado' => $before['estado'], 'cliente_id' => $before['cliente_id'], 'responsable_usuario_id' => $before['responsable_usuario_id']],
            'nuevo' => ['estado' => $normalized['estado'], 'cliente_id' => $normalized['cliente_id'], 'responsable_usuario_id' => $normalized['responsable_usuario_id']],
        ], $request, $firmaId);
    }

    public function close(int $firmaId, int $id, string $reason, Request $request): void
    {
        $case = $this->find($firmaId, $id);
        $data = ['motivo' => trim($reason)];
        if (!$this->validator->validateClose($data)) {
            throw new HttpException(422, 'Indique un motivo valido.', $this->validator->errors());
        }
        $this->repository->close($firmaId, $id, (int) $this->auth->id(), mb_substr($data['motivo'], 0, 500));
        $this->audit->record('CASO_CERRADO', 'casos', 'caso', $id, ['estado_anterior' => $case['estado']], $request, $firmaId, 'warning');
    }

    public function archive(int $firmaId, int $id, string $reason, Request $request): void
    {
        $case = $this->find($firmaId, $id);
        $data = ['motivo' => trim($reason)];
        if (!$this->validator->validateClose($data)) {
            throw new HttpException(422, 'Indique un motivo valido.', $this->validator->errors());
        }
        $this->repository->archive($firmaId, $id, (int) $this->auth->id(), mb_substr($data['motivo'], 0, 500));
        $this->audit->record('CASO_ARCHIVADO', 'casos', 'caso', $id, ['estado_anterior' => $case['estado']], $request, $firmaId, 'warning');
    }

    public function reopen(int $firmaId, int $id, string $reason, Request $request): void
    {
        $case = $this->find($firmaId, $id);
        if (!in_array($case['estado'] ?? '', ['cerrado', 'archivado'], true)) {
            throw new HttpException(409, 'Solo se pueden reabrir casos cerrados o archivados.');
        }
        $data = ['motivo' => trim($reason)];
        if (!$this->validator->validateClose($data)) {
            throw new HttpException(422, 'Indique un motivo valido.', $this->validator->errors());
        }
        $this->repository->reopen($firmaId, $id);
        $this->audit->record('CASO_REABIERTO', 'casos', 'caso', $id, [
            'estado_anterior' => $case['estado'],
            'motivo' => mb_substr($data['motivo'], 0, 500),
        ], $request, $firmaId, 'warning');
    }

    /** @param array<string, mixed> $data @param array<string, mixed>|null $before @return array<string, mixed> */
    private function normalize(int $firmaId, array $data, ?array $before = null): array
    {
        $title = trim((string) ($data['titulo'] ?? ($before['titulo'] ?? '')));

        return [
            'firma_id' => $firmaId,
            'cliente_id' => $this->requiredInt($data['cliente_id'] ?? ($before['cliente_id'] ?? null)),
            'responsable_usuario_id' => $this->nullableInt($data['responsable_usuario_id'] ?? ($before['responsable_usuario_id'] ?? null)),
            'titulo' => mb_substr($title, 0, 180),
            'titulo_normalizado' => $this->normalizeText($title, 180),
            'descripcion' => $this->nullableString($data['descripcion'] ?? ($before['descripcion'] ?? null), 2000),
            'estado' => (string) ($data['estado'] ?? ($before['estado'] ?? 'activo')),
            'prioridad' => (string) ($data['prioridad'] ?? ($before['prioridad'] ?? 'media')),
            'tipo_proceso' => $this->catalogs->normalizeOptional($firmaId, 'tipo_caso', $data['tipo_proceso'] ?? ($before['tipo_proceso'] ?? null), 'Tipo de caso'),
            'jurisdiccion' => $this->catalogs->normalizeOptional($firmaId, 'jurisdiccion', $data['jurisdiccion'] ?? ($before['jurisdiccion'] ?? null), 'Jurisdiccion'),
            'despacho' => $this->catalogs->normalizeOptional($firmaId, 'despacho', $data['despacho'] ?? ($before['despacho'] ?? null), 'Despacho'),
            'radicado' => $this->nullableString($data['radicado'] ?? ($before['radicado'] ?? null), 120),
            'fecha_apertura' => $this->nullableString($data['fecha_apertura'] ?? ($before['fecha_apertura'] ?? date('Y-m-d')), 10),
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function recordData(array $data): array
    {
        return [
            'firma_id' => $data['firma_id'],
            'cliente_id' => $data['cliente_id'],
            'responsable_usuario_id' => $data['responsable_usuario_id'],
            'titulo' => $data['titulo'],
            'titulo_normalizado' => $data['titulo_normalizado'],
            'descripcion' => $data['descripcion'],
            'estado' => $data['estado'],
            'prioridad' => $data['prioridad'],
            'tipo_proceso' => $data['tipo_proceso'],
            'jurisdiccion' => $data['jurisdiccion'],
            'despacho' => $data['despacho'],
            'radicado' => $data['radicado'],
            'fecha_apertura' => $data['fecha_apertura'],
        ];
    }

    /** @param array<string, mixed> $data */
    private function validateRelations(int $firmaId, array $data): void
    {
        if ($this->clientes->findForFirma($firmaId, (int) $data['cliente_id']) === null) {
            throw new HttpException(422, 'El cliente seleccionado no pertenece a la firma.');
        }
        if ($data['responsable_usuario_id'] !== null && $this->usuarios->findForFirma($firmaId, (int) $data['responsable_usuario_id']) === null) {
            throw new HttpException(422, 'El responsable no pertenece a la firma.');
        }
    }

    private function ensureRadicadoAvailable(int $firmaId, ?string $radicado, ?int $excludeId = null): void
    {
        if ($radicado !== null && $this->repository->radicadoExists($firmaId, $radicado, $excludeId)) {
            throw new HttpException(409, 'Ya existe un caso activo con ese radicado en la firma.');
        }
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function normalizeFilters(array $filters): array
    {
        return [
            'q' => $this->normalizeText((string) ($filters['q'] ?? ''), 180),
            'estado' => in_array(($filters['estado'] ?? ''), ['activo', 'cerrado', 'archivado'], true) ? $filters['estado'] : '',
            'cliente_id' => $this->nullableInt($filters['cliente_id'] ?? null),
        ];
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

    private function requiredInt(mixed $value): int
    {
        return filter_var($value, FILTER_VALIDATE_INT) === false ? 0 : (int) $value;
    }
}
