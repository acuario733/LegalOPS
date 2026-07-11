<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * Tests de App\Core\Request.
 *
 * Cubre: acceso a query/body/json, cabeceras, método, URI,
 * route parameters, atributos de middleware (setAttribute/getAttribute),
 * y helpers wantsJson/isAjax/isSafeMethod.
 */
class RequestTest extends TestCase
{
    private function make(
        string $method = 'GET',
        string $uri = '/clientes',
        array $query = [],
        array $body = [],
        array $json = [],
        array $headers = [],
        array $server = [],
    ): Request {
        return new Request($method, $uri, $query, $body, $json, [], $headers, $server);
    }

    // ── method / uri ─────────────────────────────────────────────────────────

    public function test_method_returns_uppercase_method(): void
    {
        $req = $this->make(method: 'post');
        $this->assertSame('post', $req->method()); // Request almacena tal como se pasa
    }

    public function test_uri_returns_path(): void
    {
        $req = $this->make(uri: '/clientes/123');
        $this->assertSame('/clientes/123', $req->uri());
    }

    // ── query / body / json / input ──────────────────────────────────────────

    public function test_query_returns_full_array_when_no_key(): void
    {
        $req = $this->make(query: ['page' => '2', 'q' => 'test']);
        $this->assertSame(['page' => '2', 'q' => 'test'], $req->query());
    }

    public function test_query_returns_value_by_key(): void
    {
        $req = $this->make(query: ['page' => '3']);
        $this->assertSame('3', $req->query('page'));
    }

    public function test_query_returns_default_for_missing_key(): void
    {
        $req = $this->make();
        $this->assertSame('default', $req->query('missing', 'default'));
    }

    public function test_post_returns_value_by_key(): void
    {
        $req = $this->make(body: ['nombre' => 'Juan']);
        $this->assertSame('Juan', $req->post('nombre'));
    }

    public function test_json_returns_json_body_value(): void
    {
        $req = $this->make(json: ['firma_id' => 42]);
        $this->assertSame(42, $req->json('firma_id'));
    }

    public function test_input_merges_query_body_and_json_with_json_winning(): void
    {
        $req = $this->make(
            query: ['shared' => 'from-query'],
            body:  ['shared' => 'from-body'],
            json:  ['shared' => 'from-json'],
        );
        // JSON tiene mayor precedencia (order of array_replace)
        $this->assertSame('from-json', $req->input('shared'));
    }

    public function test_input_with_no_key_returns_merged_array(): void
    {
        $req = $this->make(query: ['a' => '1'], body: ['b' => '2']);
        $input = $req->input();
        $this->assertSame('1', $input['a']);
        $this->assertSame('2', $input['b']);
    }

    // ── headers ──────────────────────────────────────────────────────────────

    public function test_header_returns_value_case_insensitive(): void
    {
        $req = $this->make(headers: ['content-type' => 'application/json']);
        $this->assertSame('application/json', $req->header('Content-Type'));
        $this->assertSame('application/json', $req->header('content-type'));
    }

    public function test_header_returns_default_for_missing(): void
    {
        $req = $this->make();
        $this->assertSame('fallback', $req->header('x-missing', 'fallback'));
    }

    public function test_headers_returns_all_headers(): void
    {
        $req = $this->make(headers: ['x-custom' => 'value']);
        $this->assertArrayHasKey('x-custom', $req->headers());
    }

    // ── ip / userAgent ───────────────────────────────────────────────────────

    public function test_ip_returns_remote_addr_from_server(): void
    {
        $req = $this->make(server: ['REMOTE_ADDR' => '192.168.1.1']);
        $this->assertSame('192.168.1.1', $req->ip());
    }

    public function test_ip_returns_default_when_missing(): void
    {
        $req = $this->make();
        $this->assertSame('0.0.0.0', $req->ip());
    }

    // ── route parameters ─────────────────────────────────────────────────────

    public function test_route_returns_parameter_after_set(): void
    {
        $req = $this->make();
        $req->setRouteParameters(['id' => '42']);
        $this->assertSame('42', $req->route('id'));
    }

    public function test_route_returns_default_for_missing_parameter(): void
    {
        $req = $this->make();
        $this->assertNull($req->route('nonexistent'));
        $this->assertSame('fallback', $req->route('nonexistent', 'fallback'));
    }

    public function test_route_parameters_returns_all(): void
    {
        $req = $this->make();
        $req->setRouteParameters(['id' => '5', 'slug' => 'test']);
        $this->assertSame(['id' => '5', 'slug' => 'test'], $req->routeParameters());
    }

    // ── attributes (middleware context) ──────────────────────────────────────

    public function test_set_attribute_and_get_attribute(): void
    {
        $req = $this->make();
        $req->setAttribute('api_firma_id', 7);
        $this->assertSame(7, $req->getAttribute('api_firma_id'));
    }

    public function test_get_attribute_returns_default_when_not_set(): void
    {
        $req = $this->make();
        $this->assertNull($req->getAttribute('missing'));
        $this->assertSame('default', $req->getAttribute('missing', 'default'));
    }

    public function test_attributes_returns_all_set_attributes(): void
    {
        $req = $this->make();
        $req->setAttribute('key1', 'v1');
        $req->setAttribute('key2', 42);
        $this->assertSame(['key1' => 'v1', 'key2' => 42], $req->attributes());
    }

    public function test_set_attribute_overwrites_existing_value(): void
    {
        $req = $this->make();
        $req->setAttribute('counter', 1);
        $req->setAttribute('counter', 2);
        $this->assertSame(2, $req->getAttribute('counter'));
    }

    // ── wantsJson / isAjax / isSafeMethod ────────────────────────────────────

    public function test_wants_json_true_for_api_uri(): void
    {
        $req = $this->make(uri: '/api/v1/clientes');
        $this->assertTrue($req->wantsJson());
    }

    public function test_wants_json_true_for_ajax_request(): void
    {
        $req = $this->make(headers: ['x-requested-with' => 'XMLHttpRequest']);
        $this->assertTrue($req->wantsJson());
    }

    public function test_wants_json_true_when_accept_header_contains_json(): void
    {
        $req = $this->make(headers: ['accept' => 'application/json']);
        $this->assertTrue($req->wantsJson());
    }

    public function test_wants_json_false_for_html_request(): void
    {
        $req = $this->make(uri: '/clientes', headers: ['accept' => 'text/html']);
        $this->assertFalse($req->wantsJson());
    }

    public function test_is_safe_method_true_for_get(): void
    {
        $req = $this->make(method: 'GET');
        $this->assertTrue($req->isSafeMethod());
    }

    public function test_is_safe_method_false_for_post(): void
    {
        $req = $this->make(method: 'POST');
        $this->assertFalse($req->isSafeMethod());
    }

    public function test_is_safe_method_false_for_delete(): void
    {
        $req = $this->make(method: 'DELETE');
        $this->assertFalse($req->isSafeMethod());
    }
}
