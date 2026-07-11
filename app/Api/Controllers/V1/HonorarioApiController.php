<?php

declare(strict_types=1);

namespace App\Api\Controllers\V1;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\HonorarioService;

final class HonorarioApiController extends Controller
{
    use RequiresApiScope;

    public function index(Request $request): Response
    {
        $this->requireApiScope($request, 'billing:read');
        $page = max(1, (int) $request->query('pagina', 1));
        $perPage = min(100, max(1, (int) $request->query('por_pagina', 25)));

        return $this->json($this->container->get(HonorarioService::class)->list(
            $this->apiFirmaId($request),
            (array) $request->query(),
            $page,
            $perPage
        ));
    }

    public function store(Request $request): Response
    {
        $this->requireApiScope($request, 'billing:write');
        $id = $this->container->get(HonorarioService::class)->create(
            $this->apiFirmaId($request),
            (array) $request->input(),
            $request
        );

        return $this->json(['id' => $id], 'Honorario creado correctamente.', 201);
    }
}
