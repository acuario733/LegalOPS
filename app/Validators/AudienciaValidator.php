<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Validator;

final class AudienciaValidator extends Validator
{
    /** @param array<string, mixed> $data */
    public function validateData(array $data): bool
    {
        return $this->validate($data, [
            'caso_id' => 'required|integer',
            'responsable_usuario_id' => 'integer',
            'titulo' => 'required|min:3|max:180',
            'fecha' => 'required|date:Y-m-d',
            'hora' => 'required|date:H:i',
            'modalidad' => 'required|enum:presencial,virtual,mixta,telefonica,otra',
            'despacho' => 'max:180',
            'lugar' => 'max:255',
            'enlace' => 'max:500',
            'estado' => 'required|enum:programada,realizada,cancelada',
        ]);
    }

    /** @param array<string, mixed> $data */
    public function validateResult(array $data): bool
    {
        return $this->validate($data, [
            'resultado' => 'required|min:3|max:4000',
        ]);
    }
}
