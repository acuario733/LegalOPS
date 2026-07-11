<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Validator;

final class CatalogoValidator extends Validator
{
    /** @param array<string, mixed> $data */
    public function validateCatalog(array $data): bool
    {
        return $this->validate($data, [
            'codigo' => 'required|min:2|max:100',
            'nombre' => 'required|min:2|max:150',
            'alcance' => 'required|enum:firma,global',
        ]);
    }

    /** @param array<string, mixed> $data */
    public function validateItem(array $data): bool
    {
        return $this->validate($data, [
            'codigo' => 'required|min:1|max:100',
            'etiqueta' => 'required|min:1|max:180',
            'orden' => 'integer',
        ]);
    }
}
