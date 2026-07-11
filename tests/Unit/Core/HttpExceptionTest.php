<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\HttpException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests de App\Core\HttpException.
 */
class HttpExceptionTest extends TestCase
{
    public function test_extends_runtime_exception(): void
    {
        $e = new HttpException(404, 'Not Found');
        $this->assertInstanceOf(RuntimeException::class, $e);
    }

    public function test_status_returns_http_code(): void
    {
        $e = new HttpException(403, 'Forbidden');
        $this->assertSame(403, $e->status());
    }

    public function test_message_returns_constructor_message(): void
    {
        $e = new HttpException(422, 'Unprocessable Entity');
        $this->assertSame('Unprocessable Entity', $e->getMessage());
    }

    public function test_errors_returns_empty_array_by_default(): void
    {
        $e = new HttpException(400, 'Bad Request');
        $this->assertSame([], $e->errors());
    }

    public function test_errors_returns_provided_errors(): void
    {
        $errors = ['nombre' => ['El campo nombre es requerido.']];
        $e = new HttpException(422, 'Validación fallida', $errors);
        $this->assertSame($errors, $e->errors());
    }

    public function test_different_status_codes(): void
    {
        foreach ([400, 401, 403, 404, 405, 422, 429, 500, 503] as $code) {
            $e = new HttpException($code, 'Error ' . $code);
            $this->assertSame($code, $e->status());
        }
    }

    public function test_errors_can_contain_nested_arrays(): void
    {
        $errors = [
            'email' => ['Inválido', 'Ya registrado'],
            'nombre' => ['Requerido'],
        ];
        $e = new HttpException(422, 'Validación', $errors);
        $this->assertCount(2, $e->errors()['email']);
    }
}
