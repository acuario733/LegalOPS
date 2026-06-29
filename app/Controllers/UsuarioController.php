<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\RolService;
use App\Services\UsuarioService;

final class UsuarioController extends Controller
{
    public function index(Request $request): Response
    {
        $firmaId = $this->firmaId();

        return $this->view('usuarios/index', [
            'title' => 'Usuarios',
            'usuarios' => $this->container->get(UsuarioService::class)->all($firmaId),
            'roles' => $this->container->get(RolService::class)->all($firmaId),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
            'canVerifyProfessional' => $this->permissions->allows('usuarios.verificar_profesional', (array) $this->currentUser()),
        ]);
    }

    public function store(Request $request): Response
    {
        $firmaId = $this->firmaId();
        $roles = $request->input('roles', []);
        $id = $this->container->get(UsuarioService::class)->create($firmaId, (array) $request->input(), is_array($roles) ? $roles : [], $request);

        return $this->json(['id' => $id], 'Usuario creado correctamente.', 201);
    }

    public function storeForFirma(Request $request, string $firmaId): Response
    {
        $id = $this->container->get(UsuarioService::class)->createFirstAdmin((int) $firmaId, (array) $request->input(), $request);

        return $this->json(['id' => $id], 'Administrador inicial creado correctamente.', 201);
    }

    public function update(Request $request, string $id): Response
    {
        $roles = $request->input('roles', []);
        $this->container->get(UsuarioService::class)->update($this->firmaId(), (int) $id, (array) $request->input(), is_array($roles) ? $roles : [], $request);

        return $this->json(null, 'Usuario actualizado correctamente.');
    }

    public function deactivate(Request $request, string $id): Response
    {
        $this->container->get(UsuarioService::class)->deactivate($this->firmaId(), (int) $id, $request);

        return $this->json(null, 'Usuario desactivado.');
    }

    public function reactivate(Request $request, string $id): Response
    {
        $this->container->get(UsuarioService::class)->reactivate($this->firmaId(), (int) $id, $request);

        return $this->json(null, 'Usuario reactivado.');
    }

    public function verifyProfessionalCard(Request $request, string $id): Response
    {
        $this->container->get(UsuarioService::class)->verifyProfessionalCard($this->firmaId(), (int) $id, (array) $request->input(), $request);

        return $this->json(null, 'Verificación de tarjeta profesional registrada.');
    }

    private function firmaId(): int
    {
        $firmaId = $this->currentFirma();
        if ($firmaId === null) {
            throw new HttpException(403, 'La operación requiere una firma activa.');
        }

        return (int) $firmaId;
    }
}
