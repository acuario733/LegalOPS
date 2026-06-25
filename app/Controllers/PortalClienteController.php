<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\PortalClienteService;

final class PortalClienteController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('portal/index', [
            'title' => 'Portal cliente',
            'portal' => $this->container->get(PortalClienteService::class)->home($this->firmaId(), $request),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ], 'portal');
    }

    public function download(Request $request, string $id): Response
    {
        return $this->container->get(PortalClienteService::class)->downloadDocument($this->firmaId(), (int) $id, $request);
    }

    public function accept(Request $request, string $id): Response
    {
        $this->container->get(PortalClienteService::class)->acceptLegal($this->firmaId(), (int) $id, $request);

        return $this->json(null, 'Aceptacion registrada en el portal.');
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
