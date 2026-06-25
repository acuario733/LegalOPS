<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Validator;

final class CasoParteValidator extends Validator
{
    /** @param array<string, mixed> $data */
    public function validateData(array $data): bool
    {
        return $this->validate($data, [
            'tipo_parte' => 'required|enum:demandante,demandado,contraparte,testigo,tercero,apoderado,entidad,otro',
            'nombre' => 'required|min:3|max:180',
            'tipo_documento' => 'max:40',
            'numero_documento' => 'max:80',
            'email' => 'email|max:254',
            'telefono' => 'max:60',
            'direccion' => 'max:255',
            'estado' => 'required|enum:activo,inactivo',
            'observaciones' => 'max:1000',
        ]);
    }

    /** @param array<string, mixed> $data */
    public function validateReveal(array $data): bool
    {
        return $this->validate($data, [
            'campo' => 'required|enum:numero_documento,email,telefono,direccion',
        ]);
    }
}
