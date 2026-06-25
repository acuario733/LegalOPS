<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditoriaService;

final class AuditoriaController extends Controller
{
    public function index(Request $request): Response
    {
        $service = $this->container->get(AuditoriaService::class);
        $filters = (array) $request->query();
        $page = max(1, (int) $request->query('page', 1));
        $superadmin = ($this->currentUser()['tipo'] ?? null) === 'superadmin';
        $result = $superadmin
            ? $service->listGlobal($filters, $page)
            : $service->listForFirma($this->firmaId(), $filters, $page);

        return $this->view('auditoria/index', [
            'title' => 'Auditoría', 'events' => $result['items'], 'total' => $result['total'], 'filters' => $filters,
            'csrfToken' => $this->csrf->token(), 'currentUser' => $this->currentUser(),
        ], $superadmin ? 'superadmin' : 'app');
    }

    private function firmaId(): int
    {
        return $this->currentFirma() === null ? throw new HttpException(403, 'La operación requiere una firma activa.') : (int) $this->currentFirma();
    }
}

