<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;

class Validator
{
    /** @var array<string, list<string>> */
    protected array $errors = [];

    /** @var array<string, mixed> */
    protected array $validated = [];

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|list<string>> $rules
     * @param array<string, mixed> $files
     */
    public function validate(array $data, array $rules, array $files = []): bool
    {
        $this->errors = [];
        $this->validated = [];

        foreach ($rules as $field => $fieldRules) {
            $ruleList = is_array($fieldRules) ? $fieldRules : explode('|', $fieldRules);
            $value = $data[$field] ?? null;
            $isRequired = in_array('required', $ruleList, true);

            if (!$isRequired && $this->isEmpty($value) && !in_array('file', $ruleList, true)) {
                continue;
            }

            foreach ($ruleList as $ruleDefinition) {
                [$rule, $parameters] = $this->parseRule($ruleDefinition);
                if (!$this->passes($rule, $value, $parameters, $files[$field] ?? null)) {
                    $this->errors[$field][] = $this->message($field, $rule, $parameters);
                }
            }

            if (!isset($this->errors[$field])) {
                $this->validated[$field] = in_array('file', $ruleList, true) ? ($files[$field] ?? null) : $value;
            }
        }

        return $this->errors === [];
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<string, mixed> */
    public function validated(): array
    {
        return $this->validated;
    }

    /** @param list<string> $parameters */
    protected function passes(string $rule, mixed $value, array $parameters, mixed $file): bool
    {
        return match ($rule) {
            'required' => !$this->isEmpty($value),
            'min' => is_string($value) && mb_strlen($value) >= (int) ($parameters[0] ?? 0),
            'max' => is_string($value) && mb_strlen($value) <= (int) ($parameters[0] ?? PHP_INT_MAX),
            'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'date' => is_string($value) && $this->isDate($value, $parameters[0] ?? null),
            'numeric', 'number' => is_numeric($value),
            'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false,
            'enum', 'in' => in_array((string) $value, $parameters, true),
            'file' => $this->isValidFile($file),
            'documento' => is_string($value) && preg_match('/^[0-9]{6,}$/', $value) === 1,
            'phone' => is_string($value) && preg_match('/^\+?[0-9\s\-\(\)]{7,}$/', $value) === 1,
            default => false,
        };
    }

    /** @return array{0: string, 1: list<string>} */
    private function parseRule(string $definition): array
    {
        $parts = explode(':', $definition, 2);
        $parameters = isset($parts[1]) && $parts[1] !== '' ? explode(',', $parts[1]) : [];

        return [strtolower(trim($parts[0])), array_map('trim', $parameters)];
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || (is_array($value) && $value === []);
    }

    private function isDate(string $value, ?string $format): bool
    {
        if ($format === null || $format === '') {
            return strtotime($value) !== false;
        }

        $date = DateTimeImmutable::createFromFormat($format, $value);

        return $date !== false && $date->format($format) === $value;
    }

    private function isValidFile(mixed $file): bool
    {
        return is_array($file)
            && isset($file['error'], $file['tmp_name'], $file['name'], $file['size'])
            && (int) $file['error'] === UPLOAD_ERR_OK
            && is_string($file['tmp_name'])
            && $file['tmp_name'] !== '';
    }

    /** @param list<string> $parameters */
    private function message(string $field, string $rule, array $parameters): string
    {
        $label = str_replace('_', ' ', $field);

        return match ($rule) {
            'required' => sprintf('El campo %s es obligatorio.', $label),
            'min' => sprintf('El campo %s debe tener al menos %d caracteres.', $label, (int) ($parameters[0] ?? 0)),
            'max' => sprintf('El campo %s no puede superar %d caracteres.', $label, (int) ($parameters[0] ?? 0)),
            'email' => sprintf('El campo %s debe ser un correo válido.', $label),
            'date' => sprintf('El campo %s debe ser una fecha válida.', $label),
            'numeric', 'number' => sprintf('El campo %s debe ser numérico.', $label),
            'integer' => sprintf('El campo %s debe ser un entero.', $label),
            'enum', 'in' => sprintf('El valor seleccionado para %s no es válido.', $label),
            'file' => sprintf('El archivo %s no es válido.', $label),
            'documento' => sprintf('El campo %s debe tener mínimo 6 dígitos numéricos.', $label),
            'phone' => sprintf('El campo %s debe ser un teléfono válido (mínimo 7 caracteres).', $label),
            default => sprintf('El campo %s no es válido.', $label),
        };
    }
}
