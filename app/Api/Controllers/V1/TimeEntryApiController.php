<?php

declare(strict_types=1);

namespace App\Api\Controllers\V1;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\TimeEntryService;

final class TimeEntryApiController extends Controller
{
    use RequiresApiScope;

    public function index(Request $request): Response
    {
        $this->requireApiScope($request, 'billing:read');
        $items = $this->container->get(TimeEntryService::class)->list($this->apiFirmaId($request), (array) $request->query());
        $page = max(1, (int) $request->query('pagina', 1));
        $perPage = min(100, max(1, (int) $request->query('por_pagina', 25)));

        return $this->json([
            'items' => array_slice($items, ($page - 1) * $perPage, $perPage),
            'total' => count($items),
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requireApiScope($request, 'billing:write');
        $userId = (int) $request->getAttribute('api_usuario_id', 0);
        if ($userId <= 0) {
            throw new HttpException(422, 'El token debe estar vinculado a un usuario para registrar tiempo.');
        }
        $entry = $this->container->get(TimeEntryService::class)->create(
            $this->apiFirmaId($request),
            $userId,
            (array) $request->input()
        );

        return $this->json($entry, 'Tiempo registrado correctamente.', 201);
    }
}
