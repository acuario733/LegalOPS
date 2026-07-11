<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Validator;

final class PerfilValidator extends Validator
{
    /** @param array<string, mixed> $data */
    public function validatePersonal(array $data): bool
    {
        $valid = $this->validate($data, [
            'nombres' => 'required|min:1|max:160',
            'apellidos' => 'required|min:1|max:160',
            'tipo_documento_id' => 'required|integer',
            'numero_documento' => 'required|min:3|max:80',
            'telefono' => 'max:25',
        ]);

        $phone = trim((string) ($data['telefono'] ?? ''));
        if ($phone !== '' && preg_match('/^\+?[0-9][0-9\s().-]{5,23}[0-9]$/', $phone) !== 1) {
            $this->errors['telefono'][] = 'El teléfono debe contener entre 7 y 25 caracteres y usar un formato válido.';
            $valid = false;
        }

        return $valid;
    }

    /** @param array<string, mixed> $data */
    public function validateProfessional(array $data): bool
    {
        $valid = $this->validate($data, [
            'es_abogado' => 'required|integer',
            'tiene_tarjeta_profesional' => 'required|integer',
            'numero_tarjeta_profesional' => 'max:80',
        ]);

        $isLawyer = (int) ($data['es_abogado'] ?? 0) === 1;
        $hasCard = (int) ($data['tiene_tarjeta_profesional'] ?? 0) === 1;
        $card = trim((string) ($data['numero_tarjeta_profesional'] ?? ''));

        if ($hasCard && !$isLawyer) {
            $this->errors['tiene_tarjeta_profesional'][] = 'Solo un perfil marcado como abogado(a) puede registrar tarjeta profesional.';
            $valid = false;
        }
        if ($hasCard && mb_strlen($card) < 3) {
            $this->errors['numero_tarjeta_profesional'][] = 'Ingrese el nÃºmero de tarjeta profesional.';
            $valid = false;
        }
        if ($card !== '' && preg_match('/^[\p{L}\p{N}\s.-]+$/u', $card) !== 1) {
            $this->errors['numero_tarjeta_profesional'][] = 'La tarjeta profesional solo puede contener letras, nÃºmeros, espacios, puntos o guiones.';
            $valid = false;
        }

        return $valid;
    }
}
