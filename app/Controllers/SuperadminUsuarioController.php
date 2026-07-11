<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\SuperadminRolService;
use App\Services\SuperadminUsuarioService;

final class SuperadminUsuarioController extends Controller
{
    public function index(Request $request): Response
    {
        $service     = $this->container->get(SuperadminUsuarioService::class);
        $rolService  = $this->container->get(SuperadminRolService::class);
        $currentId   = (int) ($this->currentUser()['id'] ?? 0);

        $usuarios = $service->all();
        $roles    = $rolService->all();

        // Mapa userId => [rolId, ...] para pre-marcar checkboxes en el modal editar
        $userRoleMap = [];
        foreach ($usuarios as $u) {
            $userRoleMap[(int) $u['id']] = $rolService->userRoleIds((int) $u['id']);
        }

        return $this->view('superadmin/mis-usuarios/index', [
            'title'       => 'Usuarios Superadmin',
            'usuarios'    => $usuarios,
            'roles'       => $roles,
            'userRoleMap' => $userRoleMap,
            'stats'       => $service->stats($currentId),
            'csrfToken'   => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ], 'superadmin');
    }

    public function store(Request $request): Response
    {
        $id = $this->container->get(SuperadminUsuarioService::class)
                ->create((array) $request->input(), $request);

        return $this->json(['id' => $id], 'Usuario superadmin creado.', 201);
    }

    public function update(Request $request, string $id): Response
    {
        $currentId = (int) ($this->currentUser()['id'] ?? 0);
        $this->container->get(SuperadminUsuarioService::class)
            ->update($currentId, (int) $id, (array) $request->input(), $request);

        return $this->json(null, 'Usuario actualizado.');
    }

    public function deactivate(Request $request, string $id): Response
    {
        $currentId = (int) ($this->currentUser()['id'] ?? 0);
        $this->container->get(SuperadminUsuarioService::class)
            ->deactivate($currentId, (int) $id, $request);

        return $this->json(null, 'Usuario desactivado.');
    }

    public function reactivate(Request $request, string $id): Response
    {
        $this->container->get(SuperadminUsuarioService::class)
            ->reactivate((int) $id, $request);

        return $this->json(null, 'Usuario reactivado.');
    }

    public function resetPassword(Request $request, string $id): Response
    {
        $tempPass = $this->container->get(SuperadminUsuarioService::class)
            ->resetPassword((int) $id, $request);

        return $this->json(['nueva_password' => $tempPass], 'Contraseña restablecida.');
    }
}
