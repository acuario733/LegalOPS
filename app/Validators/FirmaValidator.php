<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Validator;

final class FirmaValidator extends Validator
{
    /** @param array<string, mixed> $data */
    public function validateData(array $data): bool
    {
        return $this->validate($data, [
            'nombre' => 'required|min:3|max:180',
            'slug' => 'required|min:3|max:120',
            'timezone' => 'required|max:64',
        ]);
    }
}
