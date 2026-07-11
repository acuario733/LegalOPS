<?php

declare(strict_types=1);

namespace App\Api\Controllers\V1;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\ClienteRepository;

/**
 * ClienteApiController — endpoints públicos de Clientes para /api/v1/
 *
 * Todos los métodos obtienen firma_id del contexto del Bearer token
 * (inyectado por ApiAuthMiddleware), NO de la sesión.
 *
 * TENANT FILTER: firma_id siempre viene de $request->getAttribute('api_firma_id')
 */
final class ClienteApiController extends Controller
{
    public function index(Request $request): Response
    {
        $firmaId = (int) $request->getAttribute('api_firma_id'); // TENANT FILTER: Bearer token

        $this->requireScope($request, 'clientes:read');

        $filters = [
            'q'      => (string) $request->query('q', ''),
            'estado' => (string) $request->query('estado', ''),
        ];
        $page    = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));

        $resultado = $this->container->get(ClienteRepository::class)
            ->paginate($firmaId, $filters, page: $page, perPage: $perPage);

        return $this->json([
            'data'       => $resultado['items'],
            'total'      => $resultado['total'],
            'page'       => $page,
            'per_page'   => $perPage,
            'last_page'  => (int) ceil($resultado['total'] / $perPage),
        ]);
    }

    public function show(Request $request): Response
    {
        $firmaId   = (int) $request->getAttribute('api_firma_id'); // TENANT FILTER
        $clienteId = (int) $request->route('id');

        $this->requireScope($request, 'clientes:read');

        $cliente = $this->container->get(ClienteRepository::class)
            ->findForFirma($firmaId, $clienteId);

        if ($cliente === null) {
            return $this->json(null, 'Cliente no encontrado.', 404, ok: false);
        }

        return $this->json($cliente);
    }

    /** Verifica que el token tenga el scope requerido. Lanza 403 si no. */
    private function requireScope(Request $request, string $scope): void
    {
        $scopes = (array) $request->getAttribute('api_scopes', []);

        // Si scopes está vacío, el token tiene acceso completo (todos los scopes)
        if (empty($scopes)) {
            return;
        }

        if (!in_array($scope, $scopes, true)) {
            throw new \App\Core\HttpException(403, "Scope requerido: $scope");
        }
    }
}
