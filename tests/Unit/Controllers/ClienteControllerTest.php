<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Validators\ClienteValidator;
use PHPUnit\Framework\TestCase;

/**
 * Prueba la lógica de validación que ClienteController::store() / update() aplican.
 * Se usa ClienteValidator directamente para evitar instanciar el controller completo
 * (que necesita DI, sesión, BD, etc.).
 */
class ClienteControllerTest extends TestCase
{
    private ClienteValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ClienteValidator();
    }

    public function test_store_rechaza_email_invalido(): void
    {
        $ok = $this->validator->validateCreate([
            'tipo_persona'       => 'natural',
            'nombre_razon_social' => 'Test Corp',
            'email'              => 'no-es-email',
            'estado'             => 'activo',
        ]);

        $this->assertFalse($ok);
        $this->assertArrayHasKey('email', $this->validator->errors());
    }

    public function test_store_rechaza_documento_invalido(): void
    {
        $ok = $this->validator->validateCreate([
            'tipo_persona'       => 'natural',
            'nombre_razon_social' => 'Test Corp',
            'email'              => 'ok@ok.com',
            'numero_documento'   => '12345',   // < 6 dígitos
            'estado'             => 'activo',
        ]);

        $this->assertFalse($ok);
        $this->assertArrayHasKey('numero_documento', $this->validator->errors());
    }

    public function test_store_rechaza_nombre_demasiado_corto(): void
    {
        $ok = $this->validator->validateCreate([
            'tipo_persona'       => 'natural',
            'nombre_razon_social' => 'AB',    // < 3 chars
            'email'              => 'ok@ok.com',
            'estado'             => 'activo',
        ]);

        $this->assertFalse($ok);
        $this->assertArrayHasKey('nombre_razon_social', $this->validator->errors());
    }

    public function test_store_rechaza_tipo_persona_invalido(): void
    {
        $ok = $this->validator->validateCreate([
            'tipo_persona'       => 'extraterrestre',
            'nombre_razon_social' => 'Test Corp',
            'email'              => 'ok@ok.com',
            'estado'             => 'activo',
        ]);

        $this->assertFalse($ok);
        $this->assertArrayHasKey('tipo_persona', $this->validator->errors());
    }

    public function test_store_acepta_datos_validos(): void
    {
        $ok = $this->validator->validateCreate([
            'tipo_persona'       => 'juridica',
            'nombre_razon_social' => 'Test Corporation S.A.',
            'email'              => 'contact@test.com',
            'numero_documento'   => '900123456',
            'telefono'           => '+573001234567',
            'estado'             => 'activo',
        ]);

        $this->assertTrue($ok);
        $this->assertEmpty($this->validator->errors());
    }

    public function test_store_retorna_422_logicamente_cuando_hay_errores(): void
    {
        $ok = $this->validator->validateCreate([
            'tipo_persona'       => 'natural',
            'nombre_razon_social' => 'AB',
            'email'              => 'invalido',
            'estado'             => 'activo',
        ]);

        $this->assertFalse($ok);
        // Simula que el controller respondería 422 con los errores
        $errors = $this->validator->errors();
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('nombre_razon_social', $errors);
        $this->assertArrayHasKey('email', $errors);
    }

    public function test_update_acepta_datos_validos(): void
    {
        $ok = $this->validator->validateUpdate([
            'tipo_persona'       => 'natural',
            'nombre_razon_social' => 'Nombre Actualizado',
            'email'              => 'nuevo@correo.com',
            'estado'             => 'inactivo',
        ]);

        $this->assertTrue($ok);
    }
}
