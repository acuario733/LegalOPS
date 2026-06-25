<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Validator;

final class CasoValidator extends Validator
{
    /** @param array<string, mixed> $data */
    public function validateData(array $data): bool
    {
        return $this->validate($data, [
            'cliente_id' => 'required|integer',
            'titulo' => 'required|min:3|max:180',
            'estado' => 'required|enum:activo,cerrado,archivado',
            'prioridad' => 'required|enum:baja,media,alta,critica',
            'tipo_proceso' => 'max:120',
            'jurisdiccion' => 'max:120',
            'despacho' => 'max:180',
            'radicado' => 'max:120',
            'responsable_usuario_id' => 'integer',
            'fecha_apertura' => 'date:Y-m-d',
            'descripcion' => 'max:2000',
        ]);
    }

    /** @param array<string, mixed> $data */
    public function validateClose(array $data): bool
    {
        return $this->validate($data, [
            'motivo' => 'required|min:5|max:500',
        ]);
    }
}
