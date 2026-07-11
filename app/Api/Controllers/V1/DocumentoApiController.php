<?php

declare(strict_types=1);

namespace App\Api\Controllers\V1;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\DocumentoService;
use App\Services\DocumentoVersionService;

final class DocumentoApiController extends Controller
{
    use RequiresApiScope;

    public function index(Request $request): Response
    {
        $this->requireApiScope($request, 'documents:read');
        $page = max(1, (int) $request->query('pagina', 1));
        $perPage = min(100, max(1, (int) $request->query('por_pagina', 25)));

        return $this->json($this->container->get(DocumentoService::class)->list(
            $this->apiFirmaId($request),
            (array) $request->query(),
            $page,
            $perPage
        ));
    }

    public function store(Request $request): Response
    {
        $this->requireApiScope($request, 'documents:write');
        $file = $request->file('archivo');
        if (!is_array($file)) {
            throw new HttpException(422, 'Adjunte un archivo valido.');
        }
        $id = $this->container->get(DocumentoService::class)->create(
            $this->apiFirmaId($request),
            (array) $request->input(),
            $file,
            $request
        );

        return $this->json(['id' => $id], 'Documento cargado correctamente.', 201);
    }

    public function download(Request $request, string $id): Response
    {
        $this->requireApiScope($request, 'documents:read');

        return $this->json($this->container->get(DocumentoVersionService::class)->currentPresignedUrl(
            $this->apiFirmaId($request),
            (int) $id,
            $request
        ));
    }
}
