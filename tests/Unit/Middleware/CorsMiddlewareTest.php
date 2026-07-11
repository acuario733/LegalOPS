<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Middleware\CorsMiddleware;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('phase1c')]
class CorsMiddlewareTest extends TestCase
{
    private function makeRequest(string $method = 'GET', string $uri = '/api/v1/honorarios', ?string $origin = null): Request
    {
        $headers = [];
        if ($origin !== null) {
            $headers['origin'] = $origin;
        }

        return new Request($method, $uri, headers: $headers);
    }

    private function passThroughNext(): callable
    {
        return static fn (Request $r): Response => Response::json(['ok' => true]);
    }

    // ── Caso feliz: origen permitido recibe headers CORS ─────────────────────

    public function test_allowed_origin_receives_cors_headers(): void
    {
        $mw = new CorsMiddleware('https://app.ejemplo.com');
        $req = $this->makeRequest(origin: 'https://app.ejemplo.com');

        $res = $mw->handle($req, $this->passThroughNext());

        $this->assertSame('https://app.ejemplo.com', $res->headers()['Access-Control-Allow-Origin']);
        $this->assertSame('true', $res->headers()['Access-Control-Allow-Credentials']);
        $this->assertSame('Origin', $res->headers()['Vary']);
    }

    // ── Caso error: origen NO permitido recibe 403 ────────────────────────────

    public function test_disallowed_origin_receives_403(): void
    {
        $mw = new CorsMiddleware('https://app.ejemplo.com');
        $req = $this->makeRequest(origin: 'https://malicioso.com');

        $res = $mw->handle($req, $this->passThroughNext());

        $this->assertSame(403, $res->status());
        $this->assertArrayNotHasKey('Access-Control-Allow-Origin', $res->headers());
    }

    // ── Sin Origin (server-to-server): pasa sin validación ───────────────────

    public function test_no_origin_header_passes_through(): void
    {
        $mw = new CorsMiddleware('https://app.ejemplo.com');
        $req = $this->makeRequest(); // sin Origin

        $res = $mw->handle($req, $this->passThroughNext());

        $this->assertSame(200, $res->status());
        $this->assertArrayNotHasKey('Access-Control-Allow-Origin', $res->headers());
    }

    // ── Ruta no-API: pasa sin modificar aunque el origen no esté en la lista ─

    public function test_non_api_path_passes_through_regardless_of_origin(): void
    {
        $mw = new CorsMiddleware('https://app.ejemplo.com');
        $req = $this->makeRequest(uri: '/login', origin: 'https://malicioso.com');

        $res = $mw->handle($req, $this->passThroughNext());

        $this->assertSame(200, $res->status());
        $this->assertArrayNotHasKey('Access-Control-Allow-Origin', $res->headers());
    }

    // ── Preflight con origen permitido: 200 + métodos + headers ─────────────

    public function test_preflight_allowed_origin_returns_200_with_cors_headers(): void
    {
        $mw = new CorsMiddleware('https://app.ejemplo.com');
        $req = $this->makeRequest('OPTIONS', '/api/v1/honorarios', 'https://app.ejemplo.com');

        $res = $mw->preflight($req);

        $this->assertSame(200, $res->status());
        $this->assertSame('https://app.ejemplo.com', $res->headers()['Access-Control-Allow-Origin']);
        $this->assertStringContainsString('POST', $res->headers()['Access-Control-Allow-Methods']);
        $this->assertStringContainsString('Authorization', $res->headers()['Access-Control-Allow-Headers']);
        $this->assertSame('86400', $res->headers()['Access-Control-Max-Age']);
    }

    // ── Preflight con origen NO permitido: 403 ────────────────────────────────

    public function test_preflight_disallowed_origin_returns_403(): void
    {
        $mw = new CorsMiddleware('https://app.ejemplo.com');
        $req = $this->makeRequest('OPTIONS', '/api/v1/honorarios', 'https://malicioso.com');

        $res = $mw->preflight($req);

        $this->assertSame(403, $res->status());
    }

    // ── Wildcard * permite cualquier origen sin credentials ───────────────────

    public function test_wildcard_allows_any_origin_without_credentials(): void
    {
        $mw = new CorsMiddleware('*');
        $req = $this->makeRequest(origin: 'https://cualquiera.com');

        $res = $mw->handle($req, $this->passThroughNext());

        $this->assertSame('*', $res->headers()['Access-Control-Allow-Origin']);
        $this->assertArrayNotHasKey('Access-Control-Allow-Credentials', $res->headers());
    }

    // ── Lista de múltiples orígenes ───────────────────────────────────────────

    public function test_multiple_allowed_origins_each_get_cors_headers(): void
    {
        $mw = new CorsMiddleware('https://a.com,https://b.com');

        $resA = $mw->handle(
            $this->makeRequest(origin: 'https://a.com'),
            $this->passThroughNext()
        );
        $resB = $mw->handle(
            $this->makeRequest(origin: 'https://b.com'),
            $this->passThroughNext()
        );
        $resC = $mw->handle(
            $this->makeRequest(origin: 'https://c.com'),
            $this->passThroughNext()
        );

        $this->assertSame('https://a.com', $resA->headers()['Access-Control-Allow-Origin']);
        $this->assertSame('https://b.com', $resB->headers()['Access-Control-Allow-Origin']);
        $this->assertSame(403, $resC->status());
    }
}
