<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Validator;

final class DocumentoValidator extends Validator
{
    /** @param array<string, mixed> $data */
    public function validateData(array $data): bool
    {
        return $this->validate($data, [
            'cliente_id' => 'integer',
            'caso_id' => 'integer',
            'gasto_id' => 'integer',
            'titulo' => 'required|min:3|max:180',
            'descripcion' => 'max:2000',
            'tipo_documental' => 'max:80',
            'estado' => 'required|enum:activo',
        ]);
    }
}
