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

    /** Sesion 7 (RF-014): ficha administrativa completa de un usuario de la firma. */
    public function show(Request $request, string $id): Response
    {
        $usuario = $this->container->get(UsuarioService::class)->find($this->firmaId(), (int) $id);

        return $this->json($usuario);
    }

    /** Sesion 7 (RF-017): filtro de usuarios con tarjeta profesional pendiente. */
    public function pendingCards(Request $request): Response
    {
        $usuarios = $this->container->get(UsuarioService::class)->pendingProfessionalCards($this->firmaId());

        return $this->json($usuarios);
    }

    /** Sesion 7 (RF-015): edicion de cargo, permiso propio usuarios.editar_cargo. */
    public function updateCargo(Request $request, string $id): Response
    {
        $cargo = (string) $request->input('cargo', '');
        $this->container->get(UsuarioService::class)->updateCargo($this->firmaId(), (int) $id, $cargo, $request);

        return $this->json(null, 'Cargo actualizado correctamente.');
    }

    /** Sesion 7 (RF-018): gestion de cuenta, permiso propio usuarios.editar_cuenta. */
    public function updateAccount(Request $request, string $id): Response
    {
        $this->container->get(UsuarioService::class)->updateAccount($this->firmaId(), (int) $id, (array) $request->input(), $request);

        return $this->json(null, 'Cuenta actualizada correctamente.');
    }

    /** Sesion 7 (RF-014/RF-018): revoca sesiones sin desactivar al usuario. */
    public function revokeSessions(Request $request, string $id): Response
    {
        $this->container->get(UsuarioService::class)->revokeSessions($this->firmaId(), (int) $id, $request);

        return $this->json(null, 'Sesiones revocadas correctamente.');
    }

    /** Sesion 8 (RF-030 a RF-034): historial de cambios sensibles/administrativos del usuario. */
    public function history(Request $request, string $id): Response
    {
        $limit = (int) $request->input('por_pagina', 50);
        $offset = (int) $request->input('offset', 0);
        $history = $this->container->get(UsuarioService::class)->history($this->firmaId(), (int) $id, $limit, $offset);

        return $this->json($history);
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
