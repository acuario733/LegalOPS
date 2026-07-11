<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Validator;

final class PlanValidator extends Validator
{
    /** @param array<string, mixed> $data */
    public function validateData(array $data): bool
    {
        return $this->validate($data, [
            'codigo' => 'required|min:2|max:60',
            'nombre' => 'required|min:2|max:120',
            'descripcion' => 'max:1000',
            'estado' => 'required|enum:activo,inactivo',
        ]);
    }
}
