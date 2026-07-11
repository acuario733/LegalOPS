<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Middleware\ApiAuthMiddleware;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Tests de ApiAuthMiddleware.
 *
 * Verifica que el middleware:
 * 1. Rechace requests sin token (401)
 * 2. Rechace tokens inválidos (401)
 * 3. Rechace tokens revocados (401)
 * 4. Rechace tokens expirados (401)
 * 5. Rechace si la firma está suspendida (401)
 * 6. Acepte tokens válidos y adjunte contexto al request
 * 7. Acepte tokens con scopes correctos
 * 8. Acepte tokens sin restricción de scopes (acceso total)
 *
 * Usa SQLite :memory: — no requiere MySQL.
 */
class ApiAuthMiddlewareTest extends TestCase
{
    private PDO $pdo;
    private ApiAuthMiddleware $middleware;
    private int $firmaId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->createSchema();
        $this->firmaId = $this->insertFirma('Firma Test');
        $this->middleware = new ApiAuthMiddleware($this->pdo);
    }

    protected function tearDown(): void
    {
        unset($this->pdo, $this->middleware);
        parent::tearDown();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Schema & helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function createSchema(): void
    {
        $this->pdo->exec("
            CREATE TABLE firmas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre TEXT NOT NULL,
                suspended_at TEXT DEFAULT NULL,
                deleted_at TEXT DEFAULT NULL
            )
        ");

        $this->pdo->exec("
            CREATE TABLE api_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id INTEGER NOT NULL,
                usuario_id INTEGER DEFAULT NULL,
                nombre TEXT NOT NULL,
                token_hash TEXT NOT NULL UNIQUE,
                scopes TEXT DEFAULT NULL,
                ultimo_uso_at TEXT DEFAULT NULL,
                expira_at TEXT DEFAULT NULL,
                revocado_at TEXT DEFAULT NULL,
                created_at TEXT DEFAULT (datetime('now')),
                updated_at TEXT DEFAULT (datetime('now'))
            )
        ");
    }

    private function insertFirma(string $nombre, bool $suspended = false): int
    {
        $suspendedAt = $suspended ? date('Y-m-d H:i:s') : null;
        $this->pdo->prepare(
            "INSERT INTO firmas (nombre, suspended_at) VALUES (?, ?)"
        )->execute([$nombre, $suspendedAt]);
        return (int) $this->pdo->lastInsertId();
    }

    private function insertToken(
        int $firmaId,
        string $rawToken,
        ?string $expiraAt = null,
        ?string $revocadoAt = null,
        ?string $scopes = null,
    ): void {
        $hash = hash('sha256', $rawToken);
        $this->pdo->prepare(
            "INSERT INTO api_tokens (firma_id, nombre, token_hash, scopes, expira_at, revocado_at)
             VALUES (?, 'Test Token', ?, ?, ?, ?)"
        )->execute([$firmaId, $hash, $scopes, $expiraAt, $revocadoAt]);
    }

    private function makeRequest(string $authHeader = ''): Request
    {
        $headers = [];
        if ($authHeader !== '') {
            $headers['authorization'] = $authHeader;
        }
        return new Request('GET', '/api/v1/clientes', headers: $headers);
    }

    private function nextMiddleware(): callable
    {
        return static fn(Request $req): Response => Response::json(['passed' => true], 'OK', 200);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tests
    // ─────────────────────────────────────────────────────────────────────────

    public function test_rejects_request_without_authorization_header(): void
    {
        $response = $this->middleware->handle($this->makeRequest(), $this->nextMiddleware());

        $this->assertSame(401, $response->status());
    }

    public function test_rejects_request_with_non_bearer_scheme(): void
    {
        $response = $this->middleware->handle(
            $this->makeRequest('Basic dXNlcjpwYXNz'),
            $this->nextMiddleware()
        );

        $this->assertSame(401, $response->status());
    }

    public function test_rejects_empty_bearer_token(): void
    {
        $response = $this->middleware->handle(
            $this->makeRequest('Bearer '),
            $this->nextMiddleware()
        );

        $this->assertSame(401, $response->status());
    }

    public function test_rejects_token_not_in_database(): void
    {
        $response = $this->middleware->handle(
            $this->makeRequest('Bearer ' . str_repeat('a', 64)),
            $this->nextMiddleware()
        );

        $this->assertSame(401, $response->status());
    }

    public function test_rejects_revoked_token(): void
    {
        $rawToken = bin2hex(random_bytes(32));
        $this->insertToken($this->firmaId, $rawToken, revocadoAt: date('Y-m-d H:i:s'));

        $response = $this->middleware->handle(
            $this->makeRequest("Bearer $rawToken"),
            $this->nextMiddleware()
        );

        $this->assertSame(401, $response->status());
    }

    public function test_rejects_expired_token(): void
    {
        $rawToken = bin2hex(random_bytes(32));
        $pastDate = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $this->insertToken($this->firmaId, $rawToken, expiraAt: $pastDate);

        $response = $this->middleware->handle(
            $this->makeRequest("Bearer $rawToken"),
            $this->nextMiddleware()
        );

        $this->assertSame(401, $response->status());
    }

    public function test_rejects_token_from_suspended_firma(): void
    {
        $firmaB   = $this->insertFirma('Firma Suspendida', suspended: true);
        $rawToken = bin2hex(random_bytes(32));
        $this->insertToken($firmaB, $rawToken);

        $response = $this->middleware->handle(
            $this->makeRequest("Bearer $rawToken"),
            $this->nextMiddleware()
        );

        $this->assertSame(401, $response->status());
    }

    public function test_allows_valid_token_and_calls_next(): void
    {
        $rawToken = bin2hex(random_bytes(32));
        $this->insertToken($this->firmaId, $rawToken);

        $request  = $this->makeRequest("Bearer $rawToken");
        $response = $this->middleware->handle($request, $this->nextMiddleware());

        $this->assertSame(200, $response->status());
    }

    public function test_attaches_firma_id_to_request_on_valid_token(): void
    {
        $rawToken = bin2hex(random_bytes(32));
        $this->insertToken($this->firmaId, $rawToken);

        $request = $this->makeRequest("Bearer $rawToken");
        $this->middleware->handle($request, $this->nextMiddleware());

        $this->assertSame($this->firmaId, $request->getAttribute('api_firma_id'));
    }

    public function test_attaches_scopes_to_request_when_token_has_scopes(): void
    {
        $rawToken = bin2hex(random_bytes(32));
        $scopes   = json_encode(['clientes:read', 'casos:read']);
        $this->insertToken($this->firmaId, $rawToken, scopes: $scopes);

        $request = $this->makeRequest("Bearer $rawToken");
        $this->middleware->handle($request, $this->nextMiddleware());

        $this->assertSame(['clientes:read', 'casos:read'], $request->getAttribute('api_scopes'));
    }

    public function test_attaches_empty_scopes_when_token_has_no_scope_restriction(): void
    {
        $rawToken = bin2hex(random_bytes(32));
        $this->insertToken($this->firmaId, $rawToken, scopes: null);

        $request = $this->makeRequest("Bearer $rawToken");
        $this->middleware->handle($request, $this->nextMiddleware());

        $this->assertSame([], $request->getAttribute('api_scopes'));
    }

    public function test_allows_non_expired_token_with_future_expiry(): void
    {
        $rawToken   = bin2hex(random_bytes(32));
        $futureDate = date('Y-m-d H:i:s', strtotime('+30 days'));
        $this->insertToken($this->firmaId, $rawToken, expiraAt: $futureDate);

        $response = $this->middleware->handle(
            $this->makeRequest("Bearer $rawToken"),
            $this->nextMiddleware()
        );

        $this->assertSame(200, $response->status());
    }

    public function test_response_includes_www_authenticate_header_on_rejection(): void
    {
        $response = $this->middleware->handle($this->makeRequest(), $this->nextMiddleware());

        $headers = $response->headers();
        $this->assertArrayHasKey('WWW-Authenticate', $headers);
    }
}
