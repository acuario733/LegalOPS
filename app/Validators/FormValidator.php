<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Validator;

/**
 * Validador centralizado de formularios.
 * Soporta: required, email, minLength, maxLength, documento, phone.
 *
 * @return array<string, list<string>> errores indexados por campo
 */
final class FormValidator extends Validator
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, string|list<string>> $rules  Sintaxis: 'required|email|minLength:3'
     * @return array<string, list<string>>
     */
    public function validateForm(array $data, array $rules): array
    {
        $normalized = [];
        foreach ($rules as $field => $fieldRules) {
            $ruleList = is_array($fieldRules) ? $fieldRules : explode('|', $fieldRules);
            $normalized[$field] = array_map(
                static fn(string $r) => str_replace(['minLength', 'maxLength'], ['min', 'max'], $r),
                $ruleList,
            );
        }

        $this->validate($data, $normalized);

        return $this->errors();
    }
}
