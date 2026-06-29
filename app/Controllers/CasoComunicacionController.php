<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\CasoComunicacionService;

final class CasoComunicacionController extends Controller
{
    public function index(Request $request, string $casoId): Response
    {
        $firmaId = $this->firmaId();
        $id = (int) $casoId;
        $service = $this->container->get(CasoComunicacionService::class);
        $items = $service->list($id, $firmaId, (array) $request->query());
        $email = $service->getEmailAddress($id, $firmaId);
        $html = $this->viewContent('comunicaciones/_timeline', [
            'comunicaciones' => $items,
            'canDelete' => $this->permissions->allows('comunicaciones.eliminar', $this->currentUser()),
        ]);

        return $this->json([
            'items' => $items,
            'email_address' => $email,
            'html' => $html,
        ]);
    }

    public function store(Request $request, string $casoId): Response
    {
        $created = $this->container->get(CasoComunicacionService::class)->create(
            (int) $casoId,
            $this->firmaId(),
            $this->usuarioId(),
            (array) $request->input()
        );

        return $this->json($created, 'Comunicacion registrada correctamente.', 201);
    }

    public function destroy(Request $request, string $casoId, string $id): Response
    {
        $this->container->get(CasoComunicacionService::class)->delete((int) $id, $this->firmaId());

        return $this->json(null, 'Comunicacion eliminada correctamente.');
    }

    public function emailAddress(Request $request, string $casoId): Response
    {
        return $this->json([
            'email_address' => $this->container->get(CasoComunicacionService::class)->getEmailAddress((int) $casoId, $this->firmaId()),
        ]);
    }

    /** @param array<string, mixed> $data */
    private function viewContent(string $view, array $data): string
    {
        return $this->container->get(\App\Core\View::class)->render($view, $data, null);
    }

    private function firmaId(): int
    {
        $firmaId = $this->currentFirma();
        if ($firmaId === null) {
            throw new HttpException(403, 'La operacion requiere una firma activa.');
        }

        return (int) $firmaId;
    }

    private function usuarioId(): int
    {
        $id = $this->auth->id();
        if ($id === null) {
            throw new HttpException(403, 'La operacion requiere usuario autenticado.');
        }

        return (int) $id;
    }
}
