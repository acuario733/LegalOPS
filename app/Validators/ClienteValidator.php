<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Validator;

final class ClienteValidator extends Validator
{
    /** @param array<string, mixed> $data */
    public function validateCreate(array $data): bool
    {
        return $this->validate($data, $this->rules());
    }

    /** @param array<string, mixed> $data */
    public function validateUpdate(array $data): bool
    {
        return $this->validate($data, $this->rules());
    }

    /** @param array<string, mixed> $data */
    public function validateReveal(array $data): bool
    {
        return $this->validate($data, [
            'campo' => 'required|enum:numero_documento,email,telefono,direccion',
        ]);
    }

    /** @return array<string, string> */
    private function rules(): array
    {
        return [
            'tipo_persona' => 'required|enum:natural,juridica',
            'nombre_razon_social' => 'required|min:3|max:180',
            'tipo_documento' => 'max:40',
            'numero_documento' => 'max:80',
            'email' => 'email|max:254',
            'telefono' => 'max:60',
            'direccion' => 'max:255',
            'estado' => 'required|enum:activo,inactivo',
            'origen' => 'max:120',
            'observaciones' => 'max:1000',
            'autorizacion_medio' => 'max:120',
            'autorizacion_version' => 'max:80',
            'autorizacion_observacion' => 'max:500',
        ];
    }
}
