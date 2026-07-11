<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

/**
 * Política CORS explícita para /api/v1/*.
 *
 * Orígenes autorizados: variable de entorno CORS_ALLOWED_ORIGINS (lista
 * separada por comas). Un valor único '*' permite cualquier origen pero
 * deshabilita Access-Control-Allow-Credentials.
 *
 * Las solicitudes sin header Origin (server-to-server, curl, Postman) pasan
 * sin validación — CORS solo aplica a browsers.
 *
 * Para preflights OPTIONS, App::handle() llama directamente a preflight()
 * porque el Router no ejecuta middleware global en rutas no registradas.
 */
final class CorsMiddleware implements MiddlewareInterface
{
    private const ALLOWED_METHODS = 'GET, POST, PUT, PATCH, DELETE, OPTIONS';
    private const ALLOWED_HEADERS = 'Content-Type, Authorization, X-CSRF-TOKEN, X-Correlation-ID';
    private const MAX_AGE         = '86400';

    /** @var list<string> */
    private readonly array $allowedOrigins;

    public function __construct(string $allowedOriginsRaw = '')
    {
        if ($allowedOriginsRaw === '') {
            $this->allowedOrigins = [];
        } else {
            $this->allowedOrigins = array_values(
                array_filter(array_map('trim', explode(',', $allowedOriginsRaw)))
            );
        }
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!str_starts_with($request->uri(), '/api/v1/')) {
            return $next($request);
        }

        $origin = $request->header('origin');

        if ($origin === null) {
            return $next($request);
        }

        if (!$this->isAllowed($origin)) {
            return Response::error('Origin no autorizado.', 403);
        }

        $response = $next($request);

        return $this->withCorsHeaders($response, $origin);
    }

    /**
     * Maneja preflights OPTIONS antes de que el Router intente despachar
     * (el Router no ejecuta middleware global en rutas sin registrar).
     */
    public function preflight(Request $request): Response
    {
        $origin = $request->header('origin') ?? '';

        if ($origin === '' || !$this->isAllowed($origin)) {
            return Response::error('Origin no autorizado.', 403);
        }

        $response = Response::html('', 200);

        return $this->withCorsHeaders($response, $origin)
            ->withHeader('Access-Control-Allow-Methods', self::ALLOWED_METHODS)
            ->withHeader('Access-Control-Allow-Headers', self::ALLOWED_HEADERS)
            ->withHeader('Access-Control-Max-Age', self::MAX_AGE);
    }

    private function isAllowed(string $origin): bool
    {
        if (in_array('*', $this->allowedOrigins, true)) {
            return true;
        }

        return in_array($origin, $this->allowedOrigins, true);
    }

    private function withCorsHeaders(Response $response, string $origin): Response
    {
        $wildcard = in_array('*', $this->allowedOrigins, true);

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $wildcard ? '*' : $origin)
            ->withHeader('Vary', 'Origin');

        if (!$wildcard) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }
}
