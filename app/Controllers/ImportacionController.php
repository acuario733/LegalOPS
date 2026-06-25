<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\ImportacionService;

final class ImportacionController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('importaciones/index', [
            'title' => 'Importaciones CSV',
            'importaciones' => $this->container->get(ImportacionService::class)->recent($this->firmaId()),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function preview(Request $request): Response
    {
        $file = $request->file('archivo');
        if (!is_array($file)) {
            throw new HttpException(422, 'Adjunte un CSV valido.');
        }

        return $this->json($this->container->get(ImportacionService::class)->preview($this->firmaId(), (string) $request->input('tipo', ''), $file, $request), 'Importacion previsualizada.', 201);
    }

    public function confirm(Request $request, string $id): Response
    {
        return $this->json($this->container->get(ImportacionService::class)->confirm($this->firmaId(), (int) $id, $request), 'Importacion confirmada.');
    }

    public function apiList(Request $request): Response
    {
        return $this->json($this->container->get(ImportacionService::class)->recent($this->firmaId()));
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
