<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Validator;

final class LoginValidator extends Validator
{
    /** @param array<string, mixed> $data */
    public function validateData(array $data): bool
    {
        return $this->validate($data, [
            'email' => 'required|email|max:254',
            'password' => 'required|min:8|max:200',
            'firma' => 'max:120',
        ]);
    }
}

