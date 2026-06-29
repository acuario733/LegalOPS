<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Validators\ProspectoValidator;
use PHPUnit\Framework\TestCase;

class ProspectoControllerTest extends TestCase
{
    private ProspectoValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ProspectoValidator();
    }

    public function test_store_rechaza_email_invalido(): void
    {
        $ok = $this->validator->validateData([
            'nombre'       => 'Empresa Test',
            'tipo_persona' => 'juridica',
            'email'        => 'no-es-email',
            'estado'       => 'nuevo',
        ]);

        $this->assertFalse($ok);
        $this->assertArrayHasKey('email', $this->validator->errors());
    }

    public function test_store_requiere_nombre(): void
    {
        $ok = $this->validator->validateData([
            'nombre'       => '',
            'tipo_persona' => 'natural',
            'estado'       => 'nuevo',
        ]);

        $this->assertFalse($ok);
        $this->assertArrayHasKey('nombre', $this->validator->errors());
    }

    public function test_store_acepta_datos_validos(): void
    {
        $ok = $this->validator->validateData([
            'nombre'       => 'Startup ABC',
            'tipo_persona' => 'juridica',
            'email'        => 'contact@startup.com',
            'telefono'     => '+573001234567',
            'estado'       => 'nuevo',
        ]);

        $this->assertTrue($ok);
        $this->assertEmpty($this->validator->errors());
    }
}
