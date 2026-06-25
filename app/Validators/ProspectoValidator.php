<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Validator;

final class ProspectoValidator extends Validator
{
    /** @param array<string, mixed> $data */
    public function validateData(array $data): bool
    {
        return $this->validate($data, [
            'nombre' => 'required|min:3|max:180',
            'tipo_persona' => 'required|enum:natural,juridica',
            'email' => 'email|max:254',
            'telefono' => 'max:60',
            'tipo_documento' => 'max:40',
            'numero_documento' => 'max:80',
            'empresa' => 'max:180',
            'fuente' => 'max:120',
            'estado' => 'required|enum:nuevo,contactado,consulta,cotizacion,negociacion,ganado,perdido',
            'responsable_usuario_id' => 'integer',
            'valor_estimado' => 'numeric',
            'notas' => 'max:1000',
        ]);
    }

    /** @param array<string, mixed> $data */
    public function validateStatus(array $data): bool
    {
        return $this->validate($data, [
            'estado' => 'required|enum:nuevo,contactado,consulta,cotizacion,negociacion,ganado,perdido',
        ]);
    }
}
