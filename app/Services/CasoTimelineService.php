<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Permission;
use App\Core\Request;
use App\Repositories\CasoRepository;
use App\Repositories\CasoTimelineRepository;
use App\Validators\CasoTimelineValidator;

final class CasoTimelineService
{
    public function __construct(
        private readonly CasoTimelineRepository $repository,
        private readonly CasoRepository $casos,
        private readonly CasoTimelineValidator $validator,
        private readonly AuditoriaService $audit,
        private readonly Auth $auth,
        private readonly Permission $permissions
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(int $firmaId, int $casoId): array
    {
        $this->ensureCase($firmaId, $casoId);

        return $this->repository->allForCase($firmaId, $casoId);
    }

    /** @return array<string, mixed> */
    public function caseInfo(int $firmaId, int $casoId): array
    {
        return $this->ensureCase($firmaId, $casoId);
    }

    /** @param array<string, mixed> $data */
    public function create(int $firmaId, int $casoId, array $data, Request $request): int
    {
        $this->ensureCase($firmaId, $casoId);
        $normalized = $this->normalize($firmaId, $casoId, $data);
        $this->assertPublishAllowed($normalized['visibilidad']);
        if (!$this->validator->validateData($normalized)) {
            throw new HttpException(422, 'Revise los datos del evento.', $this->validator->errors());
        }
        $id = $this->repository->create($this->recordData($normalized));
        $this->audit->record('TIMELINE_EVENTO_CREADO', 'timeline', 'caso_timeline', $id, [
            'caso_id' => $casoId,
            'tipo_evento' => $normalized['tipo_evento'],
            'visibilidad' => $normalized['visibilidad'],
            'critico' => $normalized['critico'],
        ], $request, $firmaId);

        return $id;
    }

    /** @param array<string, mixed> $data */
    public function update(int $firmaId, int $casoId, int $id, array $data, Request $request): void
    {
        $before = $this->raw($firmaId, $casoId, $id);
        $normalized = $this->normalize($firmaId, $casoId, $data, $before);
        if ($normalized['visibilidad'] !== $before['visibilidad']) {
            $this->assertPublishAllowed($normalized['visibilidad']);
        }
        if (!$this->validator->validateData($normalized)) {
            throw new HttpException(422, 'Revise los datos del evento.', $this->validator->errors());
        }
        $this->repository->update($firmaId, $casoId, $id, $this->recordData($normalized));
        $this->audit->record('TIMELINE_EVENTO_MODIFICADO', 'timeline', 'caso_timeline', $id, [
            'caso_id' => $casoId,
            'anterior' => ['visibilidad' => $before['visibilidad'], 'tipo_evento' => $before['tipo_evento']],
            'nuevo' => ['visibilidad' => $normalized['visibilidad'], 'tipo_evento' => $normalized['tipo_evento']],
        ], $request, $firmaId);
    }

    public function changeVisibility(int $firmaId, int $casoId, int $id, string $visibility, Request $request): void
    {
        $before = $this->raw($firmaId, $casoId, $id);
        $data = ['visibilidad' => $visibility];
        if (!$this->validator->validateVisibility($data)) {
            throw new HttpException(422, 'La visibilidad seleccionada no es valida.', $this->validator->errors());
        }
        $this->assertPublishAllowed($visibility);
        $this->repository->setVisibility($firmaId, $casoId, $id, $visibility);
        $this->audit->record('TIMELINE_VISIBILIDAD_CAMBIADA', 'timeline', 'caso_timeline', $id, [
            'caso_id' => $casoId,
            'anterior' => $before['visibilidad'],
            'nuevo' => $visibility,
        ], $request, $firmaId, 'warning');
    }

    public function delete(int $firmaId, int $casoId, int $id, Request $request): void
    {
        $event = $this->raw($firmaId, $casoId, $id);
        $this->repository->softDelete($firmaId, $casoId, $id);
        $this->audit->record('TIMELINE_EVENTO_ELIMINADO', 'timeline', 'caso_timeline', $id, [
            'caso_id' => $casoId,
            'critico' => (int) $event['critico'],
        ], $request, $firmaId, 'warning');
    }

    /** @return array<string, mixed> */
    private function raw(int $firmaId, int $casoId, int $id): array
    {
        $this->ensureCase($firmaId, $casoId);

        return $this->repository->findForCase($firmaId, $casoId, $id) ?? throw new HttpException(404, 'El evento no existe en el caso.');
    }

    /** @return array<string, mixed> */
    private function ensureCase(int $firmaId, int $casoId): array
    {
        return $this->casos->findForFirma($firmaId, $casoId) ?? throw new HttpException(404, 'El caso no existe en la firma.');
    }

    /** @param array<string, mixed> $data @param array<string, mixed>|null $before @return array<string, mixed> */
    private function normalize(int $firmaId, int $casoId, array $data, ?array $before = null): array
    {
        return [
            'firma_id' => $firmaId,
            'caso_id' => $casoId,
            'autor_usuario_id' => $before['autor_usuario_id'] ?? $this->auth->id(),
            'fecha_evento' => $this->dateValue($data['fecha_evento'] ?? ($before['fecha_evento'] ?? date('Y-m-d'))),
            'tipo_evento' => $this->nullableString($data['tipo_evento'] ?? ($before['tipo_evento'] ?? 'actuacion'), 80) ?? 'actuacion',
            'titulo' => $this->nullableString($data['titulo'] ?? ($before['titulo'] ?? null), 180) ?? '',
            'contenido_publico' => $this->nullableString($data['contenido_publico'] ?? ($before['contenido_publico'] ?? null), 2000),
            'contenido_interno' => $this->nullableString($data['contenido_interno'] ?? ($before['contenido_interno'] ?? null), 4000),
            'visibilidad' => (string) ($data['visibilidad'] ?? ($before['visibilidad'] ?? 'interna')),
            'critico' => $this->truthy($data['critico'] ?? ($before['critico'] ?? 0)) ? 1 : 0,
            'documento_id' => $this->nullableInt($data['documento_id'] ?? ($before['documento_id'] ?? null)),
            'estado' => 'activo',
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function recordData(array $data): array
    {
        return [
            'firma_id' => $data['firma_id'],
            'caso_id' => $data['caso_id'],
            'autor_usuario_id' => $data['autor_usuario_id'],
            'fecha_evento' => $data['fecha_evento'],
            'tipo_evento' => $data['tipo_evento'],
            'titulo' => $data['titulo'],
            'contenido_publico' => $data['contenido_publico'],
            'contenido_interno' => $data['contenido_interno'],
            'visibilidad' => $data['visibilidad'],
            'critico' => $data['critico'],
            'documento_id' => $data['documento_id'],
            'estado' => $data['estado'],
        ];
    }

    private function assertPublishAllowed(string $visibility): void
    {
        if ($visibility === 'publica' && !$this->permissions->allows('timeline.publicar', $this->auth->user())) {
            throw new HttpException(403, 'No tiene permiso para publicar eventos en el portal.');
        }
    }

    private function dateValue(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? date('Y-m-d') : mb_substr($value, 0, 10);
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

    private function truthy(mixed $value): bool
    {
        return in_array($value, [1, '1', true, 'true', 'on', 'si', 'yes'], true);
    }
}
