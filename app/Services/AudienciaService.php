<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\AudienciaRepository;
use App\Repositories\CasoRepository;
use App\Repositories\FirmaRepository;
use App\Repositories\UsuarioRepository;
use App\Validators\AudienciaValidator;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class AudienciaService
{
    public function __construct(
        private readonly AudienciaRepository $repository,
        private readonly CasoRepository $casos,
        private readonly UsuarioRepository $usuarios,
        private readonly FirmaRepository $firmas,
        private readonly AudienciaValidator $validator,
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
        $result['items'] = array_map(fn (array $item): array => $this->withVisualState($firmaId, $item), $result['items']);
        $result['page'] = max(1, $page);
        $result['per_page'] = min(100, max(1, $perPage));

        return $result;
    }

    /** @return array<string, mixed> */
    public function find(int $firmaId, int $id): array
    {
        $hearing = $this->repository->findForFirma($firmaId, $id) ?? throw new HttpException(404, 'La audiencia no existe en la firma.');

        return $this->withVisualState($firmaId, $hearing);
    }

    /** @param array<string, mixed> $data */
    public function create(int $firmaId, array $data, Request $request): int
    {
        $this->limits->requireCapacity($firmaId, 'audiencias', $request);
        $normalized = $this->normalize($firmaId, $data);
        $this->validateRelations($firmaId, $normalized);
        if (!$this->validator->validateData($normalized)) {
            throw new HttpException(422, 'Revise los datos de la audiencia.', $this->validator->errors());
        }
        $id = $this->repository->create($this->recordData($normalized));
        $this->audit->record('AUDIENCIA_CREADA', 'audiencias', 'audiencia', $id, [
            'caso_id' => $normalized['caso_id'],
            'fecha' => $normalized['fecha'],
            'hora' => $normalized['hora'],
            'timezone' => $normalized['timezone'],
        ], $request, $firmaId);

        return $id;
    }

    /** @param array<string, mixed> $data */
    public function update(int $firmaId, int $id, array $data, Request $request): void
    {
        $before = $this->find($firmaId, $id);
        $normalized = $this->normalize($firmaId, $data, $before);
        $this->validateRelations($firmaId, $normalized);
        if (!$this->validator->validateData($normalized)) {
            throw new HttpException(422, 'Revise los datos de la audiencia.', $this->validator->errors());
        }
        $this->repository->update($firmaId, $id, $this->recordData($normalized));
        $this->audit->record('AUDIENCIA_MODIFICADA', 'audiencias', 'audiencia', $id, [
            'anterior' => ['caso_id' => $before['caso_id'], 'fecha' => $before['fecha'], 'hora' => $before['hora'], 'estado' => $before['estado']],
            'nuevo' => ['caso_id' => $normalized['caso_id'], 'fecha' => $normalized['fecha'], 'hora' => $normalized['hora'], 'estado' => $normalized['estado']],
        ], $request, $firmaId);
    }

    public function registerResult(int $firmaId, int $id, string $result, Request $request): void
    {
        $before = $this->find($firmaId, $id);
        $data = ['resultado' => $this->nullableString($result, 4000) ?? ''];
        if (!$this->validator->validateResult($data)) {
            throw new HttpException(422, 'Registre un resultado valido.', $this->validator->errors());
        }
        $this->repository->registerResult($firmaId, $id, $data['resultado'], (int) $this->auth->id());
        $this->audit->record('AUDIENCIA_RESULTADO_REGISTRADO', 'audiencias', 'audiencia', $id, [
            'caso_id' => $before['caso_id'],
            'estado_anterior' => $before['estado'],
            'fecha' => $before['fecha'],
        ], $request, $firmaId, 'warning');
    }

    /** @param array<string, mixed> $data @param array<string, mixed>|null $before @return array<string, mixed> */
    private function normalize(int $firmaId, array $data, ?array $before = null): array
    {
        $timezone = $this->timezone($firmaId)->getName();
        $today = (new DateTimeImmutable('today', new DateTimeZone($timezone)))->format('Y-m-d');

        return [
            'firma_id' => $firmaId,
            'caso_id' => $this->requiredInt($data['caso_id'] ?? ($before['caso_id'] ?? null)),
            'responsable_usuario_id' => $this->nullableInt($data['responsable_usuario_id'] ?? ($before['responsable_usuario_id'] ?? null)),
            'titulo' => $this->nullableString($data['titulo'] ?? ($before['titulo'] ?? null), 180) ?? '',
            'fecha' => $this->dateValue($data['fecha'] ?? ($before['fecha'] ?? $today), $today),
            'hora' => $this->timeValue($data['hora'] ?? ($before['hora'] ?? '08:00')),
            'timezone' => $timezone,
            'modalidad' => (string) ($data['modalidad'] ?? ($before['modalidad'] ?? 'presencial')),
            'despacho' => $this->catalogs->normalizeOptional($firmaId, 'despacho', $data['despacho'] ?? ($before['despacho'] ?? null), 'Despacho'),
            'juez_responsable' => $this->nullableString($data['juez_responsable'] ?? ($before['juez_responsable'] ?? null), 180),
            'despacho_contacto' => $this->nullableString($data['despacho_contacto'] ?? ($before['despacho_contacto'] ?? null), 255),
            'lugar' => $this->nullableString($data['lugar'] ?? ($before['lugar'] ?? null), 255),
            'enlace' => $this->nullableString($data['enlace'] ?? ($before['enlace'] ?? null), 500),
            'estado' => (string) ($data['estado'] ?? ($before['estado'] ?? 'programada')),
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function recordData(array $data): array
    {
        return [
            'firma_id' => $data['firma_id'],
            'caso_id' => $data['caso_id'],
            'responsable_usuario_id' => $data['responsable_usuario_id'],
            'titulo' => $data['titulo'],
            'fecha' => $data['fecha'],
            'hora' => $data['hora'],
            'timezone' => $data['timezone'],
            'modalidad' => $data['modalidad'],
            'despacho' => $data['despacho'],
            'juez_responsable' => $data['juez_responsable'],
            'despacho_contacto' => $data['despacho_contacto'],
            'lugar' => $data['lugar'],
            'enlace' => $data['enlace'],
            'estado' => $data['estado'],
        ];
    }

    /** @param array<string, mixed> $data */
    private function validateRelations(int $firmaId, array $data): void
    {
        if ($this->casos->findForFirma($firmaId, (int) $data['caso_id']) === null) {
            throw new HttpException(422, 'El caso seleccionado no pertenece a la firma.');
        }
        if ($data['responsable_usuario_id'] !== null && $this->usuarios->findForFirma($firmaId, (int) $data['responsable_usuario_id']) === null) {
            throw new HttpException(422, 'El responsable no pertenece a la firma.');
        }
    }

    /** @param array<string, mixed> $hearing @return array<string, mixed> */
    private function withVisualState(int $firmaId, array $hearing): array
    {
        if (($hearing['estado'] ?? '') !== 'programada') {
            $hearing['estado_visual'] = (string) $hearing['estado'];

            return $hearing;
        }
        $today = new DateTimeImmutable('today', $this->timezone($firmaId));
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $hearing['fecha'], $this->timezone($firmaId));
        $hearing['estado_visual'] = $date !== false && $date < $today ? 'pendiente_resultado' : 'programada';

        return $hearing;
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function normalizeFilters(array $filters): array
    {
        return [
            'q' => mb_substr(trim((string) ($filters['q'] ?? '')), 0, 180),
            'estado' => in_array(($filters['estado'] ?? ''), ['programada', 'realizada', 'cancelada'], true) ? $filters['estado'] : '',
            'caso_id' => $this->nullableInt($filters['caso_id'] ?? null),
            'desde' => $this->dateFilter($filters['desde'] ?? null),
            'hasta' => $this->dateFilter($filters['hasta'] ?? null),
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

    private function dateFilter(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : '';
    }

    private function timeValue(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? '08:00' : mb_substr($value, 0, 5);
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
