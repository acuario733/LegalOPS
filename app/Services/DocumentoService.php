<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\CasoRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\DocumentoRepository;
use App\Repositories\DocumentoVersionRepository;
use App\Repositories\GastoRepository;
use App\Validators\DocumentoValidator;

final class DocumentoService
{
    public function __construct(
        private readonly DocumentoRepository $repository,
        private readonly DocumentoVersionRepository $versions,
        private readonly ClienteRepository $clientes,
        private readonly CasoRepository $casos,
        private readonly GastoRepository $gastos,
        private readonly DocumentoValidator $validator,
        private readonly DocumentoVersionService $versionService,
        private readonly LimitePlanService $limits,
        private readonly Database $database,
        private readonly AuditoriaService $audit,
        private readonly Auth $auth
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
        $document = $this->repository->findForFirma($firmaId, $id) ?? throw new HttpException(404, 'El documento no existe en la firma.');
        $document['versiones'] = $this->versions->allForDocument($firmaId, $id);

        return $document;
    }

    /** @param array<string, mixed> $data @param array<string, mixed> $file */
    public function create(int $firmaId, array $data, array $file, Request $request): int
    {
        $this->limits->requireCapacity($firmaId, 'documentos');
        $normalized = $this->validateRelations($firmaId, $this->normalize($firmaId, $data));
        if (!$this->validator->validateData($normalized)) {
            throw new HttpException(422, 'Revise los datos del documento.', $this->validator->errors());
        }

        return $this->database->transaction(function () use ($firmaId, $normalized, $file, $request): int {
            $id = $this->repository->create($this->recordData($normalized));
            $this->versionService->create($firmaId, $id, $file, $request, 'DOCUMENTO_CARGADO');

            return $id;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(int $firmaId, int $id, array $data, Request $request): void
    {
        $before = $this->find($firmaId, $id);
        $normalized = $this->validateRelations($firmaId, $this->normalize($firmaId, $data, $before));
        if (!$this->validator->validateData($normalized)) {
            throw new HttpException(422, 'Revise los datos del documento.', $this->validator->errors());
        }
        $this->repository->update($firmaId, $id, $this->recordData($normalized));
        $this->audit->record('DOCUMENTO_MODIFICADO', 'documentos', 'documento', $id, [
            'anterior' => ['cliente_id' => $before['cliente_id'], 'caso_id' => $before['caso_id'], 'gasto_id' => $before['gasto_id']],
            'nuevo' => ['cliente_id' => $normalized['cliente_id'], 'caso_id' => $normalized['caso_id'], 'gasto_id' => $normalized['gasto_id']],
        ], $request, $firmaId);
    }

    public function delete(int $firmaId, int $id, Request $request): void
    {
        $document = $this->find($firmaId, $id);
        $this->repository->softDelete($firmaId, $id);
        $this->audit->record('DOCUMENTO_ELIMINADO', 'documentos', 'documento', $id, [
            'cliente_id' => $document['cliente_id'],
            'caso_id' => $document['caso_id'],
            'gasto_id' => $document['gasto_id'],
            'version_actual' => $document['version_numero'] ?? null,
        ], $request, $firmaId, 'warning');
    }

    /** @param array<string, mixed> $data @param array<string, mixed>|null $before @return array<string, mixed> */
    private function normalize(int $firmaId, array $data, ?array $before = null): array
    {
        $title = trim((string) ($data['titulo'] ?? ($before['titulo'] ?? '')));

        return [
            'firma_id' => $firmaId,
            'cliente_id' => $this->nullableInt($data['cliente_id'] ?? ($before['cliente_id'] ?? null)),
            'caso_id' => $this->nullableInt($data['caso_id'] ?? ($before['caso_id'] ?? null)),
            'gasto_id' => $this->nullableInt($data['gasto_id'] ?? ($before['gasto_id'] ?? null)),
            'titulo' => mb_substr($title, 0, 180),
            'titulo_normalizado' => $this->normalizeText($title, 180),
            'descripcion' => $this->nullableString($data['descripcion'] ?? ($before['descripcion'] ?? null), 2000),
            'tipo_documental' => $this->nullableString($data['tipo_documental'] ?? ($before['tipo_documental'] ?? null), 80),
            'estado' => 'activo',
            'visible_portal' => $this->truthy($data['visible_portal'] ?? ($before['visible_portal'] ?? 0)) ? 1 : 0,
            'created_by_usuario_id' => $before['created_by_usuario_id'] ?? $this->auth->id(),
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function validateRelations(int $firmaId, array $data): array
    {
        if ($data['cliente_id'] === null && $data['caso_id'] === null && $data['gasto_id'] === null) {
            throw new HttpException(422, 'El documento debe asociarse a cliente, caso o gasto.');
        }
        if ($data['gasto_id'] !== null) {
            $gasto = $this->gastos->findForFirma($firmaId, (int) $data['gasto_id']) ?? throw new HttpException(422, 'El gasto seleccionado no pertenece a la firma.');
            $data['cliente_id'] ??= (int) $gasto['cliente_id'];
            $data['caso_id'] ??= $this->nullableInt($gasto['caso_id'] ?? null);
        }
        if ($data['caso_id'] !== null) {
            $case = $this->casos->findForFirma($firmaId, (int) $data['caso_id']) ?? throw new HttpException(422, 'El caso seleccionado no pertenece a la firma.');
            if ($data['cliente_id'] === null) {
                $data['cliente_id'] = (int) $case['cliente_id'];
            } elseif ((int) $data['cliente_id'] !== (int) $case['cliente_id']) {
                throw new HttpException(422, 'El cliente no coincide con el caso seleccionado.');
            }
        }
        if ($data['cliente_id'] !== null && $this->clientes->findForFirma($firmaId, (int) $data['cliente_id']) === null) {
            throw new HttpException(422, 'El cliente seleccionado no pertenece a la firma.');
        }

        return $data;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function recordData(array $data): array
    {
        return [
            'firma_id' => $data['firma_id'],
            'cliente_id' => $data['cliente_id'],
            'caso_id' => $data['caso_id'],
            'gasto_id' => $data['gasto_id'],
            'titulo' => $data['titulo'],
            'titulo_normalizado' => $data['titulo_normalizado'],
            'descripcion' => $data['descripcion'],
            'tipo_documental' => $data['tipo_documental'],
            'estado' => $data['estado'],
            'visible_portal' => $data['visible_portal'],
            'created_by_usuario_id' => $data['created_by_usuario_id'],
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function normalizeFilters(array $filters): array
    {
        $q = trim((string) ($filters['q'] ?? ''));

        return [
            'q' => $this->normalizeText($q, 180),
            'q_raw' => mb_substr($q, 0, 180),
            'cliente_id' => $this->nullableInt($filters['cliente_id'] ?? null),
            'caso_id' => $this->nullableInt($filters['caso_id'] ?? null),
            'gasto_id' => $this->nullableInt($filters['gasto_id'] ?? null),
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

    private function truthy(mixed $value): bool
    {
        return in_array($value, [1, '1', true, 'true', 'on', 'si', 'yes'], true);
    }
}
