<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Validator;

final class AceptacionLegalValidator extends Validator
{
    /** @param array<string, mixed> $data */
    public function validateDocument(array $data): bool
    {
        return $this->validate($data, [
            'tipo' => 'required|min:2|max:80',
            'version' => 'required|min:1|max:40',
            'titulo' => 'required|min:3|max:180',
            'contenido' => 'required|min:20|max:50000',
        ]);
    }
}

