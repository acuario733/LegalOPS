<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Validator;

final class CasoTimelineValidator extends Validator
{
    /** @param array<string, mixed> $data */
    public function validateData(array $data): bool
    {
        return $this->validate($data, [
            'fecha_evento' => 'required|date:Y-m-d',
            'tipo_evento' => 'required|max:80',
            'titulo' => 'required|min:3|max:180',
            'contenido_publico' => 'max:2000',
            'contenido_interno' => 'max:4000',
            'visibilidad' => 'required|enum:interna,publica',
            'documento_id' => 'integer',
            'estado' => 'required|enum:activo',
        ]);
    }

    /** @param array<string, mixed> $data */
    public function validateVisibility(array $data): bool
    {
        return $this->validate($data, [
            'visibilidad' => 'required|enum:interna,publica',
        ]);
    }
}
