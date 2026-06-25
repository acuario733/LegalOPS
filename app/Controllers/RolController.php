<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\RolService;

final class RolController extends Controller
{
    public function index(Request $request): Response
    {
        $firmaId = $this->firmaId();

        return $this->view('roles/index', [
            'title' => 'Roles y permisos',
            'roles' => $this->container->get(RolService::class)->all($firmaId),
            'permisos' => $this->container->get(RolService::class)->permissions(),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function store(Request $request): Response
    {
        $permissions = $request->input('permisos', []);
        $id = $this->container->get(RolService::class)->create($this->firmaId(), (array) $request->input(), is_array($permissions) ? $permissions : [], $request);

        return $this->json(['id' => $id], 'Rol creado correctamente.', 201);
    }

    public function update(Request $request, string $id): Response
    {
        $permissions = $request->input('permisos', []);
        $this->container->get(RolService::class)->update($this->firmaId(), (int) $id, (array) $request->input(), is_array($permissions) ? $permissions : [], $request);

        return $this->json(null, 'Rol actualizado correctamente.');
    }

    public function assign(Request $request, string $userId): Response
    {
        $roles = $request->input('roles', []);
        $this->container->get(RolService::class)->assignToUser($this->firmaId(), (int) $userId, is_array($roles) ? $roles : [], $request);

        return $this->json(null, 'Roles asignados correctamente.');
    }

    private function firmaId(): int
    {
        return $this->currentFirma() === null ? throw new HttpException(403, 'La operación requiere una firma activa.') : (int) $this->currentFirma();
    }
}

