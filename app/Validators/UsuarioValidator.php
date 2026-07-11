<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Validator;

final class UsuarioValidator extends Validator
{
    /** @param array<string, mixed> $data */
    public function validateCreate(array $data): bool
    {
        return $this->validate($data, [
            'nombre' => 'required|min:3|max:160',
            'email' => 'required|email|max:254',
            'tipo' => 'required|enum:interno,cliente_externo',
            'password' => 'required|min:12|max:200',
        ]);
    }

    /** @param array<string, mixed> $data */
    public function validateUpdate(array $data): bool
    {
        return $this->validate($data, [
            'nombre' => 'required|min:3|max:160',
            'email' => 'required|email|max:254',
            'tipo' => 'required|enum:interno,cliente_externo',
            'estado' => 'required|enum:activo,inactivo',
        ]);
    }
}
