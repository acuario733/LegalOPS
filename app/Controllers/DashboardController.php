<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\DashboardService;

final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('dashboard/index', [
            'title' => 'Dashboard',
            'summary' => $this->container->get(DashboardService::class)->summary($this->firmaId(), $this->currentUser() ?? []),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function api(Request $request): Response
    {
        return $this->json($this->container->get(DashboardService::class)->summary($this->firmaId(), $this->currentUser() ?? []));
    }

    private function firmaId(): int
    {
        $firmaId = $this->currentFirma();
        if ($firmaId === null) {
            throw new HttpException(403, 'La operacion requiere una firma activa.');
        }

        return (int) $firmaId;
    }
}
