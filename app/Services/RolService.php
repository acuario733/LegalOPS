<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\RolRepository;
use App\Repositories\UsuarioRepository;
use App\Validators\RolValidator;

final class RolService
{
    public function __construct(
        private readonly RolRepository $repository,
        private readonly UsuarioRepository $users,
        private readonly LimitePlanService $limits,
        private readonly RolValidator $validator,
        private readonly Database $database,
        private readonly AuditoriaService $audit
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function all(int $firmaId): array
    {
        return $this->repository->allForFirma($firmaId);
    }

    /** @return array<string, mixed> */
    public function find(int $firmaId, int $id): array
    {
        $role = $this->repository->findForFirma($firmaId, $id) ?? throw new HttpException(404, 'El rol no existe.');
        $role['permisos'] = $this->repository->permissionIds($firmaId, $id);

        return $role;
    }

    /** @return list<array<string, mixed>> */
    public function permissions(): array
    {
        return $this->repository->permissions();
    }

    /** @param array<string, mixed> $data @param list<int> $permissions */
    public function create(int $firmaId, array $data, array $permissions, Request $request, bool $protected = false): int
    {
        $this->limits->requireCapacity($firmaId, 'roles');
        $data = $this->normalize($data) + ['is_protected' => $protected ? 1 : 0];
        if (!$this->validator->validateData($data)) {
            throw new HttpException(422, 'Revise los datos del rol.', $this->validator->errors());
        }

        return $this->database->transaction(function () use ($firmaId, $data, $permissions, $request): int {
            $id = $this->repository->create($firmaId, $data);
            $this->repository->syncPermissions($firmaId, $id, array_map('intval', $permissions));
            $this->audit->record('ROL_CREADO', 'roles', 'rol', $id, ['codigo' => $data['codigo']], $request, $firmaId);

            return $id;
        });
    }

    /** @param array<string, mixed> $data @param list<int> $permissions */
    public function update(int $firmaId, int $id, array $data, array $permissions, Request $request): void
    {
        $before = $this->find($firmaId, $id);
        $data = $this->normalize($data);
        if ((int) $before['is_protected'] === 1) {
            $data['codigo'] = (string) $before['codigo'];
            $data['estado'] = 'activo';
            $permissions = array_map('intval', (array) $before['permisos']);
        }
        if (!$this->validator->validateData($data)) {
            throw new HttpException(422, 'Revise los datos del rol.', $this->validator->errors());
        }
        $this->database->transaction(function () use ($firmaId, $id, $data, $permissions, $request): void {
            $this->repository->update($firmaId, $id, $data);
            $this->repository->syncPermissions($firmaId, $id, array_map('intval', $permissions));
            $this->audit->record('ROL_MODIFICADO', 'roles', 'rol', $id, ['codigo' => $data['codigo'], 'permisos' => array_map('intval', $permissions)], $request, $firmaId);
        });
    }

    /** @param list<int> $roleIds */
    public function assignToUser(int $firmaId, int $userId, array $roleIds, Request $request): void
    {
        $user = $this->users->findForFirma($firmaId, $userId) ?? throw new HttpException(404, 'El usuario no existe en la firma.');
        if ($user['tipo'] === 'cliente_externo' && $roleIds !== []) {
            throw new HttpException(422, 'Los usuarios externos no pueden recibir roles internos.');
        }
        $this->database->transaction(function () use ($firmaId, $userId, $roleIds, $request): void {
            $this->repository->syncUserRoles($firmaId, $userId, array_map('intval', $roleIds));
            $this->audit->record('USUARIO_ROLES_MODIFICADOS', 'roles', 'usuario', $userId, ['roles' => array_map('intval', $roleIds)], $request, $firmaId);
        });
    }

    /** @param array<string, mixed> $data @return array<string, string> */
    private function normalize(array $data): array
    {
        $code = strtolower(trim((string) ($data['codigo'] ?? '')));
        $code = preg_replace('/[^a-z0-9_]+/', '_', $code) ?? '';

        return [
            'codigo' => trim($code, '_'),
            'nombre' => trim((string) ($data['nombre'] ?? '')),
            'descripcion' => trim((string) ($data['descripcion'] ?? '')),
            'estado' => (string) ($data['estado'] ?? 'activo'),
        ];
    }
}
