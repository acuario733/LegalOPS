<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Validator;

final class HonorarioValidator extends Validator
{
    /** @param array<string, mixed> $data */
    public function validateData(array $data): bool
    {
        return $this->validate($data, [
            'cliente_id' => 'required|integer',
            'caso_id' => 'integer',
            'concepto' => 'required|min:3|max:180',
            'descripcion' => 'max:2000',
            'monto' => 'required|numeric',
            'moneda' => 'required|max:3',
            'fecha_acuerdo' => 'required|date:Y-m-d',
            'estado' => 'required|enum:pendiente,parcial,pagado,cancelado',
        ]);
    }
}
