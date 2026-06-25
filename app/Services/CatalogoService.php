<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\CatalogoRepository;
use App\Validators\CatalogoValidator;

final class CatalogoService
{
    public function __construct(
        private readonly CatalogoRepository $repository,
        private readonly CatalogoValidator $validator,
        private readonly Auth $auth,
        private readonly AuditoriaService $audit
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function all(int $firmaId): array
    {
        return ($this->auth->user()['tipo'] ?? null) === 'superadmin'
            ? $this->repository->allGlobal()
            : $this->repository->visibleForFirma($firmaId);
    }

    /** @param array<string, mixed> $data */
    public function create(int $firmaId, array $data, Request $request): int
    {
        $normalized = $this->normalizeCatalog($data);
        if (!$this->validator->validateCatalog($normalized)) {
            throw new HttpException(422, 'Revise los datos del catálogo.', $this->validator->errors());
        }
        $global = $normalized['alcance'] === 'global';
        if ($global && ($this->auth->user()['tipo'] ?? null) !== 'superadmin') {
            throw new HttpException(403, 'Solo superadministración puede crear catálogos globales.');
        }
        if (!$global && $firmaId <= 0) {
            throw new HttpException(422, 'Un catálogo de firma requiere un contexto de firma activo.');
        }
        $ownerFirma = $global ? null : $firmaId;
        $id = $this->repository->create([
            'firma_id' => $ownerFirma,
            'alcance' => $normalized['alcance'],
            'codigo' => $normalized['codigo'],
            'scope_key' => $global ? 'global:' . $normalized['codigo'] : 'firma:' . $firmaId . ':' . $normalized['codigo'],
            'nombre' => $normalized['nombre'],
        ]);
        $this->audit->record('CATALOGO_CREADO', 'configuracion', 'catalogo', $id, ['codigo' => $normalized['codigo'], 'alcance' => $normalized['alcance']], $request, $ownerFirma);

        return $id;
    }

    /** @return array<string, mixed> */
    public function detail(int $firmaId, int $id): array
    {
        $catalog = $this->repository->findVisible($firmaId, $id) ?? throw new HttpException(404, 'El catálogo no existe.');
        $catalog['items'] = $this->repository->items($id, $firmaId);

        return $catalog;
    }

    /** @param array<string, mixed> $data */
    public function createItem(int $firmaId, int $catalogId, array $data, Request $request): int
    {
        $catalog = $this->detail($firmaId, $catalogId);
        $normalized = [
            'codigo' => strtolower(trim((string) ($data['codigo'] ?? ''))),
            'etiqueta' => trim((string) ($data['etiqueta'] ?? '')),
            'orden' => (int) ($data['orden'] ?? 0),
        ];
        if (!$this->validator->validateItem($normalized)) {
            throw new HttpException(422, 'Revise los datos del ítem.', $this->validator->errors());
        }
        $ownerFirma = $catalog['firma_id'] === null ? null : $firmaId;
        if ($ownerFirma === null && ($this->auth->user()['tipo'] ?? null) !== 'superadmin') {
            throw new HttpException(403, 'La firma no puede modificar un catálogo global.');
        }
        $id = $this->repository->createItem($normalized + ['firma_id' => $ownerFirma, 'catalogo_id' => $catalogId]);
        $this->audit->record('CATALOGO_ITEM_CREADO', 'configuracion', 'catalogo_item', $id, ['catalogo_id' => $catalogId, 'codigo' => $normalized['codigo']], $request, $ownerFirma);

        return $id;
    }

    public function setItemStatus(int $firmaId, int $catalogId, int $itemId, string $status, Request $request): void
    {
        $catalog = $this->detail($firmaId, $catalogId);
        $ownerFirma = $catalog['firma_id'] === null ? null : $firmaId;
        if ($ownerFirma === null && ($this->auth->user()['tipo'] ?? null) !== 'superadmin') {
            throw new HttpException(403, 'La firma no puede modificar un catálogo global.');
        }
        $before = $this->repository->findItem($itemId, $catalogId, $ownerFirma) ?? throw new HttpException(404, 'El ítem no existe.');
        $newStatus = $status === 'activo' ? 'activo' : 'inactivo';
        if (!$this->repository->setItemStatus($itemId, $catalogId, $ownerFirma, $newStatus) && $before['estado'] !== $newStatus) {
            throw new HttpException(404, 'El ítem no existe.');
        }
        $this->audit->record('CATALOGO_ITEM_ESTADO_CAMBIADO', 'configuracion', 'catalogo_item', $itemId, ['anterior' => ['estado' => $before['estado']], 'nuevo' => ['estado' => $newStatus]], $request, $ownerFirma);
    }

    /** @param array<string, mixed> $data */
    public function updateItem(int $firmaId, int $catalogId, int $itemId, array $data, Request $request): void
    {
        $catalog = $this->detail($firmaId, $catalogId);
        $ownerFirma = $catalog['firma_id'] === null ? null : $firmaId;
        if ($ownerFirma === null && ($this->auth->user()['tipo'] ?? null) !== 'superadmin') {
            throw new HttpException(403, 'La firma no puede modificar un catálogo global.');
        }
        $before = $this->repository->findItem($itemId, $catalogId, $ownerFirma) ?? throw new HttpException(404, 'El ítem no existe.');
        $normalized = [
            'codigo' => (string) $before['codigo'],
            'etiqueta' => trim((string) ($data['etiqueta'] ?? '')),
            'orden' => (int) ($data['orden'] ?? 0),
        ];
        if (!$this->validator->validateItem($normalized)) {
            throw new HttpException(422, 'Revise los datos del ítem.', $this->validator->errors());
        }
        $changed = $this->repository->updateItem($itemId, $catalogId, $ownerFirma, ['etiqueta' => $normalized['etiqueta'], 'orden' => $normalized['orden']]);
        if (!$changed && ($before['etiqueta'] !== $normalized['etiqueta'] || (int) $before['orden'] !== $normalized['orden'])) {
            throw new HttpException(404, 'El ítem no existe.');
        }
        $this->audit->record('CATALOGO_ITEM_MODIFICADO', 'configuracion', 'catalogo_item', $itemId, [
            'anterior' => ['etiqueta' => $before['etiqueta'], 'orden' => (int) $before['orden']],
            'nuevo' => ['etiqueta' => $normalized['etiqueta'], 'orden' => $normalized['orden']],
        ], $request, $ownerFirma);
    }

    /** @param array<string, mixed> $data @return array<string, string> */
    private function normalizeCatalog(array $data): array
    {
        $code = strtolower(trim((string) ($data['codigo'] ?? '')));
        $code = preg_replace('/[^a-z0-9_]+/', '_', $code) ?? '';

        return ['codigo' => trim($code, '_'), 'nombre' => trim((string) ($data['nombre'] ?? '')), 'alcance' => (string) ($data['alcance'] ?? 'firma')];
    }
}
