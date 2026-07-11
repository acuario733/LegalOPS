<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\SessionService;

final class SessionController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('profile/sessions', [
            'title' => 'Sesiones activas',
            'sessions' => $this->container->get(SessionService::class)->mine(),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function revoke(Request $request, string $id): Response
    {
        $this->container->get(SessionService::class)->revoke((int) $id, $request);

        return $this->json(null, 'Sesión revocada correctamente.');
    }
}
