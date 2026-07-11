<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Repositories\SuperadminRolRepository;
use App\Services\AuditoriaService;
use App\Core\HttpException;

final class SuperadminRolService
{
    public function __construct(
        private readonly SuperadminRolRepository $repo,
        private readonly AuditoriaService $auditoriaService,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->repo->all();
    }

    /** @return array<string, mixed> */
    public function find(int $id): array
    {
        $rol = $this->repo->find($id);
        if ($rol === null) {
            throw new HttpException(404, 'Rol superadmin no encontrado.');
        }

        return $rol;
    }

    /** @return list<array<string, mixed>> */
    public function superadminPermissions(): array
    {
        return $this->repo->superadminPermissions();
    }

    /** @return list<int> */
    public function permissionIds(int $roleId): array
    {
        return $this->repo->permissionIds($roleId);
    }

    /** @return list<array<string, mixed>> */
    public function usersForRole(int $roleId): array
    {
        return $this->repo->usersForRole($roleId);
    }

    /** @return list<array<string, mixed>> */
    public function allSuperadminUsers(): array
    {
        return $this->repo->allSuperadminUsers();
    }

    /** @return list<int> */
    public function userRoleIds(int $userId): array
    {
        return $this->repo->userRoleIds($userId);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, Request $request): int
    {
        $this->validateData($data);

        $codigo = strtolower(trim($data['codigo']));
        if ($this->repo->codeExists($codigo)) {
            throw new HttpException(422, "El código '{$codigo}' ya está en uso.");
        }

        $id = $this->repo->create([
            'codigo'      => $codigo,
            'nombre'      => trim($data['nombre']),
            'descripcion' => trim($data['descripcion'] ?? ''),
            'estado'      => 'activo',
        ]);

        $this->audit($request, 'SA_ROL_CREADO', "Rol superadmin creado: {$codigo} (id={$id})");

        return $id;
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data, Request $request): void
    {
        $rol = $this->find($id);

        if ((int) $rol['is_protected'] === 1) {
            throw new HttpException(403, 'Este rol está protegido y no puede modificarse.');
        }

        $this->validateData($data, excludeId: $id);

        $codigo = strtolower(trim($data['codigo']));
        if ($this->repo->codeExists($codigo, $id)) {
            throw new HttpException(422, "El código '{$codigo}' ya está en uso.");
        }

        $this->repo->update($id, [
            'nombre'      => trim($data['nombre']),
            'descripcion' => trim($data['descripcion'] ?? ''),
            'estado'      => in_array($data['estado'] ?? 'activo', ['activo', 'inactivo'], true)
                                ? $data['estado']
                                : 'activo',
        ]);

        $this->audit($request, 'SA_ROL_ACTUALIZADO', "Rol superadmin actualizado: id={$id}");
    }

    /** @param list<int> $permissionIds */
    public function syncPermissions(int $roleId, array $permissionIds, Request $request): void
    {
        $this->find($roleId); // 404 guard

        $this->repo->syncPermissions($roleId, $permissionIds);

        $count = count($permissionIds);
        $this->audit($request, 'SA_ROL_PERMISOS_SYNC', "Permisos sincronizados en rol superadmin id={$roleId}: {$count} permisos");
    }

    public function assignToUser(int $userId, int $roleId, Request $request): void
    {
        $this->find($roleId);
        $this->repo->assignToUser($userId, $roleId);
        $this->audit($request, 'SA_ROL_ASIGNADO', "Rol superadmin id={$roleId} asignado a usuario id={$userId}");
    }

    public function removeFromUser(int $userId, int $roleId, Request $request): void
    {
        $this->find($roleId);
        $this->repo->removeFromUser($userId, $roleId);
        $this->audit($request, 'SA_ROL_REMOVIDO', "Rol superadmin id={$roleId} removido de usuario id={$userId}");
    }

    /** @param list<int> $roleIds */
    public function syncUserRoles(int $userId, array $roleIds, Request $request): void
    {
        $this->repo->syncUserRoles($userId, $roleIds);
        $count = count($roleIds);
        $this->audit($request, 'SA_ROL_USUARIO_SYNC', "Roles superadmin sincronizados para usuario id={$userId}: {$count} roles");
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $data */
    private function validateData(array $data, ?int $excludeId = null): void
    {
        if (empty(trim($data['codigo'] ?? ''))) {
            throw new HttpException(422, 'El código del rol es requerido.');
        }
        if (empty(trim($data['nombre'] ?? ''))) {
            throw new HttpException(422, 'El nombre del rol es requerido.');
        }
        if (!preg_match('/^[a-z0-9_]+$/', strtolower(trim($data['codigo'])))) {
            throw new HttpException(422, 'El código solo puede contener letras minúsculas, números y guión bajo.');
        }
    }

    private function audit(Request $request, string $accion, string $detalle): void
    {
        $this->auditoriaService->record(
            action:     $accion,
            module:     'roles',
            entityType: null,
            entityId:   null,
            metadata:   ['detalle' => $detalle],
            request:    $request,
            firmaId:    null,
        );
    }
}
