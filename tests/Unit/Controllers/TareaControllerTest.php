<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Validators\TareaValidator;
use PHPUnit\Framework\TestCase;

class TareaControllerTest extends TestCase
{
    private TareaValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new TareaValidator();
    }

    public function test_store_requiere_titulo(): void
    {
        $ok = $this->validator->validateData([
            'titulo'    => 'AB', // < 3 chars
            'prioridad' => 'alta',
            'estado'    => 'pendiente',
        ]);

        $this->assertFalse($ok);
        $this->assertArrayHasKey('titulo', $this->validator->errors());
    }

    public function test_store_requiere_estado_valido(): void
    {
        $ok = $this->validator->validateData([
            'titulo'    => 'Revisar documento',
            'prioridad' => 'alta',
            'estado'    => 'inventado',
        ]);

        $this->assertFalse($ok);
        $this->assertArrayHasKey('estado', $this->validator->errors());
    }

    public function test_store_acepta_datos_validos(): void
    {
        $ok = $this->validator->validateData([
            'titulo'                  => 'Revisar contrato',
            'prioridad'               => 'alta',
            'estado'                  => 'pendiente',
            'fecha_vencimiento'       => '2026-12-31',
            'responsable_usuario_id'  => '5',
        ]);

        $this->assertTrue($ok);
        $this->assertEmpty($this->validator->errors());
    }
}
