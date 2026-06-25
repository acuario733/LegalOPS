<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\NotificacionService;

final class NotificacionController extends Controller
{
    public function index(Request $request): Response
    {
        $firmaId = $this->firmaId();
        $usuarioId = $this->usuarioId();

        return $this->view('notificaciones/index', [
            'title' => 'Notificaciones',
            'notificaciones' => $this->container->get(NotificacionService::class)->list($firmaId, $usuarioId, (array) $request->query(), max(1, (int) $request->query('page', 1))),
            'filters' => (array) $request->query(),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function read(Request $request, string $id): Response
    {
        $this->container->get(NotificacionService::class)->markRead($this->firmaId(), $this->usuarioId(), (int) $id, $request);

        return $this->json(null, 'Notificacion marcada como leida.');
    }

    public function search(Request $request): Response
    {
        return $this->json($this->container->get(NotificacionService::class)->list($this->firmaId(), $this->usuarioId(), (array) $request->query(), max(1, (int) $request->query('page', 1))));
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
