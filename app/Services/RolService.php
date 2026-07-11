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

    /** All roles across all firms with their permission IDs — superadmin-only. @return list<array<string, mixed>> */
    public function allGlobal(): array
    {
        $roles = $this->repository->allGlobal();
        foreach ($roles as &$rol) {
            $rol['permisos_ids'] = $this->repository->permissionIds((int) $rol['firma_id'], (int) $rol['id']);
        }
        unset($rol);

        return $roles;
    }

    /** All permissions (including superadmin-only) — for superadmin view. @return list<array<string, mixed>> */
    public function allPermissions(): array
    {
        return $this->repository->allPermissions();
    }

    /** @param array<string, mixed> $data @param list<int> $permissions */
    public function create(int $firmaId, array $data, array $permissions, Request $request, bool $protected = false): int
    {
        $this->limits->requireCapacity($firmaId, 'roles', $request);
        $data = $this->normalize($data) + ['is_protected' => $protected ? 1 : 0];
        if (!$this->validator->validateData($data)) {
            throw new HttpException(422, 'Revise los datos del rol.', $this->validator->errors());
        }
        if ($this->repository->findByCode($firmaId, $data['codigo']) !== null) {
            throw new HttpException(409, 'Ya existe un rol con ese codigo en la firma.');
        }

        return $this->database->transaction(function () use ($firmaId, $data, $permissions, $request): int {
            $id = $this->repository->create($firmaId, $data);
            $this->repository->syncPermissions($firmaId, $id, $this->repository->assignablePermissionIds(array_map('intval', $permissions)));
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
        $duplicate = $this->repository->findByCode($firmaId, $data['codigo']);
        if ($duplicate !== null && (int) $duplicate['id'] !== $id) {
            throw new HttpException(409, 'Ya existe un rol con ese codigo en la firma.');
        }
        $this->database->transaction(function () use ($firmaId, $id, $data, $permissions, $request): void {
            $this->repository->update($firmaId, $id, $data);
            $assignable = $this->repository->assignablePermissionIds(array_map('intval', $permissions));
            $this->repository->syncPermissions($firmaId, $id, $assignable);
            $this->audit->record('ROL_MODIFICADO', 'roles', 'rol', $id, ['codigo' => $data['codigo'], 'permisos' => $assignable], $request, $firmaId);
        });
    }

    /** @param list<int> $roleIds */
    public function assignToUser(int $firmaId, int $userId, array $roleIds, Request $request): void
    {
        $user = $this->users->findForFirma($firmaId, $userId) ?? throw new HttpException(404, 'El usuario no existe en la firma.');
        if ($user['tipo'] === 'cliente_externo' && $roleIds !== []) {
            throw new HttpException(422, 'Los usuarios externos no pueden recibir roles internos.');
        }
        if (
            ($user['estado'] ?? null) === 'activo'
            && $this->users->hasRole($userId, $firmaId, 'administrador')
            && $this->users->countActiveAdmins($firmaId) <= 1
            && !$this->containsAdminRole($firmaId, array_map('intval', $roleIds))
        ) {
            throw new HttpException(409, 'No se puede dejar la firma sin un administrador activo.');
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

    /**
     * Superadmin-only: sync permissions on any role, ignoring is_protected.
     * @param list<int> $permissions Raw permission IDs from the request
     */
    public function forceSyncPermissions(int $firmaId, int $id, array $permissions, Request $request): void
    {
        $before = $this->find($firmaId, $id);
        $this->database->transaction(function () use ($firmaId, $id, $permissions, $before, $request): void {
            $assignable = $this->repository->assignablePermissionIds(array_map('intval', $permissions));
            $this->repository->syncPermissions($firmaId, $id, $assignable);
            $this->audit->record('ROL_PERMISOS_FORZADOS', 'roles', 'rol', $id, ['codigo' => $before['codigo'], 'permisos' => $assignable], $request, $firmaId);
        });
    }

    /** @param list<int> $roleIds */
    private function containsAdminRole(int $firmaId, array $roleIds): bool
    {
        $adminRole = $this->repository->findByCode($firmaId, 'administrador');
        if ($adminRole === null) {
            return false;
        }

        return in_array((int) $adminRole['id'], array_map('intval', $roleIds), true);
    }
}
