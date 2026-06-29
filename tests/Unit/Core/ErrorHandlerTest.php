<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\ErrorHandler;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests de App\Core\ErrorHandler.
 *
 * Cubre: handle() devuelve JSON para requests API, HTML para HTML requests,
 * incluye correlation_id, usa el status correcto de HttpException,
 * sanitiza mensajes con datos sensibles, escribe el log.
 */
class ErrorHandlerTest extends TestCase
{
    private string $logFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logFile = sys_get_temp_dir() . '/errorhandler_test_' . uniqid() . '.log';
    }

    protected function tearDown(): void
    {
        if (is_file($this->logFile)) {
            @unlink($this->logFile);
        }
        parent::tearDown();
    }

    private function makeHandler(): ErrorHandler
    {
        return new ErrorHandler(logPath: $this->logFile);
    }

    private function makeRequest(
        string $method = 'GET',
        string $uri = '/dashboard',
        array $headers = [],
    ): Request {
        return new Request($method, $uri, headers: $headers);
    }

    private function makeApiRequest(string $uri = '/api/v1/clientes'): Request
    {
        return new Request('GET', $uri, headers: ['accept' => 'application/json']);
    }

    // ── JSON response para peticiones API ────────────────────────────────────

    public function test_handle_returns_json_for_api_request(): void
    {
        $handler = $this->makeHandler();
        $request = $this->makeApiRequest();

        $response = $handler->handle(new RuntimeException('Crash'), $request);
        $body = json_decode($response->content(), true);

        $this->assertArrayHasKey('ok', $body);
        $this->assertFalse($body['ok']);
    }

    public function test_handle_json_includes_correlation_id_in_body(): void
    {
        $handler = $this->makeHandler();
        $response = $handler->handle(new RuntimeException('error'), $this->makeApiRequest());
        $body = json_decode($response->content(), true);

        $this->assertArrayHasKey('correlation_id', $body['data']);
        $this->assertNotEmpty($body['data']['correlation_id']);
    }

    public function test_handle_json_includes_correlation_id_header(): void
    {
        $handler = $this->makeHandler();
        $response = $handler->handle(new RuntimeException('error'), $this->makeApiRequest());

        $this->assertArrayHasKey('X-Correlation-ID', $response->headers());
    }

    // ── Status code correcto ──────────────────────────────────────────────────

    public function test_handle_uses_http_exception_status(): void
    {
        $handler = $this->makeHandler();
        $response = $handler->handle(
            new HttpException(422, 'Validación fallida'),
            $this->makeApiRequest()
        );

        $this->assertSame(422, $response->status());
    }

    public function test_handle_uses_500_for_generic_exceptions(): void
    {
        $handler = $this->makeHandler();
        $response = $handler->handle(new RuntimeException('Crash'), $this->makeApiRequest());

        $this->assertSame(500, $response->status());
    }

    public function test_handle_uses_404_for_not_found_exception(): void
    {
        $handler = $this->makeHandler();
        $response = $handler->handle(
            new HttpException(404, 'No encontrado'),
            $this->makeApiRequest()
        );

        $this->assertSame(404, $response->status());
    }

    // ── HTML response para peticiones normales ────────────────────────────────

    public function test_handle_returns_html_for_normal_request(): void
    {
        $handler = $this->makeHandler();
        $request = $this->makeRequest();
        $response = $handler->handle(new HttpException(404, 'No encontrado'), $request);

        $this->assertStringContainsString('text/html', $response->headers()['Content-Type']);
        $this->assertStringContainsString('404', $response->content());
    }

    public function test_handle_html_includes_correlation_id(): void
    {
        $handler = $this->makeHandler();
        $request = $this->makeRequest();
        $response = $handler->handle(new RuntimeException('Server error'), $request);

        $this->assertStringContainsString('Referencia', $response->content());
    }

    public function test_handle_html_uses_safe_generic_message_for_500(): void
    {
        $handler = $this->makeHandler();
        $request = $this->makeRequest();
        // El mensaje real del RuntimeException NO debe aparecer en la respuesta
        $response = $handler->handle(
            new RuntimeException('SELECT * FROM usuarios WHERE ...'),
            $request
        );

        $this->assertStringNotContainsString('SELECT', $response->content());
        $this->assertStringContainsString('error inesperado', $response->content());
    }

    // ── Escritura de log ──────────────────────────────────────────────────────

    public function test_handle_writes_json_log_entry(): void
    {
        $handler = $this->makeHandler();
        $handler->handle(new RuntimeException('test error'), $this->makeApiRequest());

        $this->assertFileExists($this->logFile);
        $log = file_get_contents($this->logFile);
        $entry = json_decode(trim($log), true);

        $this->assertIsArray($entry);
        $this->assertArrayHasKey('timestamp', $entry);
        $this->assertArrayHasKey('correlation_id', $entry);
        $this->assertArrayHasKey('exception', $entry);
    }

    public function test_handle_creates_log_directory_if_needed(): void
    {
        $nestedLog = sys_get_temp_dir() . '/nested_' . uniqid() . '/sub/app.log';
        $handler = new ErrorHandler(logPath: $nestedLog);
        $handler->handle(new RuntimeException('test'), $this->makeApiRequest());

        $this->assertFileExists($nestedLog);
        @unlink($nestedLog);
        @rmdir(dirname($nestedLog));
        @rmdir(dirname($nestedLog, 2));
    }

    public function test_log_sanitizes_token_in_message(): void
    {
        $handler = $this->makeHandler();
        $handler->handle(
            new RuntimeException('token=abc123secret was invalid'),
            $this->makeApiRequest()
        );

        $log = file_get_contents($this->logFile);
        $this->assertStringNotContainsString('abc123secret', $log);
        $this->assertStringContainsString('[REDACTED]', $log);
    }

    // ── HttpException conserva su mensaje ────────────────────────────────────

    public function test_http_exception_message_appears_in_json_response(): void
    {
        $handler = $this->makeHandler();
        $response = $handler->handle(
            new HttpException(403, 'No tiene permisos para esta acción'),
            $this->makeApiRequest()
        );
        $body = json_decode($response->content(), true);

        $this->assertStringContainsString('permisos', $body['message']);
    }

    public function test_http_exception_errors_array_in_json_response(): void
    {
        $handler = $this->makeHandler();
        $errors = ['campo' => ['Requerido']];
        $response = $handler->handle(
            new HttpException(422, 'Validación', $errors),
            $this->makeApiRequest()
        );
        $body = json_decode($response->content(), true);

        $this->assertSame($errors, $body['errors']);
    }
}
