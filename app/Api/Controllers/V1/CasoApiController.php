<?php

declare(strict_types=1);

namespace App\Api\Controllers\V1;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\CasoRepository;

/**
 * CasoApiController — endpoints públicos de Casos para /api/v1/
 *
 * TENANT FILTER: firma_id siempre viene de $request->getAttribute('api_firma_id')
 */
final class CasoApiController extends Controller
{
    public function index(Request $request): Response
    {
        $firmaId = (int) $request->getAttribute('api_firma_id'); // TENANT FILTER

        $this->requireScope($request, 'casos:read');

        $filters = [
            'q'        => (string) $request->query('q', ''),
            'estado'   => (string) $request->query('estado', ''),
            'prioridad' => (string) $request->query('prioridad', ''),
        ];
        $page    = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));

        $resultado = $this->container->get(CasoRepository::class)
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
        $casoId  = (int) $request->route('id');

        $this->requireScope($request, 'casos:read');

        $caso = $this->container->get(CasoRepository::class)
            ->findForFirma($firmaId, $casoId);

        if ($caso === null) {
            return $this->json(null, 'Caso no encontrado.', 404, ok: false);
        }

        return $this->json($caso);
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
