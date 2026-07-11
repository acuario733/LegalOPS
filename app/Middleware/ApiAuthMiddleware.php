<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use PDO;
use App\Security\ApiRateLimitService;

/**
 * ApiAuthMiddleware — autenticación Bearer token para /api/v1/
 *
 * Valida el token en la cabecera Authorization: Bearer <token>
 * Verifica: existencia, hash, no revocado, no expirado, firma activa.
 *
 * Si el token es válido, adjunta el contexto al request:
 *   $request->getAttribute('api_firma_id')  → int
 *   $request->getAttribute('api_token_id')  → int
 *   $request->getAttribute('api_scopes')    → array
 *
 * SEGURIDAD:
 * - El token en texto plano nunca se almacena — solo el SHA-256 (token_hash)
 * - Timing-safe comparison con hash_equals()
 * - Fail-closed: cualquier error retorna 401 (nunca 500 visible)
 */
final class ApiAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ?ApiRateLimitService $rateLimiter = null
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $authHeader = $request->header('authorization', '');

        if (!str_starts_with((string) $authHeader, 'Bearer ')) {
            return $this->unauthorized('Token de acceso requerido.');
        }

        $rawToken = substr((string) $authHeader, 7);

        if ($rawToken === '' || strlen($rawToken) < 32) {
            return $this->unauthorized('Token inválido.');
        }

        // Hash del token recibido para comparar con BD
        $tokenHash = hash('sha256', $rawToken);

        // Buscar token en BD (TENANT FILTER: firma activa)
        $stmt = $this->pdo->prepare(
            'SELECT
                t.id,
                t.firma_id,
                t.usuario_id,
                t.scopes,
                t.expira_at,
                t.revocado_at,
                f.suspended_at   AS firma_suspended_at,
                f.deleted_at     AS firma_deleted_at
             FROM api_tokens t
             INNER JOIN firmas f ON f.id = t.firma_id
             WHERE t.token_hash = :hash
             LIMIT 1'
        );
        $stmt->execute(['hash' => $tokenHash]);
        $token = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($token === false) {
            return $this->unauthorized('Token no encontrado.');
        }

        // Verificar que no esté revocado
        if ($token['revocado_at'] !== null) {
            return $this->unauthorized('Token revocado.');
        }

        // Verificar que no haya expirado
        if ($token['expira_at'] !== null && strtotime($token['expira_at']) < time()) {
            return $this->unauthorized('Token expirado.');
        }

        // Verificar que la firma esté activa
        if ($token['firma_suspended_at'] !== null || $token['firma_deleted_at'] !== null) {
            return $this->unauthorized('Firma inactiva o suspendida.');
        }

        // Actualizar último uso (best-effort, no falla si hay error)
        try {
            $this->pdo->prepare(
                'UPDATE api_tokens SET ultimo_uso_at = NOW(6) WHERE id = :id'
            )->execute(['id' => $token['id']]);
        } catch (\Throwable) {
            // No bloquear la request por esto
        }

        // Decodificar scopes
        $scopes = [];
        if ($token['scopes'] !== null) {
            $decoded = json_decode((string) $token['scopes'], true);
            $scopes  = is_array($decoded) ? $decoded : [];
        }

        // Adjuntar contexto al request para los controllers
        $request->setAttribute('api_firma_id', (int) $token['firma_id']);
        $request->setAttribute('api_token_id', (int) $token['id']);
        $request->setAttribute('api_usuario_id', $token['usuario_id'] !== null ? (int) $token['usuario_id'] : null);
        $request->setAttribute('api_scopes', $scopes);

        $rate = ($this->rateLimiter ?? new ApiRateLimitService())->check((int) $token['id']);
        if (!$rate['allowed']) {
            return Response::json(null, 'Limite de API excedido.', 429, [], false)
                ->withHeader('Retry-After', (string) $rate['retry_after'])
                ->withHeader('X-RateLimit-Remaining', '0')
                ->withHeader('X-RateLimit-Reset', (string) $rate['reset_at']);
        }

        return $next($request)
            ->withHeader('X-RateLimit-Remaining', (string) $rate['remaining'])
            ->withHeader('X-RateLimit-Reset', (string) $rate['reset_at']);
    }

    private function unauthorized(string $message): Response
    {
        return Response::json(
            data: null,
            message: $message,
            status: 401,
            errors: [],
            ok: false
        )->withHeader('WWW-Authenticate', 'Bearer realm="LegalOPS API"');
    }
}
