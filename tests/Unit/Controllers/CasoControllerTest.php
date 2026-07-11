<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Validators\CasoValidator;
use PHPUnit\Framework\TestCase;

class CasoControllerTest extends TestCase
{
    private CasoValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new CasoValidator();
    }

    public function test_store_requiere_titulo(): void
    {
        $ok = $this->validator->validateData([
            'cliente_id' => '1',
            'titulo'     => '',
            'estado'     => 'activo',
            'prioridad'  => 'media',
        ]);

        $this->assertFalse($ok);
        $this->assertArrayHasKey('titulo', $this->validator->errors());
    }

    public function test_store_requiere_prioridad_valida(): void
    {
        $ok = $this->validator->validateData([
            'cliente_id' => '1',
            'titulo'     => 'Caso de prueba',
            'estado'     => 'activo',
            'prioridad'  => 'urgentisima', // valor no permitido
        ]);

        $this->assertFalse($ok);
        $this->assertArrayHasKey('prioridad', $this->validator->errors());
    }

    public function test_store_acepta_datos_validos(): void
    {
        $ok = $this->validator->validateData([
            'cliente_id'  => '1',
            'titulo'      => 'Caso legal importante',
            'estado'      => 'activo',
            'prioridad'   => 'alta',
            'descripcion' => 'Descripción del caso.',
        ]);

        $this->assertTrue($ok);
        $this->assertEmpty($this->validator->errors());
    }
}
