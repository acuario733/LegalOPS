<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AceptacionLegalService;

final class AceptacionLegalController extends Controller
{
    public function admin(Request $request): Response
    {
        return $this->view('legal/admin', [
            'title' => 'Documentos legales', 'documents' => $this->container->get(AceptacionLegalService::class)->allDocuments(),
            'csrfToken' => $this->csrf->token(), 'currentUser' => $this->currentUser(),
        ], 'superadmin');
    }

    public function store(Request $request): Response
    {
        $id = $this->container->get(AceptacionLegalService::class)->createDocument((array) $request->input(), $request);

        return $this->json(['id' => $id], 'Documento legal creado.', 201);
    }

    public function publish(Request $request, string $id): Response
    {
        $this->container->get(AceptacionLegalService::class)->publish((int) $id, $request);

        return $this->json(null, 'Documento legal publicado.');
    }

    public function pending(Request $request): Response
    {
        return $this->view('legal/pending', [
            'title' => 'Aceptaciones legales', 'documents' => $this->container->get(AceptacionLegalService::class)->pending(),
            'csrfToken' => $this->csrf->token(), 'currentUser' => $this->currentUser(),
        ]);
    }

    public function accept(Request $request, string $id): Response
    {
        $this->container->get(AceptacionLegalService::class)->accept((int) $id, $request);

        return $this->json(null, 'Aceptación registrada correctamente.');
    }
}

