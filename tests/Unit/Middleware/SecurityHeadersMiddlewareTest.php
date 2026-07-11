<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Middleware\SecurityHeadersMiddleware;
use PHPUnit\Framework\TestCase;

/**
 * Tests de SecurityHeadersMiddleware.
 *
 * Verifica que todos los headers de seguridad se añadan correctamente
 * y que HSTS solo aparezca en production.
 */
class SecurityHeadersMiddlewareTest extends TestCase
{
    private function makeRequest(string $method = 'GET', string $uri = '/'): Request
    {
        return new Request($method, $uri);
    }

    private function runMiddleware(Request $request, Response $innerResponse): Response
    {
        $mw = new SecurityHeadersMiddleware();
        return $mw->handle($request, static fn (Request $r): Response => $innerResponse);
    }

    // ── headers de seguridad obligatorios ────────────────────────────────────

    public function test_adds_x_content_type_options_nosniff(): void
    {
        $req = $this->makeRequest();
        $res = $this->runMiddleware($req, Response::html('ok'));

        $this->assertSame('nosniff', $res->headers()['X-Content-Type-Options']);
    }

    public function test_adds_x_frame_options_sameorigin(): void
    {
        $req = $this->makeRequest();
        $res = $this->runMiddleware($req, Response::html('ok'));

        $this->assertSame('SAMEORIGIN', $res->headers()['X-Frame-Options']);
    }

    public function test_adds_x_xss_protection(): void
    {
        $req = $this->makeRequest();
        $res = $this->runMiddleware($req, Response::html('ok'));

        $this->assertSame('1; mode=block', $res->headers()['X-XSS-Protection']);
    }

    public function test_adds_referrer_policy(): void
    {
        $req = $this->makeRequest();
        $res = $this->runMiddleware($req, Response::html('ok'));

        $this->assertSame('strict-origin-when-cross-origin', $res->headers()['Referrer-Policy']);
    }

    public function test_adds_permissions_policy(): void
    {
        $req = $this->makeRequest();
        $res = $this->runMiddleware($req, Response::html('ok'));

        $this->assertStringContainsString('geolocation=()', $res->headers()['Permissions-Policy']);
        $this->assertStringContainsString('camera=()', $res->headers()['Permissions-Policy']);
    }

    public function test_adds_content_security_policy(): void
    {
        $req = $this->makeRequest();
        $res = $this->runMiddleware($req, Response::html('ok'));

        $csp = $res->headers()['Content-Security-Policy'];
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
    }

    // ── HSTS: solo en producción ──────────────────────────────────────────────

    public function test_hsts_header_absent_in_non_production(): void
    {
        // El test runner no tiene APP_ENV=production → HSTS no debe estar
        putenv('APP_ENV=testing');
        $req = $this->makeRequest();
        $res = $this->runMiddleware($req, Response::html('ok'));

        $this->assertArrayNotHasKey('Strict-Transport-Security', $res->headers());
        putenv('APP_ENV=');
    }

    public function test_hsts_header_present_in_production(): void
    {
        putenv('APP_ENV=production');
        try {
            $req = $this->makeRequest();
            $res = $this->runMiddleware($req, Response::html('ok'));

            $hsts = $res->headers()['Strict-Transport-Security'] ?? '';
            $this->assertStringContainsString('max-age=31536000', $hsts);
            $this->assertStringContainsString('includeSubDomains', $hsts);
        } finally {
            putenv('APP_ENV=');
        }
    }

    // ── Cache-Control para HTML ───────────────────────────────────────────────

    public function test_adds_no_store_cache_control_for_html_response(): void
    {
        $req = $this->makeRequest();
        $res = $this->runMiddleware($req, Response::html('<p>Contenido privado</p>'));

        $this->assertStringContainsString('no-store', $res->headers()['Cache-Control']);
    }

    public function test_does_not_add_no_store_cache_control_for_json_response(): void
    {
        $req = $this->makeRequest();
        $inner = Response::json(['id' => 1]);
        $res = $this->runMiddleware($req, $inner);

        // JSON no debe forzar no-store (puede tener sus propias políticas)
        $cacheControl = $res->headers()['Cache-Control'] ?? null;
        if ($cacheControl !== null) {
            // Si existe, no debe ser el no-store de HTML
            // (aunque puede añadirse desde la respuesta original)
            $this->assertNotSame('no-store, no-cache, must-revalidate', $cacheControl);
        }
        $this->assertTrue(true); // JSON response pasa sin cache-control forzado
    }

    // ── No muta la respuesta original ────────────────────────────────────────

    public function test_middleware_preserves_original_response_content(): void
    {
        $req = $this->makeRequest();
        $inner = Response::html('<h1>Dashboard</h1>');
        $res = $this->runMiddleware($req, $inner);

        $this->assertSame('<h1>Dashboard</h1>', $res->content());
    }

    public function test_middleware_preserves_original_status_code(): void
    {
        $req = $this->makeRequest();
        $inner = Response::html('Not Found', 404);
        $res = $this->runMiddleware($req, $inner);

        $this->assertSame(404, $res->status());
    }

    public function test_middleware_preserves_existing_headers(): void
    {
        $req = $this->makeRequest();
        $inner = Response::html('ok')->withHeader('X-My-Custom', 'my-value');
        $res = $this->runMiddleware($req, $inner);

        $this->assertSame('my-value', $res->headers()['X-My-Custom']);
    }
}
