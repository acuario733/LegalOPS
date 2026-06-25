<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Validator;

final class TerminoValidator extends Validator
{
    /** @param array<string, mixed> $data */
    public function validateData(array $data): bool
    {
        return $this->validate($data, [
            'caso_id' => 'integer',
            'tarea_id' => 'integer',
            'responsable_usuario_id' => 'integer',
            'titulo' => 'required|min:3|max:180',
            'descripcion' => 'max:2000',
            'fecha_inicio' => 'required|date:Y-m-d',
            'fecha_vencimiento' => 'required|date:Y-m-d',
            'prioridad' => 'required|enum:baja,media,alta,critica',
            'estado' => 'required|enum:vigente,proximo,critico,vencido,cumplido',
            'alerta_dias' => 'required|integer',
        ]);
    }

    /** @param array<string, mixed> $data */
    public function validateComplete(array $data): bool
    {
        return $this->validate($data, [
            'observacion' => 'max:1000',
        ]);
    }
}
