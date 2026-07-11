<?php

declare(strict_types=1);

namespace App\Api\Controllers\V1;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\TareaRepository;

/**
 * TareaApiController — endpoints públicos de Tareas para /api/v1/
 *
 * TENANT FILTER: firma_id siempre viene de $request->getAttribute('api_firma_id')
 */
final class TareaApiController extends Controller
{
    public function index(Request $request): Response
    {
        $firmaId = (int) $request->getAttribute('api_firma_id'); // TENANT FILTER

        $this->requireScope($request, 'tareas:read');

        $filters = [
            'q'       => (string) $request->query('q', ''),
            'estado'  => (string) $request->query('estado', ''),
            'caso_id' => (string) $request->query('caso_id', ''),
        ];
        $page    = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));

        $resultado = $this->container->get(TareaRepository::class)
            ->paginate($firmaId, $filters, page: $page, perPage: $perPage);

        return $this->json([
            'data'      => $resultado['items'],
            'total'     => $resultado['total'],
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => (int) ceil($resultado['total'] / $perPage),
        ]);
    }

    public function show(Request $request): Response
    {
        $firmaId = (int) $request->getAttribute('api_firma_id'); // TENANT FILTER
        $tareaId = (int) $request->route('id');

        $this->requireScope($request, 'tareas:read');

        $tarea = $this->container->get(TareaRepository::class)
            ->findForFirma($firmaId, $tareaId);

        if ($tarea === null) {
            return $this->json(null, 'Tarea no encontrada.', 404, ok: false);
        }

        return $this->json($tarea);
    }

    private function requireScope(Request $request, string $scope): void
    {
        $scopes = (array) $request->getAttribute('api_scopes', []);
        if (empty($scopes)) {
            return;
        }
        if (!in_array($scope, $scopes, true)) {
            throw new HttpException(403, "Scope requerido: $scope");
        }
    }
}
