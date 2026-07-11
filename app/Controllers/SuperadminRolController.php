<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\SuperadminRolService;

final class SuperadminRolController extends Controller
{
    public function index(Request $request): Response
    {
        $service = $this->container->get(SuperadminRolService::class);

        return $this->view('superadmin/mis-roles/index', [
            'title'   => 'Roles Superadmin',
            'roles'   => $service->all(),
            'permisos' => $service->superadminPermissions(),
            'usuarios' => $service->allSuperadminUsers(),
            'csrfToken' => $this->csrf->token(),
        ], 'superadmin');
    }

    public function store(Request $request): Response
    {
        $id = $this->container->get(SuperadminRolService::class)
                ->create((array) $request->input(), $request);

        return $this->json(['id' => $id], 'Rol creado.', 201);
    }

    public function update(Request $request, string $id): Response
    {
        $this->container->get(SuperadminRolService::class)
            ->update((int) $id, (array) $request->input(), $request);

        return $this->json(null, 'Rol actualizado.');
    }

    public function syncPermissions(Request $request, string $id): Response
    {
        $input = (array) $request->input();
        $permIds = array_map('intval', (array) ($input['permisos'] ?? []));

        $this->container->get(SuperadminRolService::class)
            ->syncPermissions((int) $id, $permIds, $request);

        return $this->json(null, 'Permisos actualizados.');
    }

    public function assign(Request $request, string $id): Response
    {
        $input  = (array) $request->input();
        $userId = (int) ($input['usuario_id'] ?? 0);

        $this->container->get(SuperadminRolService::class)
            ->assignToUser($userId, (int) $id, $request);

        return $this->json(null, 'Rol asignado al usuario.');
    }

    public function unassign(Request $request, string $id): Response
    {
        $input  = (array) $request->input();
        $userId = (int) ($input['usuario_id'] ?? 0);

        $this->container->get(SuperadminRolService::class)
            ->removeFromUser($userId, (int) $id, $request);

        return $this->json(null, 'Rol removido del usuario.');
    }

    public function roleUsers(Request $request, string $id): Response
    {
        $data = $this->container->get(SuperadminRolService::class)
                ->usersForRole((int) $id);

        return $this->json($data);
    }

    public function rolePermissions(Request $request, string $id): Response
    {
        $data = $this->container->get(SuperadminRolService::class)
                ->permissionIds((int) $id);

        return $this->json($data);
    }
}
