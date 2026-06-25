<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Validator;

final class TareaValidator extends Validator
{
    /** @param array<string, mixed> $data */
    public function validateData(array $data): bool
    {
        return $this->validate($data, [
            'caso_id' => 'integer',
            'termino_id' => 'integer',
            'responsable_usuario_id' => 'integer',
            'titulo' => 'required|min:3|max:180',
            'descripcion' => 'max:2000',
            'prioridad' => 'required|enum:baja,media,alta,critica',
            'estado' => 'required|enum:pendiente,en_proceso,completada,vencida,cancelada',
            'fecha_vencimiento' => 'date:Y-m-d',
        ]);
    }

    /** @param array<string, mixed> $data */
    public function validateStatus(array $data): bool
    {
        return $this->validate($data, [
            'estado' => 'required|enum:pendiente,en_proceso,completada,vencida,cancelada',
        ]);
    }

    /** @param array<string, mixed> $data */
    public function validateReassign(array $data): bool
    {
        return $this->validate($data, [
            'responsable_usuario_id' => 'required|integer',
        ]);
    }
}
