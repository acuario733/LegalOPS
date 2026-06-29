<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\HttpException;
use App\Core\Response;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests de App\Core\Response.
 *
 * Cubre: html(), json(), error(), redirect(), withHeader(),
 * status(), content(), headers(), validaciones de seguridad.
 */
class ResponseTest extends TestCase
{
    // ── html() ───────────────────────────────────────────────────────────────

    public function test_html_sets_status_200_by_default(): void
    {
        $res = Response::html('<p>Hola</p>');
        $this->assertSame(200, $res->status());
    }

    public function test_html_sets_content_type_header(): void
    {
        $res = Response::html('<p>Hola</p>');
        $this->assertStringContainsString('text/html', $res->headers()['Content-Type']);
    }

    public function test_html_stores_content(): void
    {
        $res = Response::html('<h1>LegalOPS</h1>');
        $this->assertSame('<h1>LegalOPS</h1>', $res->content());
    }

    public function test_html_accepts_custom_status(): void
    {
        $res = Response::html('Not Found', 404);
        $this->assertSame(404, $res->status());
    }

    // ── json() ───────────────────────────────────────────────────────────────

    public function test_json_wraps_data_in_standard_envelope(): void
    {
        $res = Response::json(['id' => 1]);
        $body = json_decode($res->content(), true);

        $this->assertTrue($body['ok']);
        $this->assertSame(['id' => 1], $body['data']);
        $this->assertArrayHasKey('message', $body);
        $this->assertArrayHasKey('errors', $body);
    }

    public function test_json_sets_application_json_content_type(): void
    {
        $res = Response::json(null);
        $this->assertStringContainsString('application/json', $res->headers()['Content-Type']);
    }

    public function test_json_custom_message_and_status(): void
    {
        $res = Response::json(null, 'Recurso creado.', 201);
        $body = json_decode($res->content(), true);

        $this->assertSame(201, $res->status());
        $this->assertSame('Recurso creado.', $body['message']);
    }

    public function test_json_null_data_becomes_empty_object_in_payload(): void
    {
        $res = Response::json(null);
        $body = json_decode($res->content(), false); // stdClass

        $this->assertInstanceOf(\stdClass::class, $body->data);
    }

    // ── error() ──────────────────────────────────────────────────────────────

    public function test_error_sets_ok_false(): void
    {
        $res = Response::error('Algo salió mal', 422, ['campo' => ['requerido']]);
        $body = json_decode($res->content(), true);

        $this->assertFalse($body['ok']);
        $this->assertSame(422, $res->status());
        $this->assertSame(['campo' => ['requerido']], $body['errors']);
    }

    public function test_error_default_status_is_400(): void
    {
        $res = Response::error('Bad request');
        $this->assertSame(400, $res->status());
    }

    // ── redirect() ───────────────────────────────────────────────────────────

    public function test_redirect_sets_location_header(): void
    {
        $res = Response::redirect('/dashboard');
        $this->assertSame('/dashboard', $res->headers()['Location']);
    }

    public function test_redirect_default_status_is_302(): void
    {
        $res = Response::redirect('/login');
        $this->assertSame(302, $res->status());
    }

    public function test_redirect_accepts_301(): void
    {
        $res = Response::redirect('/nueva-ruta', 301);
        $this->assertSame(301, $res->status());
    }

    public function test_redirect_throws_on_invalid_code(): void
    {
        $this->expectException(RuntimeException::class);
        Response::redirect('/somewhere', 200);
    }

    public function test_redirect_throws_on_404_code(): void
    {
        $this->expectException(RuntimeException::class);
        Response::redirect('/somewhere', 404);
    }

    // ── withHeader() ─────────────────────────────────────────────────────────

    public function test_with_header_returns_new_instance(): void
    {
        $original = Response::html('content');
        $modified = $original->withHeader('X-Custom', 'value');

        $this->assertNotSame($original, $modified);
    }

    public function test_with_header_adds_header_to_clone(): void
    {
        $res = Response::html('content')->withHeader('X-Tracking', 'abc123');

        $this->assertSame('abc123', $res->headers()['X-Tracking']);
    }

    public function test_with_header_does_not_mutate_original(): void
    {
        $original = Response::html('content');
        $original->withHeader('X-Extra', 'value');

        $this->assertArrayNotHasKey('X-Extra', $original->headers());
    }

    public function test_with_header_throws_on_crlf_injection_in_name(): void
    {
        $this->expectException(RuntimeException::class);
        Response::html('x')->withHeader("X-Bad\r\nHeader", 'value');
    }

    public function test_with_header_throws_on_crlf_injection_in_value(): void
    {
        $this->expectException(RuntimeException::class);
        Response::html('x')->withHeader('X-Good', "value\r\nX-Injected: bad");
    }

    // ── headers() / status() / content() ─────────────────────────────────────

    public function test_headers_includes_all_set_headers(): void
    {
        $res = Response::json(null)->withHeader('X-Request-Id', 'req-1');

        $this->assertArrayHasKey('X-Request-Id', $res->headers());
        $this->assertArrayHasKey('Content-Type', $res->headers());
    }

    public function test_json_with_errors_includes_errors_in_body(): void
    {
        $res = Response::json(null, 'Error', 422, ['email' => ['Inválido']], false);
        $body = json_decode($res->content(), true);

        $this->assertSame(['email' => ['Inválido']], $body['errors']);
    }
}
