<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\PerfilService;

final class PerfilController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $this->authenticatedUser();
        $service = $this->container->get(PerfilService::class);

        return $this->view('perfil/show', [
            'title' => 'Mi perfil',
            'perfil' => $service->own(),
            'tiposDocumento' => $service->documentTypes(),
            'canEdit' => $this->permissions->allows('perfil.editar', $user),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $user,
        ], $this->layoutFor($user));
    }

    public function update(Request $request): Response
    {
        $user = $this->authenticatedUser();
        $this->container->get(PerfilService::class)->updatePersonal((array) $request->input(), $request);

        return $this->json(null, 'Información personal actualizada correctamente.');
    }

    public function photo(Request $request): Response
    {
        $this->authenticatedUser();
        $photo = $this->container->get(PerfilService::class)->ownPhoto();

        return Response::file($photo['path'], $photo['mime']);
    }

    public function updateProfessional(Request $request): Response
    {
        $this->authenticatedUser();
        $this->container->get(PerfilService::class)->updateProfessional((array) $request->input(), $request);

        return $this->json(null, 'Información profesional actualizada correctamente.');
    }

    public function changePassword(Request $request): Response
    {
        $this->authenticatedUser();
        $this->container->get(PerfilService::class)->changePassword((array) $request->input(), $request);

        return $this->json(null, 'Contraseña actualizada correctamente.');
    }

    /** @return array<string, mixed> */
    private function authenticatedUser(): array
    {
        $user = $this->currentUser();
        if (!is_array($user) || !isset($user['id'])) {
            throw new HttpException(401, 'Debe iniciar sesión para consultar su perfil.');
        }

        return $user;
    }

    /** @param array<string, mixed> $user */
    private function layoutFor(array $user): string
    {
        return match ((string) ($user['tipo'] ?? '')) {
            'cliente_externo' => 'portal',
            'superadmin' => 'superadmin',
            default => 'app',
        };
    }
}
